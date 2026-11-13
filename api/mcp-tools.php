<?php
declare(strict_types=1);
function mcpTools(): array {
    $str=fn($description,$limit=20000)=>['type'=>'string','description'=>$description,'minLength'=>1,'maxLength'=>$limit];
    $id=$str('Точный ID из свежих данных',100);
    $rev=['type'=>'integer','minimum'=>0,'description'=>'revision из последнего успешного чтения'];
    $date=['type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$','description'=>'Дата исследования YYYY-MM-DD; не подменять датой загрузки'];
    $provenance=['researchDate'=>$date,'source'=>$str('Источник: ID документа, лог, SHA, команда или ссылка',2000),'evidenceLevel'=>['type'=>'string','enum'=>['confirmed','static_observation','offline_test','runtime','hypothesis','not_tested','failed']]];
    $defs=[
        ['get_project_context','Прочитать актуальный проект','Вызывать перед каждым новым исследовательским выводом, советом о галочке и записью. Возвращает свежую revision, весь план, критерии и комментарии, содержимое страниц и каталог исследований. Полные документы читай get_research; не заявляй об актуальности при ошибке.',[],[],false],
        ['get_task','Прочитать задачу и критерий галочки','Точная задача с состоянием, критерием proof, этапом и всеми датированными комментариями. Используй ID, а не приблизительное название.',['taskId'=>$id],['taskId'],false],
        ['get_research','Прочитать исследование','Возвращает полный текст по страницам символов с nextOffset. Прочитай все нужные страницы; не делай окончательный вывод из отрывка.',['id'=>$id,'offset'=>['type'=>'integer','minimum'=>0],'limit'=>['type'=>'integer','minimum'=>100,'maximum'=>30000]],['id'],false],
        ['search_research','Найти источники','Поиск в полных текстах. Возвращает ID и отрывок, а не полный источник. Для выводов прочти документы через get_research.',['query'=>$str('Поисковая строка',200),'offset'=>['type'=>'integer','minimum'=>0],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50]],['query'],false],
        ['get_changes','Прочитать последние изменения','Последние 100 сохранений, включая операции ИИ и владельца. Более ранняя история может быть недоступна.',['sinceRevision'=>['type'=>'integer','minimum'=>0]],[],false],
        ['add_comment','Добавить результат к задаче','Сохранить комментарий с датой исследования, источником и уровнем доказательства. Не меняет галочку.',['revision'=>$rev,'taskId'=>$id,'text'=>$str('Результат, ограничения и наблюдения',100000),...$provenance],['revision','taskId','text',...array_keys($provenance)],true],
        ['set_task_status','Поставить или снять галочку','Сопоставь доказательство с proof. При недостаточных данных оставь галочку снятой. Записывает объяснение в комментарий и журнал; не означает готовность всей WCOS.',['revision'=>$rev,'taskId'=>$id,'done'=>['type'=>'boolean'],'evidence'=>$str('Конкретное доказательство или причина снятия отметки',20000),...$provenance],['revision','taskId','done','evidence',...array_keys($provenance)],true],
        ['add_task','Добавить задачу','Добавляет невыполненную задачу к существующему этапу. Укажи конкретный проверяемый критерий галочки.',['revision'=>$rev,'gateId'=>$id,'title'=>$str('Название задачи',2000),'description'=>$str('Цель и контекст'),'proof'=>$str('Проверяемый критерий выполнения')],['revision','gateId','title','description','proof'],true],
        ['update_task','Уточнить задачу','Меняет только название, описание и критерий, сохраняя ID, галочку и комментарии. Не переписывай критерий ради уже полученного результата.',['revision'=>$rev,'taskId'=>$id,'title'=>$str('Название',2000),'description'=>$str('Контекст'),'proof'=>$str('Критерий выполнения')],['revision','taskId','title','description','proof'],true],
        ['save_research','Сохранить исследование','Создать или обновить исследование по стабильному ID. Для обновления сначала прочти существующий текст целиком. Сохраняй ограничения и отрицательные результаты. Не добавляй секреты: чтение сайта публичное.',['revision'=>$rev,'id'=>$id,'title'=>$str('Название',2000),'summary'=>$str('Краткое описание'),'topic'=>$str('Тема',2000),'content'=>$str('Полный текст исследования',2000000),'format'=>['type'=>'string','enum'=>['md','txt','json','csv','ps1','py']],...$provenance],['revision','id','title','summary','topic','content','format',...array_keys($provenance)],true],
        ['save_timeline_entry','Добавить событие хронологии','Создаёт событие на дату исследования или дописывает результат к существующему событию этого дня без потери текста.',['revision'=>$rev,'title'=>$str('Заголовок',2000),'text'=>$str('Событие',15000),...$provenance],['revision','title','text',...array_keys($provenance)],true],
    ];
    return array_map(function($d){[$name,$title,$description,$properties,$required,$write]=$d;
        $security=$write?[['type'=>'oauth2','scopes'=>['atlas:write']]]:[['type'=>'noauth']];
        return ['name'=>$name,'title'=>$title,'description'=>$description,'inputSchema'=>['type'=>'object','properties'=>$properties?:new stdClass(),'required'=>$required,'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>!$write,'destructiveHint'=>$write,'idempotentHint'=>!$write,'openWorldHint'=>false],'securitySchemes'=>$security,'_meta'=>['securitySchemes'=>$security]];
    },$defs);
}
function validateArguments(array $args, array $schema): void {
    foreach ($schema['required'] as $key) if (!array_key_exists($key,$args)) fail(422,'Нужно поле '.$key);
    foreach ($args as $key=>$value) {
        if (!isset($schema['properties'][$key])) fail(422,'Неизвестное поле '.$key);
        $s=$schema['properties'][$key];$valid=match($s['type']){'string'=>is_string($value),'integer'=>is_int($value),'boolean'=>is_bool($value),default=>false};
        if (!$valid) fail(422,'Неверный тип '.$key);
        if (is_string($value)) {
            $length=preg_match_all('/./us',$value);
            if ($length===false || $length<($s['minLength']??0) || $length>($s['maxLength']??2000000)) fail(422,'Неверная длина '.$key);
            if (isset($s['pattern']) && !preg_match('~'.$s['pattern'].'~D',$value)) fail(422,'Неверный формат '.$key);
        }
        if (isset($s['enum']) && !in_array($value,$s['enum'],true)) fail(422,'Неверное значение '.$key);
        if (is_int($value) && ($value<($s['minimum']??0) || $value>($s['maximum']??PHP_INT_MAX))) fail(422,'Неверный диапазон '.$key);
    }
    if (isset($args['researchDate']) && !validDate($args['researchDate'])) fail(422,'Несуществующая дата исследования');
    foreach (['id','taskId','gateId'] as $key) if (isset($args[$key]) && !preg_match('/^[A-Za-z0-9_-]+$/D',$args[$key])) fail(422,'Неверный ID');
}
function evidenceText(array $args, string $text): string {
    return $text."\n\nИсточник: ".$args['source']."\nУровень доказательства: ".$args['evidenceLevel'];
}
function appendAiComment(array &$data, string $taskId, array $args, string $text, string $actor): string {
    $task=atlasTask($data,$taskId);
    if (!isset($data['state']['comments'])) $data['state']['comments']=[];
    $data['state']['comments'][$taskId]=$task['comments'];
    // Legacy note has now been preserved as a comment; do not resurrect it after deletion.
    unset($data['state']['notes'][$taskId]);
    $id='ai-'.bin2hex(random_bytes(12));
    $data['state']['comments'][$taskId][]=['id'=>$id,'text'=>evidenceText($args,$text),'researchDate'=>$args['researchDate'],'createdAt'=>gmdate('Y-m-d\TH:i:s').'.000Z','source'=>$args['source'],'evidenceLevel'=>$args['evidenceLevel'],'author'=>$actor];
    return $id;
}
function callAtlasTool(string $name, array $a, ?array $grant): array {
    $definition=null;foreach(mcpTools() as $tool) if($tool['name']===$name) $definition=$tool;
    if (!$definition) fail(404,'Инструмент не найден');
    validateArguments($a,$definition['inputSchema']);
    $write=!$definition['annotations']['readOnlyHint'];
    if ($write && (!$grant || !in_array('atlas:write',explode(' ',$grant['scope']),true))) authChallenge();
    if (!$write) {
        $data=atlasData();$meta=['revision'=>$data['revision'],'updatedAt'=>$data['updatedAt']??null,'readAt'=>gmdate('c')];
        if ($name==='get_project_context') return contextData($data);
        if ($name==='get_task') return [...$meta,'task'=>atlasTask($data,$a['taskId'])];
        if ($name==='get_changes') return [...$meta,'changes'=>array_values(array_filter($data['changes']??[],fn($c)=>$c['revision']>($a['sinceRevision']??0))),'retainedHistoryLimit'=>100];
        if ($name==='get_research') {
            foreach ($data['content']['research'] as $r) if ($r['id']===$a['id']) {
                $text=$r['content'];$offset=$a['offset']??0;$limit=$a['limit']??20000;
                $total=strlen((string)preg_replace('/[\x80-\xBF]/','',$text));
                if ($offset>$total) fail(422,'offset за пределами документа');
                // Scan UTF-8 codepoint boundaries without an array for every character.
                $from=0;$to=strlen($text);$position=0;
                for($byte=0,$bytes=strlen($text);$byte<$bytes;$byte++) {
                    if((ord($text[$byte])&0xC0)===0x80) continue;
                    if($position===$offset) $from=$byte;
                    if($position===$offset+$limit) {$to=$byte;break;}
                    $position++;
                }
                if($offset===$total) $from=strlen($text);
                $r['content']=substr($text,$from,$to-$from);$next=$offset+$limit;
                return [...$meta,'research'=>$r,'offset'=>$offset,'totalCharacters'=>$total,'nextOffset'=>$next<$total?$next:null,'complete'=>$offset===0 && $next>=$total];
            }fail(404,'Исследование не найдено');
        }
        if ($name==='search_research') {
            $hits=[];$query=preg_quote($a['query'],'~');
            foreach ($data['content']['research'] as $r) if (preg_match('~'.$query.'~iu',$r['title']."\n".$r['summary']."\n".$r['content'])) {
                $excerpt='';if(preg_match('~.{0,120}'.$query.'.{0,180}~isu',$r['content'],$match)) $excerpt=$match[0];
                $hits[]=['id'=>$r['id'],'title'=>$r['title'],'date'=>$r['date'],'summary'=>$r['summary'],'excerpt'=>$excerpt];
            }
            $offset=$a['offset']??0;$limit=$a['limit']??20;
            return [...$meta,'matches'=>array_slice($hits,$offset,$limit),'total'=>count($hits),'nextOffset'=>$offset+$limit<count($hits)?$offset+$limit:null];
        }
        fail(404,'Инструмент не найден');
    }
    $result=[];$actor='AI: '.$grant['name'];
    $audit=['actor'=>$actor,'action'=>$name,'target'=>$a['taskId']??$a['id']??$a['gateId']??$a['researchDate']??'','source'=>$a['source']??null,'evidenceLevel'=>$a['evidenceLevel']??null];
    $data=atlasMutate($a['revision'],function($data)use($name,$a,$actor,&$result){
        switch($name) {
            case 'add_comment': $result['commentId']=appendAiComment($data,$a['taskId'],$a,$a['text'],$actor);break;
            case 'set_task_status':
                $task=atlasTask($data,$a['taskId']);
                if ($a['done'] && in_array($a['evidenceLevel'],['hypothesis','not_tested','failed'],true)) fail(422,'Гипотеза, непроверенный или неудачный опыт не подтверждают выполнение');
                $result['commentId']=appendAiComment($data,$a['taskId'],$a,($a['done']?'Отметка поставлена.':'Отметка снята.')."\nКритерий: ".$task['proof']."\nОбоснование: ".$a['evidence'],$actor);
                $data['state']['done'][$a['taskId']]=$a['done'];$result['taskId']=$a['taskId'];$result['done']=$a['done'];break;
            case 'add_task':
                $found=false;foreach($data['content']['gates'] as $g) if($g['id']===$a['gateId']) $found=true;
                if(!$found) fail(404,'Этап не найден');
                $id='ai-task-'.bin2hex(random_bytes(12));$data['state']['custom'][]=['id'=>$id,'gate'=>$a['gateId'],'title'=>$a['title'],'description'=>$a['description'],'proof'=>$a['proof'],'done'=>false,'custom'=>true];$result['taskId']=$id;break;
            case 'update_task':
                atlasTask($data,$a['taskId']);
                foreach($data['content']['gates'] as &$g) {foreach($g['tasks'] as &$t) if($t['id']===$a['taskId']) foreach(['title','description','proof'] as $key) $t[$key]=$a[$key];unset($t);}unset($g);
                foreach($data['state']['custom'] as &$t) if($t['id']===$a['taskId']) foreach(['title','description','proof'] as $key) $t[$key]=$a[$key];unset($t);
                $result['taskId']=$a['taskId'];break;
            case 'save_research':
                $record=['id'=>$a['id'],'title'=>$a['title'],'summary'=>$a['summary'],'topic'=>$a['topic'],'group'=>$actor,'date'=>$a['researchDate'],'format'=>$a['format'],'content'=>$a['content'],'source'=>$a['source'],'evidenceLevel'=>$a['evidenceLevel']];
                $index=array_search($a['id'],array_column($data['content']['research'],'id'),true);
                if ($index===false) $data['content']['research'][]=$record;else $data['content']['research'][$index]=array_merge($data['content']['research'][$index],$record);
                $result['researchId']=$a['id'];break;
            case 'save_timeline_entry':
                $index=array_search($a['researchDate'],array_column($data['content']['days'],'date'),true);
                $text=evidenceText($a,$a['text']);
                if($index===false) $data['content']['days'][]=['date'=>$a['researchDate'],'title'=>$a['title'],'text'=>$text,'tags'=>[$a['evidenceLevel'],'ИИ']];
                else $data['content']['days'][$index]['text'].="\n\n".$a['title']."\n".$text;
                $result['date']=$a['researchDate'];break;
            default: fail(404,'Инструмент не найден');
        }
        return ['content'=>$data['content'],'state'=>$data['state']];
    },$audit);
    return ['saved'=>true,'revision'=>$data['revision'],'updatedAt'=>$data['updatedAt'],...$result];
}
