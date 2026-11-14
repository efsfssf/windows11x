<?php
declare(strict_types=1);

function siteSection(array $data,string $section): mixed {
    if($section==='state') return $data['state'];
    if($section==='metadata') return ['snapshot'=>$data['content']['snapshot'],'artifactHash'=>$data['content']['artifactHash']];
    if(!array_key_exists($section,$data['content'])) fail(404,'Раздел не найден');
    return $data['content'][$section];
}
function textPage(string $text,int $offset=0,int $limit=20000): array {
    $total=strlen((string)preg_replace('/[\x80-\xBF]/','',$text));
    if($offset>$total) fail(422,'offset за пределами текста');
    $from=0;$to=strlen($text);$position=0;
    for($byte=0,$bytes=strlen($text);$byte<$bytes;$byte++) {
        if((ord($text[$byte])&0xC0)===0x80) continue;
        if($position===$offset) $from=$byte;
        if($position===$offset+$limit) {$to=$byte;break;}
        $position++;
    }
    if($offset===$total) $from=strlen($text);
    return ['text'=>substr($text,$from,$to-$from),'offset'=>$offset,'totalCharacters'=>$total,'nextOffset'=>$offset+$limit<$total?$offset+$limit:null,'complete'=>$offset===0 && $offset+$limit>=$total];
}
function pointerParts(string $pointer): array {
    if(!str_starts_with($pointer,'/') || strlen($pointer)>2000) fail(422,'Нужен JSON Pointer от /content или /state');
    $parts=explode('/',substr($pointer,1));
    if(count($parts)>20) fail(422,'Слишком глубокий путь');
    foreach($parts as &$part) {
        if(preg_match('/~(?![01])/',$part)) fail(422,'Неверное экранирование JSON Pointer');
        $part=str_replace(['~1','~0'],['/','~'],$part);
        if(in_array($part,['__proto__','constructor','prototype'],true)) fail(422,'Недопустимый путь');
    }unset($part);
    if(!in_array($parts[0],['content','state'],true)) fail(403,'Изменять можно только /content и /state');
    return $parts;
}
function pointerRead(array $data,array $parts): mixed {
    $value=$data;
    foreach($parts as $key) {if(!is_array($value) || !array_key_exists($key,$value)) fail(404,'Путь не найден');$value=$value[$key];}
    return $value;
}
function &pointerParent(array &$data,array $parts): array {
    array_pop($parts);$parent=&$data;
    foreach($parts as $key) {
        if(!is_array($parent) || !array_key_exists($key,$parent) || !is_array($parent[$key])) fail(422,'Родительский объект пути не найден');
        $parent=&$parent[$key];
    }
    return $parent;
}
function pointerWrite(array &$data,array $parts,mixed $value,bool $replace): void {
    $key=end($parts);$parent=&pointerParent($data,$parts);
    if(array_is_list($parent) && ($key==='-' || preg_match('/^[0-9]+$/D',(string)$key))) {
        if($key==='-' && $replace) fail(422,'Для replace нужен индекс');
        if($key!=='-' && (string)(int)$key!==(string)$key) fail(422,'Неверный индекс');
        $index=$key==='-'?count($parent):(int)$key;
        if($index<0 || $index>count($parent) || ($replace && $index===count($parent))) fail(422,'Индекс за пределами списка');
        if($replace) $parent[$index]=$value;else array_splice($parent,$index,0,[$value]);
    } else {
        if(array_is_list($parent) && $parent) fail(422,'Для списка нужен числовой индекс');
        if($replace && !array_key_exists($key,$parent)) fail(404,'Заменяемое поле не найдено');
        $parent[$key]=$value;
    }
}
function pointerRemove(array &$data,array $parts): mixed {
    $key=end($parts);$parent=&pointerParent($data,$parts);
    if(!array_key_exists($key,$parent)) fail(404,'Удаляемое поле не найдено');
    $old=$parent[$key];
    if(array_is_list($parent)) array_splice($parent,(int)$key,1);else unset($parent[$key]);
    return $old;
}
function clearTaskState(array &$data,array $ids): void {
    foreach($ids as $id) foreach(['done','notes','comments'] as $key) unset($data['state'][$key][$id]);
}
function deleteAtlasTask(array &$data,string $id): void {
    atlasTask($data,$id);
    foreach($data['content']['gates'] as &$gate) $gate['tasks']=array_values(array_filter($gate['tasks'],fn($t)=>$t['id']!==$id));unset($gate);
    $data['state']['custom']=array_values(array_filter($data['state']['custom'],fn($t)=>$t['id']!==$id));
    clearTaskState($data,[$id]);
}
function editSiteData(array $data,string $json): array {
    try{$changes=json_decode($json,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'changesJson должен содержать JSON-массив операций');}
    if(!is_array($changes) || !array_is_list($changes) || !$changes || count($changes)>100) fail(422,'Нужно от 1 до 100 операций');
    $before=$data;
    foreach($changes as $change) {
        if(!is_array($change) || !is_string($change['op']??null) || !is_string($change['path']??null)) fail(422,'Неверная операция');
        $parts=pointerParts($change['path']);
        switch($change['op']) {
            case 'add': case 'replace':
                if(!array_key_exists('value',$change)) fail(422,'Нужно значение value');
                pointerWrite($data,$parts,$change['value'],$change['op']==='replace');break;
            case 'remove':pointerRemove($data,$parts);break;
            case 'move':
                if(!is_string($change['from']??null)) fail(422,'Нужен from для move');
                $from=pointerParts($change['from']);
                if($from===$parts) {pointerRead($data,$from);break;}
                if(array_slice($parts,0,count($from))===$from) fail(422,'Нельзя переместить объект внутрь самого себя');
                $value=pointerRemove($data,$from);pointerWrite($data,$parts,$value,false);break;
            case 'test':
                if(!array_key_exists('value',$change) || pointerRead($data,$parts)!==$change['value']) fail(409,'Проверка test не совпала; перечитайте данные');break;
            default:fail(422,'Поддерживаются add, replace, remove, move, test');
        }
    }
    // Every intermediate patch is in memory. Only a valid final project can be committed.
    validatePayload($data);
    $oldIds=array_column(allAtlasTasks($before),'id');$newIds=array_column(allAtlasTasks($data),'id');
    clearTaskState($data,array_values(array_diff($oldIds,$newIds)));
    return ['content'=>$data['content'],'state'=>$data['state']];
}
function manageSiteMutation(string $name,array $a,array $data,string $actor,array &$result): array {
    switch($name) {
        case 'edit_site':return editSiteData($data,$a['changesJson']);
        case 'update_comment':case 'delete_comment':
            $task=atlasTask($data,$a['taskId']);$list=$task['comments'];
            $index=array_search($a['commentId'],array_column($list,'id'),true);
            if($index===false) fail(404,'Комментарий не найден');
            if($name==='delete_comment') array_splice($list,$index,1);
            else {
                $list[$index]['text']=evidenceText($a,$a['text']);
                foreach(['researchDate','source','evidenceLevel'] as $key) $list[$index][$key]=$a[$key];
                $list[$index]['updatedAt']=gmdate('c');$list[$index]['updatedBy']=$actor;
            }
            $data['state']['comments'][$a['taskId']]=$list;unset($data['state']['notes'][$a['taskId']]);
            $result['commentId']=$a['commentId'];$result['taskId']=$a['taskId'];break;
        case 'delete_task':deleteAtlasTask($data,$a['taskId']);$result['taskId']=$a['taskId'];break;
        case 'save_gate':
            $index=array_search($a['id'],array_column($data['content']['gates'],'id'),true);
            $gate=['id'=>$a['id'],'title'=>$a['title'],'subtitle'=>$a['subtitle'],'status'=>$a['status'],'statusText'=>$a['statusText'],'context'=>$a['context'],'focus'=>$a['focus']];
            if($index===false) $data['content']['gates'][]=[...$gate,'tasks'=>[]];
            else $data['content']['gates'][$index]=array_merge($data['content']['gates'][$index],$gate);
            $result['gateId']=$a['id'];break;
        case 'delete_gate':
            if(!in_array($a['gateId'],array_column($data['content']['gates'],'id'),true)) fail(404,'Этап не найден');
            $ids=array_column(array_values(array_filter(allAtlasTasks($data),fn($t)=>$t['gateId']===$a['gateId'])),'id');
            foreach($ids as $id) deleteAtlasTask($data,$id);
            $data['content']['gates']=array_values(array_filter($data['content']['gates'],fn($g)=>$g['id']!==$a['gateId']));
            $result=['gateId'=>$a['gateId'],'removedTasks'=>count($ids)];break;
        case 'move_task':
            $task=atlasTask($data,$a['taskId']);
            foreach($data['content']['gates'] as $g) foreach($g['tasks'] as $original) if($original['id']===$a['taskId']) $task['done']=$original['done'];
            foreach($data['state']['custom'] as $original) if($original['id']===$a['taskId']) $task['done']=$original['done'];
            $index=array_search($a['gateId'],array_column($data['content']['gates'],'id'),true);
            if($index===false) fail(404,'Целевой этап не найден');
            foreach($data['content']['gates'] as &$g) $g['tasks']=array_values(array_filter($g['tasks'],fn($t)=>$t['id']!==$a['taskId']));unset($g);
            $data['state']['custom']=array_values(array_filter($data['state']['custom'],fn($t)=>$t['id']!==$a['taskId']));
            foreach(['gateId','gateTitle','gate','custom','comments','taskUrl'] as $key) unset($task[$key]);
            $tasks=&$data['content']['gates'][$index]['tasks'];
            $position=$a['position']??count($tasks);if($position>count($tasks)) fail(422,'Позиция за пределами этапа');
            array_splice($tasks,$position,0,[$task]);$result=['taskId'=>$a['taskId'],'gateId'=>$a['gateId']];break;
        case 'delete_research':
            if(!in_array($a['id'],array_column($data['content']['research'],'id'),true)) fail(404,'Исследование не найдено');
            $data['content']['research']=array_values(array_filter($data['content']['research'],fn($r)=>$r['id']!==$a['id']));
            if($data['content']['site']['featuredResearchId']===$a['id']) $data['content']['site']['featuredResearchId']='';
            $result['researchId']=$a['id'];break;
        case 'delete_timeline_entry':
            if(!in_array($a['researchDate'],array_column($data['content']['days'],'date'),true)) fail(404,'Событие не найдено');
            $data['content']['days']=array_values(array_filter($data['content']['days'],fn($d)=>$d['date']!==$a['researchDate']));
            $result['date']=$a['researchDate'];break;
        default:fail(404,'Инструмент не найден');
    }
    return ['content'=>$data['content'],'state'=>$data['state']];
}
