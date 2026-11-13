<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/atlas.php';
try {
    $action = $_GET['action']??'data';
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method==='GET' && $action==='data') reply(200,atlasData());
    if ($method==='GET' && $action==='version') { $data=atlasData();reply(200,['revision'=>$data['revision'],'updatedAt'=>$data['updatedAt']??null]); }
    $editor=atlasSession();
    if ($method==='GET' && $action==='session') reply(200,['editor'=>$editor,'configured'=>$configured,'csrf'=>$_SESSION['csrf']]);
    if ($method!=='POST') fail(405,'Метод не поддерживается');
    if (!hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) fail(403,'Обновите страницу и повторите действие');
    if (in_array($action,['ai-connections','ai-revoke'],true)) { require __DIR__.'/oauth-lib.php'; manageConnections($action,$editor); }
    if (!in_array($action,['login','logout','save'],true)) fail(404,'Действие не найдено');
    if ($action==='save' && !$editor) fail(401,'Для изменения нужен вход владельца');
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>12582912) fail(413,'Данные больше 12 МБ');
    $raw=file_get_contents('php://input',false,null,0,12582913);
    if ($raw===false || strlen($raw)>12582912) fail(413,'Данные больше 12 МБ');
    try { $input=json_decode($raw,true,128,JSON_THROW_ON_ERROR); } catch (JsonException $e) { fail(400,'Неверный JSON'); }
    if (!is_array($input)) fail(400,'Неверные данные');
    if ($action==='login') {
        if (!$configured) fail(503,'Владелец ещё не настроил пароль на сервере');
        // Shared, locked counters cannot be bypassed by changing session cookies.
        $lock=fopen($privateDir.'/login.lock','c'); if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Lock failed');
        $attempts=readJson($privateDir.'/attempts.json',[]); $now=time();
        $attempts=array_filter($attempts,fn($x)=>($x['since']??0)>$now-900);
        $key=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??''));
        $entry=$attempts[$key]??['since'=>$now,'count'=>0];
        if ($entry['count']>=10) fail(429,'Слишком много попыток. Повторите через 15 минут');
        $ok=is_string($input['username']??null) && is_string($input['password']??null) && strlen($input['password'])<=1024 && hash_equals($username,$input['username']) && password_verify($input['password'],$passwordHash);
        if (!$ok) { $entry['count']++; $attempts[$key]=$entry; atomicWrite($privateDir.'/attempts.json',$attempts); fail(401,'Неверный логин или пароль'); }
        unset($attempts[$key]); atomicWrite($privateDir.'/attempts.json',$attempts); flock($lock,LOCK_UN); fclose($lock);
        session_regenerate_id(true); $_SESSION['editor']=true; $_SESSION['expires']=$now+28800; $_SESSION['credential']=hash('sha256',$username.$passwordHash); $_SESSION['csrf']=bin2hex(random_bytes(32));
        reply(200,['editor'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    if ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); reply(200,['editor'=>false,'csrf'=>$_SESSION['csrf']]); }
    $result=atlasMutate($input['revision']??null, fn($previous)=>['content'=>$input['content']??null,'state'=>$input['state']??null], ['actor'=>'owner','action'=>'site_save']);
    reply(200,['revision'=>$result['revision'],'updatedAt'=>$result['updatedAt']]);
} catch (AtlasError $e) { reply($e->status,['error'=>$e->getMessage()]); } catch (Throwable $e) { error_log('Windows11X API: '.$e->getMessage()); fail(503,'Серверное сохранение недоступно. Проверьте настройку PHP и закрытой папки данных'); }
