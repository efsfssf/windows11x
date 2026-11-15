<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/atlas.php';
try {
    $action=$_GET['action']??'list';$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET' && $action==='list') { $data=atlasData();reply(200,['revision'=>$data['revision'],'files'=>artifactRows($data),'storage'=>artifactUsage()]); }
    if($method==='GET' && $action==='download') {
        $data=atlasData();
        if(isset($_GET['backupId'])) {if(!atlasSession())fail(401,'Нужен вход владельца');if(!is_string($_GET['backupId']))fail(422,'Неверная копия');$data=readAtlasBackup($_GET['backupId'])['snapshot'];}
        if(!is_string($_GET['id']??null))fail(422,'Нужен ID файла');
        $row=artifactFind($data,$_GET['id']);$path=artifactBlob($row['sha256']);
        if(!is_file($path) || !hash_equals($row['sha256'],hash_file('sha256',$path))) fail(503,'Файл не найден или проверка SHA-256 не пройдена');
        header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="artifact.bin"; filename*=UTF-8\'\''.rawurlencode($row['filename']));
        header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Length: '.filesize($path));header('X-Artifact-SHA256: '.$row['sha256']);
        readfile($path);exit;
    }
    if($method!=='POST')fail(405,'Метод не поддерживается');
    if(!atlasSession())fail(401,'Нужен вход владельца');
    if(!hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))fail(403,'Обновите страницу и повторите действие');
    // Serialize staging operations; publication additionally uses the shared revision/data lock.
    $lock=fopen(artifactUploadDirectory().'/uploads.lock','c');if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Upload lock failed');
    try {
        if($action==='chunk') {
            if(!is_string($_GET['uploadId']??null) || !is_string($_GET['offset']??null) || !preg_match('/^[0-9]{1,12}$/D',$_GET['offset'])) fail(422,'Нужны ID и смещение загрузки');
            $raw=file_get_contents('php://input',false,null,0,ARTIFACT_CHUNK_LIMIT+1);if($raw===false)throw new RuntimeException('Cannot read chunk');
            reply(200,artifactUploadChunk($_GET['uploadId'],(int)$_GET['offset'],$raw));
        }
        $raw=file_get_contents('php://input',false,null,0,100001);if($raw===false || strlen($raw)>100000)fail(413,'Слишком большое описание');
        try{$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){fail(400,'Неверный JSON');}
        if(!is_array($input))fail(400,'Неверные данные');
        if($action==='clear-pending') {
            foreach(glob(artifactUploadDirectory().'/*.part')?:[] as $part) {if(!is_link($part)){unlink($part);$meta=substr($part,0,-5).'.json';if(is_file($meta))unlink($meta);}}
            reply(200,['cleared'=>true]);
        }
        if($action==='begin')reply(200,artifactUploadBegin($input));
        if($action==='finish')reply(200,artifactUploadFinish($input));
        if($action==='cancel') {
            if(!is_string($input['uploadId']??null))fail(422,'Нужен ID');artifactUploadInfo($input['uploadId']);$base=artifactUploadPath($input['uploadId']);
            if(is_file($base.'.part'))unlink($base.'.part');unlink($base.'.json');reply(200,['cancelled'=>true]);
        }
        if(!in_array($action,['update','delete','restore-file'],true))fail(404,'Действие не найдено');
        if(!is_string($input['id']??null))fail(422,'Нужен ID файла');
        $data=atlasMutate($input['revision']??null,function($data)use($input,$action){
            if($action==='restore-file') {
                if(!is_string($input['backupId']??null))fail(422,'Нужен ID копии');
                $row=artifactFind(readAtlasBackup($input['backupId'])['snapshot'],$input['id']);$row['updatedAt']=gmdate('c');
                $rows=artifactRows($data);$found=false;
                foreach($rows as &$r)if($r['id']===$row['id']){$r=$row;$found=true;}unset($r);
                if(!$found)$rows[]=$row;$data['content']['artifacts']=$rows;return $data;
            }
            $row=artifactFind($data,$input['id']);
            if($action==='delete')$data['content']['artifacts']=array_values(array_filter(artifactRows($data),fn($r)=>$r['id']!==$input['id']));
            else foreach($data['content']['artifacts'] as &$r)if($r['id']===$input['id'])$r=[...$row,...artifactFields($input,$data,$row['taskIds']),'updatedAt'=>gmdate('c')];
            return $data;
        },['actor'=>'owner','action'=>$action.'_artifact','target'=>$input['id']]);
        reply(200,['revision'=>$data['revision'],'backupId'=>$data['backupId'],'backupExpiresAt'=>$data['backupExpiresAt']]);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}catch(AtlasError $e){reply($e->status,['error'=>$e->getMessage()]);}
