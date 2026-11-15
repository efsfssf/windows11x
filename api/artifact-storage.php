<?php
declare(strict_types=1);
const ARTIFACT_FILE_LIMIT=104857600; // 100 MiB, streamed in 512 KiB chunks.
const ARTIFACT_STORAGE_LIMIT=1073741824;
const ARTIFACT_CHUNK_LIMIT=524288;
function artifactDirectory(): string {
    global $privateDir;
    $dir=$privateDir.'/artifacts';
    if(is_link($dir)) throw new RuntimeException('Invalid artifact directory');
    if(!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Cannot create artifact storage');
    return $dir;
}
function artifactBlob(string $sha): string {
    if(!preg_match('/^[a-f0-9]{64}$/D',$sha)) fail(422,'Неверный SHA-256 файла');
    $path=artifactDirectory().'/'.$sha.'.blob';
    if(is_link($path)) throw new RuntimeException('Invalid artifact blob');
    return $path;
}
function artifactRows(array $data): array { return $data['content']['artifacts']??[]; }
function artifactFind(array $data,string $id): array {
    foreach(artifactRows($data) as $row) if($row['id']===$id) return $row;
    fail(404,'Файл не найден');
}
function artifactUsage(): array {
    $used=0;foreach(glob(artifactDirectory().'/*.blob')?:[] as $p) $used+=filesize($p);
    return ['usedBytes'=>$used,'limitBytes'=>ARTIFACT_STORAGE_LIMIT,'fileLimitBytes'=>ARTIFACT_FILE_LIMIT,'chunkBytes'=>ARTIFACT_CHUNK_LIMIT];
}
// Called only while the shared data.lock is held. Deleted bytes survive every unexpired snapshot.
function artifactPrune(array $current): void {
    $active=array_column(artifactRows($current),'sha256');
    foreach(glob(artifactDirectory().'/*.blob')?:[] as $p) {
        $sha=basename($p,'.blob');
        if(!is_link($p) && !in_array($sha,$active,true) && filemtime($p)+7*86400<=time()) unlink($p);
    }
}
function artifactRetain(array $previous,array $next): void {
    foreach(array_unique([...array_column(artifactRows($previous),'sha256'),...array_column(artifactRows($next),'sha256')]) as $sha) {
        if(!touch(artifactBlob($sha))) throw new RuntimeException('Cannot retain artifact for backup');
    }
}
function validateArtifact(array $row): void {
    foreach(['id','familyId','filename','title','description','version','status','issues','testedOn','verification','sha256','createdAt','updatedAt'] as $key) textField($row,$key,in_array($key,['description','issues','verification'])?20000:2000);
    foreach(['id','familyId'] as $key) if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$row[$key])) fail(422,'Неверный ID файла');
    if(trim($row['title'])==='' || strlen($row['filename'])>240 || preg_match('~[\\\\/\x00-\x1f\x7f]~',$row['filename']) || trim($row['filename'])==='') fail(422,'Неверное название файла');
    if(!in_array($row['status'],['untested','offline_pass','runtime_pass','failed','archived'],true)) fail(422,'Неверный статус файла');
    if(in_array($row['status'],['offline_pass','runtime_pass'],true) && (trim($row['verification'])==='' || trim($row['testedOn'])==='')) fail(422,'Для успешной проверки укажите среду и конкретный результат проверки');
    if(!is_int($row['size']??null) || $row['size']<1 || $row['size']>ARTIFACT_FILE_LIMIT) fail(422,'Неверный размер файла');
    $path=artifactBlob($row['sha256']);
    if(!is_file($path) || filesize($path)!==$row['size']) fail(422,'Бинарный файл отсутствует в хранилище');
    foreach(collection($row,'taskIds',200) as $id) if(!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$id)) fail(422,'Неверная привязка задачи');
}
function artifactFields(array $input,array $data,array $historicalTaskIds=[]): array {
    $out=[];foreach(['title','description','version','status','issues','testedOn','verification','taskIds'] as $key) {if(!array_key_exists($key,$input)) fail(422,'Нужно поле '.$key);$out[$key]=$input[$key];}
    foreach(collection($out,'taskIds',200) as $id) {if(!is_string($id)) fail(422,'Неверная задача');if(!in_array($id,$historicalTaskIds,true))atlasTask($data,$id);}
    $out['taskIds']=array_values(array_unique($out['taskIds']));
    return $out;
}
function artifactUploadDirectory(): string {
    global $privateDir;$dir=$privateDir.'/artifact-uploads';
    if(is_link($dir)) throw new RuntimeException('Invalid upload directory');
    if(!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Cannot create upload directory');
    return $dir;
}
function artifactUploadPath(string $id): string {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)) fail(422,'Неверный ID загрузки');
    return artifactUploadDirectory().'/'.$id;
}
function artifactUploadStamp(): string {global $username,$passwordHash;return hash('sha256',$username.$passwordHash);}
function artifactUploadInfo(string $id): array {
    $base=artifactUploadPath($id);$meta=readJson($base.'.json',[]);
    if(!$meta || $meta['created']+86400<=time() || !hash_equals($meta['credential'],artifactUploadStamp())) fail(410,'Загрузка истекла. Выберите файл заново');
    return $meta;
}
function artifactUploadBegin(array $input): array {
    textField($input,'filename',240);$name=$input['filename'];
    if(trim($name)==='' || preg_match('~[\\\\/\x00-\x1f\x7f]~',$name)) fail(422,'Неверное имя файла');
    if(!is_int($input['size']??null) || $input['size']<1 || $input['size']>ARTIFACT_FILE_LIMIT) fail(413,'Размер файла должен быть от 1 байта до 100 МиБ');
    foreach(glob(artifactUploadDirectory().'/*.json')?:[] as $p) { $m=readJson($p,[]);if(($m['created']??0)+86400<=time()) {unlink($p);if(is_file(substr($p,0,-5).'.part'))unlink(substr($p,0,-5).'.part');} }
    if(count(glob(artifactUploadDirectory().'/*.part')?:[])>=3) fail(429,'Есть три незавершённые загрузки. Отмените их или повторите через сутки');
    $id=bin2hex(random_bytes(16));$base=artifactUploadPath($id);
    if(file_put_contents($base.'.part','')!==0 || !chmod($base.'.part',0600)) throw new RuntimeException('Cannot start upload');
    atomicWrite($base.'.json',['filename'=>$name,'size'=>$input['size'],'created'=>time(),'credential'=>artifactUploadStamp(),'artifactId'=>'file-'.bin2hex(random_bytes(12))]);
    return ['uploadId'=>$id,'chunkBytes'=>ARTIFACT_CHUNK_LIMIT];
}
function artifactUploadChunk(string $id,int $offset,string $bytes): array {
    $meta=artifactUploadInfo($id);$path=artifactUploadPath($id).'.part';
    if(!is_file($path)) fail(409,'Эта загрузка уже завершена');
    $size=filesize($path);$length=strlen($bytes);
    if(!$length || $length>ARTIFACT_CHUNK_LIMIT || $offset<0 || $offset+$length>$meta['size']) fail(413,'Неверный размер части файла');
    if($offset<$size) {if(file_get_contents($path,false,null,$offset,$length)===$bytes)return ['received'=>$size];fail(409,'Повторная часть отличается от полученной');}
    if($offset!==$size) fail(409,'Нарушен порядок загрузки. Ожидается смещение '.$size);
    if(file_put_contents($path,$bytes,FILE_APPEND)!==$length) throw new RuntimeException('Incomplete upload write');
    return ['received'=>$size+$length];
}
function artifactUploadFinish(array $input): array {
    $id=$input['uploadId']??'';if(!is_string($id))fail(422,'Нужен ID загрузки');
    $meta=artifactUploadInfo($id);$current=atlasData();
    foreach(artifactRows($current) as $row) if($row['id']===$meta['artifactId']) return ['revision'=>$current['revision'],'artifact'=>$row,'alreadySaved'=>true];
    $part=artifactUploadPath($id).'.part';
    if(!is_file($part) || filesize($part)!==$meta['size']) fail(422,'Файл загружен не полностью');
    $sha=hash_file('sha256',$part);if($sha===false)throw new RuntimeException('Cannot hash upload');
    $blob=artifactBlob($sha);$artifact=[];
    $saved=atlasMutate($input['revision']??null,function($data)use($input,$meta,$sha,$part,$blob,&$artifact){
        $fields=artifactFields($input,$data);$familyId=$meta['artifactId'];
        if(!empty($input['baseArtifactId'])) {if(!is_string($input['baseArtifactId']))fail(422,'Неверная версия');$familyId=artifactFind($data,$input['baseArtifactId'])['familyId'];}
        if(!is_file($blob)) {
            if(artifactUsage()['usedBytes']+$meta['size']>ARTIFACT_STORAGE_LIMIT) fail(507,'Хранилище заполнено (1 ГиБ с учётом резервных версий)');
            $temp=tempnam(artifactDirectory(),'.blob-');if($temp===false)throw new RuntimeException('Cannot create blob');
            try {if(!copy($part,$temp) || !chmod($temp,0600) || !rename($temp,$blob))throw new RuntimeException('Cannot store blob');}finally{if(is_file($temp))unlink($temp);}
        }
        $artifact=[...$fields,'id'=>$meta['artifactId'],'familyId'=>$familyId,'filename'=>$meta['filename'],'size'=>$meta['size'],'sha256'=>$sha,'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c')];
        validateArtifact($artifact);$data['content']['artifacts'][]=$artifact;return $data;
    },['actor'=>'owner','action'=>'upload_artifact','target'=>$meta['artifactId']]);
    unlink($part);
    return ['revision'=>$saved['revision'],'artifact'=>$artifact,'backupId'=>$saved['backupId'],'backupExpiresAt'=>$saved['backupExpiresAt']];
}
