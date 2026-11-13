<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/oauth-lib.php';
$action=$_GET['action']??'metadata';$method=$_SERVER['REQUEST_METHOD'];
if ($method==='GET' && $action==='resource') reply(200,resourceMetadata());
if ($method==='GET' && $action==='metadata') reply(200,oauthMetadata());
if ($action==='register' && $method==='POST') {
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) fail(413,'Client metadata too large');
    $input=json_decode((string)file_get_contents('php://input',false,null,0,16001),true);
    if (!is_array($input)) fail(400,'Invalid client metadata');
    $uris=$input['redirect_uris']??[];
    if (!is_array($uris) || !array_is_list($uris) || !$uris || count($uris)>10 || ($input['token_endpoint_auth_method']??'none')!=='none') fail(400,'Public PKCE clients only');
    foreach ($uris as $uri) {
        if (!is_string($uri) || strlen($uri)>2000) fail(400,'Invalid redirect URI');
        $u=parse_url($uri);
        if (!$u || preg_match('/[\x00-\x20\x7f]/',$uri) || isset($u['fragment']) || isset($u['user']) || isset($u['pass']) || empty($u['host']) || !(($u['scheme']??'')==='https' || (($u['scheme']??'')==='http' && in_array($u['host'],['127.0.0.1','localhost','[::1]'],true)))) fail(400,'HTTPS redirect URI required (loopback HTTP allowed)');
    }
    $client=oauthStore(function(&$db)use($input,$uris){
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');
        $recent=array_filter($db['clients'],fn($c)=>($c['ip']??'')===$ip && $c['created']>time()-3600);
        if (count($recent)>=20 || count($db['clients'])>=1000) fail(429,'Too many client registrations');
        $id='wcos-'.bin2hex(random_bytes(16));
        $c=['client_id'=>$id,'client_name'=>substr(is_string($input['client_name']??null)?$input['client_name']:'MCP client',0,160),'redirect_uris'=>$uris,'token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code']];
        $db['clients'][$id]=[...$c,'ip'=>$ip,'created'=>time(),'expires'=>time()+31536000];return $c;
    });reply(201,$client);
}
if ($action==='token' && $method==='POST') {
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) fail(413,'Request too large');
    try {
        $tokens=oauthStore(function(&$db){
            $id=$_POST['client_id']??'';$resource=$_POST['resource']??'';
            if (!is_string($id) || !isset($db['clients'][$id]) || !is_string($resource) || $resource!==mcpResource()) fail(400,'invalid_target');
            if (($_POST['grant_type']??'')==='authorization_code') {
                $hash=hash('sha256',(string)($_POST['code']??''));$code=$db['codes'][$hash]??null;
                $verifier=$_POST['code_verifier']??'';
                if (!$code || $code['clientId']!==$id || $code['resource']!==$resource || $code['redirectUri']!==($_POST['redirect_uri']??'') || !hash_equals($code['credential'],credentialStamp()) || !is_string($verifier) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier)) fail(400,'invalid_grant');
                $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
                if (!hash_equals($code['challenge'],$challenge)) fail(400,'invalid_grant');
                unset($db['codes'][$hash]);return issueGrant($db,$id,$code['scope'],$resource);
            }
            if (($_POST['grant_type']??'')==='refresh_token') {
                $hash=hash('sha256',(string)($_POST['refresh_token']??''));
                foreach ($db['grants'] as $key=>$g) if ($g['clientId']===$id && $g['resource']===$resource && hash_equals($g['refreshHash'],$hash) && hash_equals($g['credential'],credentialStamp())) return issueGrant($db,$id,$g['scope'],$resource,$key);
                fail(400,'invalid_grant');
            }
            fail(400,'unsupported_grant_type');
        });reply(200,$tokens);
    } catch (AtlasError $e) { reply($e->status,['error'=>$e->getMessage()]); }
}
if ($action==='revoke' && $method==='POST') {
    $hash=hash('sha256',(string)($_POST['token']??''));
    oauthStore(function(&$db)use($hash){foreach($db['grants'] as $id=>$g) if(hash_equals($g['accessHash'],$hash)||hash_equals($g['refreshHash'],$hash)) unset($db['grants'][$id]);});
    reply(200,[]);
}
if ($action!=='authorize' || !in_array($method,['GET','POST'],true)) fail(405,'Method not supported');
// All authorization parameters stay in the URL; POST can only approve or deny it.
$p=$_GET;
foreach (['client_id','redirect_uri','response_type','code_challenge','code_challenge_method','resource','state','scope'] as $key) if (isset($p[$key]) && (!is_string($p[$key]) || strlen($p[$key])>2000)) fail(400,'Invalid authorization request');
$client=oauthStore(fn(&$db)=>$db['clients'][$p['client_id']??'']??null);
if (!$client || !in_array($p['redirect_uri']??'', $client['redirect_uris'],true)) fail(400,'Unknown client or redirect URI');
if (($p['response_type']??'')!=='code' || ($p['code_challenge_method']??'')!=='S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$p['code_challenge']??'') || ($p['resource']??'')!==mcpResource()) fail(400,'Authorization code + PKCE S256 and exact resource required');
$scopes=array_values(array_unique(explode(' ',trim($p['scope']??'atlas:read atlas:write'))));
if (!$scopes || array_diff($scopes,['atlas:read','atlas:write'])) fail(400,'Invalid scope');
$scope=implode(' ',$scopes);$editor=atlasSession();
if ($method==='POST') {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) fail(403,'Обновите страницу подключения');
    if (!$editor) fail(401,'Сначала войдите как владелец');
    $out=['state'=>$p['state']??'','iss'=>atlasOrigin()];
    if (($_POST['decision']??'')==='allow') {
        $out['code']=oauthStore(function(&$db)use($p,$scope){
            if (count($db['codes'])>=200) fail(429,'Too many pending authorizations');
            $code=bin2hex(random_bytes(32));$db['codes'][hash('sha256',$code)]=['clientId'=>$p['client_id'],'redirectUri'=>$p['redirect_uri'],'challenge'=>$p['code_challenge'],'scope'=>$scope,'resource'=>$p['resource'],'credential'=>credentialStamp(),'expires'=>time()+300];return $code;
        });
    } else $out['error']='access_denied';
    header('Location: '.$p['redirect_uri'].(str_contains($p['redirect_uri'],'?')?'&':'?').http_build_query($out),true,303);exit;
}
$nonce=bin2hex(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'nonce-$nonce'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header('Content-Type: text/html; charset=utf-8');
function h(string $s): string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Подключение ИИ · Windows11X</title><link rel="stylesheet" href="../styles.css?v=4"><body class="oauth-page"><main class="oauth-card"><p class="eyebrow">Windows11X · доступ ИИ</p><h1>Подключить <?=h($client['client_name'])?>?</h1><p>Адрес возврата: <code><?=h($p['redirect_uri'])?></code></p><p><?=in_array('atlas:write',$scopes,true)?'Разрешение: читать проект, добавлять исследования и комментарии, изменять задачи и отметки.':'Разрешение: только читать проект.'?> Доступ можно отозвать на сайте кнопкой «Для ИИ».</p>
<?php if(!$editor): ?><form id="oauthLogin"><label>Логин<input name="username" autocomplete="username" required value="admin"></label><label>Пароль владельца<input name="password" type="password" autocomplete="current-password" required></label><button class="button button-dark">Войти</button><p id="error" role="alert"></p></form><script nonce="<?=$nonce?>">document.getElementById('oauthLogin').addEventListener('submit',async e=>{e.preventDefault();const form=e.target,button=form.querySelector('button');button.disabled=true;try{const r=await fetch('index.php?action=login',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode($_SESSION['csrf'])?>},body:JSON.stringify(Object.fromEntries(new FormData(form)))});const d=await r.json();if(!r.ok)throw Error(d.error);location.reload();}catch(err){document.getElementById('error').textContent=err.message;button.disabled=false;}});</script>
<?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="button button-dark" name="decision" value="allow">Разрешить подключение</button><button class="button button-light" name="decision" value="deny">Отмена</button></form><?php endif; ?><p>Пароль остаётся на этом сайте. Клиент получает отдельный ограниченный по времени доступ.</p></main></body></html>
