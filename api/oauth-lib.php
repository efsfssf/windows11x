<?php
declare(strict_types=1);
function atlasOrigin(): string {
    global $config;
    $url=rtrim(getenv('ATLAS_BASE_URL') ?: ($config['base_url']??'https://dandomi.tech'),'/');
    if (!preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]+)?$~D',$url) && !preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::[0-9]+)?$~D',$url)) throw new RuntimeException('Invalid ATLAS_BASE_URL');
    return $url;
}
function mcpResource(): string { return atlasOrigin().'/api/mcp.php'; }
function credentialStamp(): string { global $username,$passwordHash;return hash('sha256',$username.$passwordHash); }
function oauthStore(callable $fn): mixed {
    global $privateDir;
    $lock=fopen($privateDir.'/oauth.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('OAuth lock failed');
    try {
        $db=readJson($privateDir.'/oauth.json',['clients'=>[],'codes'=>[],'grants'=>[]]);
        foreach (['clients','codes','grants'] as $key) $db[$key]=array_filter($db[$key],fn($v)=>($v['expires']??0)>time());
        $result=$fn($db);atomicWrite($privateDir.'/oauth.json',$db);return $result;
    } finally { flock($lock,LOCK_UN);fclose($lock); }
}
function authChallenge(string $scope='atlas:write'): never {
    header('WWW-Authenticate: Bearer resource_metadata="'.atlasOrigin().'/api/oauth.php?action=resource", scope="'.$scope.'"');
    fail(401,'Нужно подключение OAuth владельца сайта');
}
function bearerGrant(): ?array {
    $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    if (!$header) return null;
    if (!preg_match('/^Bearer ([A-Za-z0-9_-]{40,200})$/D',$header,$m)) authChallenge();
    $hash=hash('sha256',$m[1]);
    $grant=oauthStore(function(&$db) use($hash) {
        foreach ($db['grants'] as $id=>$g) if (hash_equals($g['accessHash'],$hash) && $g['accessExpires']>time() && hash_equals($g['credential'],credentialStamp()) && $g['resource']===mcpResource()) return [...$g,'id'=>$id];
        return null;
    });
    if (!$grant) authChallenge();
    return $grant;
}
function manageConnections(string $action, bool $editor): never {
    if (!$editor) fail(401,'Нужен вход владельца');
    if ($action==='ai-connections') {
        $connections=oauthStore(function(&$db){$rows=[];foreach($db['grants'] as $id=>$g) if(hash_equals($g['credential'],credentialStamp())) $rows[]=['id'=>$id,'name'=>$g['name'],'scope'=>$g['scope'],'createdAt'=>$g['createdAt'],'expiresAt'=>gmdate('c',$g['expires'])];return $rows;});
        reply(200,['connections'=>$connections,'mcpUrl'=>mcpResource()]);
    }
    $input=json_decode((string)file_get_contents('php://input'),true);
    if (!is_string($input['id']??null)) fail(422,'Нужен ID подключения');
    oauthStore(function(&$db)use($input){unset($db['grants'][$input['id']]);});
    reply(200,['revoked'=>true]);
}
function resourceMetadata(): array {
    return ['resource'=>mcpResource(),'authorization_servers'=>[atlasOrigin()],'scopes_supported'=>['atlas:read','atlas:write'],'bearer_methods_supported'=>['header'],'resource_name'=>'Windows11X research atlas'];
}
function oauthMetadata(): array {
    $base=atlasOrigin().'/api/oauth.php?action=';
    return ['issuer'=>atlasOrigin(),'authorization_endpoint'=>$base.'authorize','token_endpoint'=>$base.'token','registration_endpoint'=>$base.'register','revocation_endpoint'=>$base.'revoke','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'token_endpoint_auth_methods_supported'=>['none'],'code_challenge_methods_supported'=>['S256'],'scopes_supported'=>['atlas:read','atlas:write'],'authorization_response_iss_parameter_supported'=>true];
}
function issueGrant(array &$db, string $clientId, string $scope, string $resource, ?string $oldId=null): array {
    if ($oldId!==null) unset($db['grants'][$oldId]);
    if (count($db['grants'])>=100) fail(429,'Слишком много подключений; отзовите старые на сайте');
    $id=bin2hex(random_bytes(12));$access=bin2hex(random_bytes(32));$refresh=bin2hex(random_bytes(32));
    $db['grants'][$id]=['clientId'=>$clientId,'name'=>$db['clients'][$clientId]['client_name'],'scope'=>$scope,'resource'=>$resource,'accessHash'=>hash('sha256',$access),'refreshHash'=>hash('sha256',$refresh),'accessExpires'=>time()+3600,'expires'=>time()+2592000,'createdAt'=>gmdate('c'),'credential'=>credentialStamp()];
    return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'refresh_token'=>$refresh,'scope'=>$scope];
}
