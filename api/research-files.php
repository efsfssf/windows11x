<?php
declare(strict_types=1);

function researchFolderPath(string $folder): array {
    global $webRoot;
    $folder=rtrim(trim($folder),'/');
    $parts=explode('/',$folder);
    if($folder==='' || strlen($folder)>2000 || $parts[0]!=='library' || str_contains($folder,'\\') || str_contains($folder,"\0")) fail(422,'Папка должна находиться внутри library');
    foreach($parts as $part) if($part==='' || $part==='.' || $part==='..' || str_starts_with($part,'.')) fail(422,'Недопустимый путь папки');
    $root=realpath($webRoot.'/library');$path=$webRoot;
    foreach($parts as $part) {$path.='/'.$part;if(is_link($path)) fail(422,'Символические ссылки не поддерживаются');}
    $path=realpath($path);
    if(!$root || !$path || !is_dir($path) || ($path!==$root && !str_starts_with($path,$root.DIRECTORY_SEPARATOR))) fail(404,'Папка исследований не найдена');
    return [$folder,$path,$root];
}
function decodeResearchText(string $raw): ?string {
    if(str_starts_with($raw,"\xEF\xBB\xBF")) $raw=substr($raw,3);
    $encoding=null;
    if(str_starts_with($raw,"\xFF\xFE")) {$encoding='UTF-16LE';$raw=substr($raw,2);}
    elseif(str_starts_with($raw,"\xFE\xFF")) {$encoding='UTF-16BE';$raw=substr($raw,2);}
    elseif(preg_match('//u',$raw)!==1) $encoding='Windows-1251';
    if($encoding!==null) {
        if(function_exists('mb_convert_encoding')) $raw=mb_convert_encoding($raw,'UTF-8',$encoding);
        elseif(function_exists('iconv')) {$converted=@iconv($encoding,'UTF-8',$raw);if($converted===false)return null;$raw=$converted;}
        else return null;
    }
    return str_contains($raw,"\0") || preg_match('//u',$raw)!==1?null:$raw;
}
function scanResearchFolder(string $folder): array {
    global $webRoot;
    [$folder,$directory,$root]=researchFolderPath($folder);
    $formats=['md'=>'md','markdown'=>'md','json'=>'json','csv'=>'csv','txt'=>'txt','ps1'=>'ps1','py'=>'py'];
    $iterator=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),fn($file)=>!$file->isLink() && !str_starts_with($file->getFilename(),'.')));
    $records=[];$warnings=[];$total=0;$seen=0;
    foreach($iterator as $file) {
        if(!$file->isFile() || $file->isLink()) continue;
        $format=$formats[strtolower($file->getExtension())]??null;if(!$format)continue;
        if(++$seen>2000) fail(413,'В папке больше 2000 поддерживаемых файлов. Выберите подпапку');
        $path=$file->getRealPath();if(!$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR)) continue;
        $relative=substr($path,strlen($webRoot)+1);
        if(preg_match('//u',$relative)!==1 || strlen($relative)>2000) {$warnings[]='Пропущен файл с неподдерживаемым именем или слишком длинным путём';continue;}
        if($file->getSize()>2000000) {$warnings[]=$relative.': больше 2 МБ';continue;}
        $raw=file_get_contents($path,false,null,0,2000001);
        if($raw===false || strlen($raw)>2000000) {$warnings[]=$relative.': не удалось прочитать файл или размер больше 2 МБ';continue;}
        $text=decodeResearchText($raw);
        if($text===null) {$warnings[]=$relative.': сохраните текст в UTF-8 (или установите mbstring/iconv для старых кодировок)';continue;}
        if(strlen($text)>2000000) {$warnings[]=$relative.': текст после преобразования больше 2 МБ';continue;}
        $total+=strlen($text);if($total>8000000) fail(413,'Общий текст папки больше 8 МБ. Выберите подпапку');
        $title=pathinfo($relative,PATHINFO_FILENAME);
        if($format==='md' && preg_match('/^#\s+(.+)$/m',$text,$m)) $title=trim($m[1]);
        $date=gmdate('Y-m-d',$file->getMTime());$dateSource='file_mtime';
        if(preg_match('/(20[0-9]{2})[-_]([0-9]{2})[-_]([0-9]{2})/',$file->getFilename(),$m) && validDate($m[1].'-'.$m[2].'-'.$m[3])) {$date=$m[1].'-'.$m[2].'-'.$m[3];$dateSource='filename';}
        $records[]=['id'=>'server-'.hash('sha256',$relative),'path'=>$relative,'format'=>$format,'title'=>$title,'summary'=>'Загружено из файла '.$relative,'date'=>$date,'dateSource'=>$dateSource,'topic'=>'Общее','group'=>'Файлы сервера · '.dirname($relative),'content'=>$text];
    }
    usort($records,fn($a,$b)=>strcmp($a['path'],$b['path']));
    return ['folder'=>$folder,'files'=>$records,'count'=>count($records),'warnings'=>$warnings,'formats'=>array_keys($formats),'recursive'=>true];
}
function mergeResearchFiles(array $existing,array $files): array {
    $byPath=[];$ids=[];foreach($existing as $i=>$row){if(isset($row['path']))$byPath[$row['path']]=$i;$ids[$row['id']]=true;}
    $added=0;$updated=0;
    foreach($files as $file) {
        if(isset($byPath[$file['path']])) {
            $i=$byPath[$file['path']];
            if($existing[$i]['content']!==$file['content'] || $existing[$i]['format']!==$file['format'])$updated++;
            $existing[$i]=[...$file,...$existing[$i],'content'=>$file['content'],'format'=>$file['format'],'path'=>$file['path']];
        } else {
            while(isset($ids[$file['id']])) $file['id'].='-x';
            if(strlen($file['id'])>100) fail(422,'Конфликт ID импортируемого файла');
            $ids[$file['id']]=true;$byPath[$file['path']]=count($existing);$existing[]=$file;$added++;
        }
    }
    return ['research'=>$existing,'added'=>$added,'updated'=>$updated];
}
