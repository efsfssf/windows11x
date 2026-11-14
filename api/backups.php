<?php
declare(strict_types=1);
const ATLAS_BACKUP_SECONDS=7*24*60*60;
function backupDirectory(): string {
    global $privateDir;
    $dir=$privateDir.'/backups';
    if (is_link($dir)) throw new RuntimeException('Backup directory must not be a symbolic link');
    if (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Cannot create backups');
    return $dir;
}
function backupTimestamp(string $id): int {
    if (!preg_match('/^b-([0-9]{10})-[0-9]+-[a-f0-9]{16}$/D',$id,$m)) fail(422,'Неверный ID резервной копии');
    return (int)$m[1];
}
function pruneBackups(): void {
    $dir=backupDirectory();
    foreach (glob($dir.'/b-*')?:[] as $file) {
        if (!preg_match('/^(b-([0-9]{10})-[0-9]+-[a-f0-9]{16})\.(meta\.json|json\.gz|json)$/D',basename($file),$m)) continue;
        if ((int)$m[2]+ATLAS_BACKUP_SECONDS<=time() && !is_link($file) && is_file($file) && !unlink($file)) error_log('Windows11X: cannot prune expired backup');
    }
}
function createAtlasBackup(array $snapshot, array $audit): array {
    $dir=backupDirectory();$now=time();
    $id='b-'.$now.'-'.$snapshot['revision'].'-'.bin2hex(random_bytes(8));
    $raw=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $compressed=function_exists('gzencode');$bytes=$compressed?gzencode($raw,6):$raw;
    if ($bytes===false) throw new RuntimeException('Cannot compress backup');
    $extension=$compressed?'.json.gz':'.json';$path=$dir.'/'.$id.$extension;
    $temp=tempnam($dir,'.backup-');if($temp===false) throw new RuntimeException('Cannot create backup file');
    try {
        if(file_put_contents($temp,$bytes)!==strlen($bytes)) throw new RuntimeException('Incomplete backup write');
        if(!chmod($temp,0600) || !rename($temp,$path)) throw new RuntimeException('Cannot commit backup');
    } finally { if(is_file($temp)) unlink($temp); }
    $metadata=['id'=>$id,'createdAt'=>gmdate('c',$now),'expiresAt'=>gmdate('c',$now+ATLAS_BACKUP_SECONDS),'revision'=>$snapshot['revision'],'beforeAction'=>$audit,'sha256'=>hash('sha256',$raw),'extension'=>$extension,'bytes'=>strlen($bytes)];
    atomicWrite($dir.'/'.$id.'.meta.json',$metadata);
    return $metadata;
}
function listAtlasBackups(int $offset=0,int $limit=50): array {
    pruneBackups();$dir=backupDirectory();$rows=[];
    foreach(glob($dir.'/b-*.meta.json')?:[] as $path) {
        if(is_link($path)) continue;
        try {
        $m=readJson($path,[]);
        if(!$m || backupTimestamp($m['id'])+ATLAS_BACKUP_SECONDS<=time()) continue;
        if(!in_array($m['extension']??'', ['.json','.json.gz'],true) || !is_file($dir.'/'.$m['id'].$m['extension'])) continue;
        $rows[]=$m;
        } catch(Throwable $e) { error_log('Windows11X: unreadable backup metadata '.basename($path)); }
    }
    usort($rows,fn($a,$b)=>($b['revision']<=>$a['revision'])?:strcmp($b['id'],$a['id']));
    return ['backups'=>array_slice($rows,$offset,$limit),'total'=>count($rows),'nextOffset'=>$offset+$limit<count($rows)?$offset+$limit:null,'retentionDays'=>7];
}
function readAtlasBackup(string $id): array {
    $created=backupTimestamp($id);
    if($created+ATLAS_BACKUP_SECONDS<=time()) fail(410,'Срок хранения копии истёк (7 дней)');
    $dir=backupDirectory();$metaPath=$dir.'/'.$id.'.meta.json';
    if(is_link($metaPath) || !is_file($metaPath)) fail(404,'Резервная копия не найдена');
    $meta=readJson($metaPath,[]);
    if(($meta['id']??'')!==$id || !in_array($meta['extension']??'', ['.json','.json.gz'],true)) throw new RuntimeException('Invalid backup metadata');
    $path=$dir.'/'.$id.$meta['extension'];
    if(is_link($path) || !is_file($path)) fail(404,'Файл копии не найден');
    $raw=file_get_contents($path);
    if($raw===false) throw new RuntimeException('Cannot read backup');
    if($meta['extension']==='.json.gz') $raw=@gzdecode($raw,50000000);
    if($raw===false || !hash_equals($meta['sha256'],hash('sha256',$raw))) fail(503,'Проверка целостности копии не пройдена');
    $data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
    if(!is_array($data) || ($data['revision']??null)!==$meta['revision']) throw new RuntimeException('Invalid backup snapshot');
    validatePayload($data);
    return ['metadata'=>$meta,'snapshot'=>$data];
}
function restoreAtlasBackup(mixed $revision,string $id,array $audit): array {
    return atlasMutate($revision,function($current)use($id){
        $backup=readAtlasBackup($id);
        return ['content'=>$backup['snapshot']['content'],'state'=>$backup['snapshot']['state']];
    },[...$audit,'action'=>'restore_backup','target'=>$id]);
}
