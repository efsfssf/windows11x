<?php
declare(strict_types=1);
require_once __DIR__.'/site-management.php';
require_once __DIR__.'/research-files.php';
function mcpTools(): array {
    $str=fn($description,$limit=20000)=>['type'=>'string','description'=>$description,'minLength'=>1,'maxLength'=>$limit];
    $id=$str('Точный ID из свежих данных',100);
    $rev=['type'=>'integer','minimum'=>0,'description'=>'revision из последнего успешного чтения'];
    $date=['type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$','description'=>'Дата исследования YYYY-MM-DD; не подменять датой загрузки'];
    $provenance=['researchDate'=>$date,'source'=>$str('Источник: ID документа, лог, SHA, команда или ссылка',2000),'evidenceLevel'=>['type'=>'string','enum'=>['confirmed','static_observation','offline_test','runtime','hypothesis','not_tested','failed']]];
    $reason=$str('Причина изменения или удаления',2000);
    $section=['type'=>'string','enum'=>['site','texts','gates','days','architecture','terms','research','artifacts','overviewBlocks','myths','metadata','state']];
    $page=['offset'=>['type'=>'integer','minimum'=>0],'limit'=>['type'=>'integer','minimum'=>100,'maximum'=>30000]];
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
        ['update_timeline_entry','Обновить событие хронологии','Заменяет заголовок и текст существующего события за точную дату researchDate. Сначала прочитай хронологию через get_site_section(section=days), включая все страницы. Передай полный новый текст с сохранением нужных результатов и ограничений: старый текст заменяется, а не дописывается. Теги и дополнительные поля события сохраняются; другие даты и разделы не меняются. Для новой даты используй save_timeline_entry. Перед заменой обязательна резервная копия на 7 дней.',['revision'=>$rev,'title'=>$str('Новый заголовок',2000),'text'=>$str('Полный новый текст события',15000),'reason'=>$reason,...$provenance],['revision','title','text','reason',...array_keys($provenance)],true],
    ];
    $defs=[...$defs,
        ['import_research_folder','Загрузить исследования из папки сервера','Рекурсивно импортирует md/markdown/json/csv/txt/ps1/py из library или её подпапки. Существующие записи обновляет по path с сохранением ID и метаданных, новые добавляет; другие исследования не удаляет. Новым файлам без даты в имени назначается дата изменения файла: её нужно проверить. Изменение резервируется на 7 дней.',['revision'=>$rev,'folder'=>$str('Папка относительно корня сайта, например library/ai',2000),'reason'=>$reason],['revision','folder','reason'],true],
        ['get_site_section','Прочитать исходные данные раздела','Исходный JSON любого раздела сайта. Для правок edit_site сначала прочитайте нужный раздел и все страницы. В state находятся отметки, комментарии и свои задачи.',['section'=>$section,...$page],['section'],false],
        ['edit_site','Изменить любые данные сайта','Атомарный пакет JSON Patch над /content и /state: add, replace, remove, move, test. Может изменять и удалять любые тексты, оформление, навигацию, этапы, задачи, архитектуру, хронологию, словарь и исследования. Перед записью прочитай get_site_section и актуальную revision. changesJson — JSON-строка массива операций; индекс списка начинается с 0, /- добавляет в конец, ~1 экранирует /, ~0 экранирует ~. Пример: [{"op":"replace","path":"/content/site/name","value":"Новое имя"}]. Итоговая структура должна остаться валидной. Автоматическая полная копия на 7 дней обязательна. Не имеет доступа к паролям, токенам, резервным копиям или серверным командам.',['revision'=>$rev,'changesJson'=>$str('JSON-массив до 100 операций над content/state',2000000),'reason'=>$reason],['revision','changesJson','reason'],true],
        ['update_comment','Редактировать комментарий','Изменяет конкретный комментарий по commentId, сохраняя его ID и время создания. Перед правкой прочитай get_task. Старое содержимое резервируется на 7 дней.',['revision'=>$rev,'taskId'=>$id,'commentId'=>$str('Точный ID комментария из get_task',200),'text'=>$str('Новый текст',95000),...$provenance],['revision','taskId','commentId','text',...array_keys($provenance)],true],
        ['delete_comment','Удалить комментарий','Удаляет только указанный commentId из указанной задачи. Не удаляй другие комментарии или задачу. Полная копия на 7 дней создаётся до удаления.',['revision'=>$rev,'taskId'=>$id,'commentId'=>$str('Точный ID комментария',200),'reason'=>$reason],['revision','taskId','commentId','reason'],true],
        ['delete_task','Удалить задачу','Удаляет указанную задачу (включая исходные задачи), её отметку и комментарии. Не затрагивает другие задачи. Копия на 7 дней обязательна. Для простого снятия галочки используй set_task_status(done=false).',['revision'=>$rev,'taskId'=>$id,'reason'=>$reason],['revision','taskId','reason'],true],
        ['save_gate','Добавить или изменить этап','Создаёт этап или меняет его поля по ID; существующие задачи сохраняются. Перестановка этапов доступна через edit_site.',['revision'=>$rev,'id'=>$id,'title'=>$str('Название'),'subtitle'=>['type'=>'string','maxLength'=>20000],'status'=>['type'=>'string','enum'=>['partial','wait','future','complete']],'statusText'=>['type'=>'string','maxLength'=>20000],'context'=>['type'=>'string','maxLength'=>20000],'focus'=>['type'=>'boolean']],['revision','id','title','subtitle','status','statusText','context','focus'],true],
        ['delete_gate','Удалить этап и его задачи','Каскадно удаляет этап со всеми его задачами, отметками и комментариями. Используй только при запросе удалить весь этап. Всё резервируется на 7 дней.',['revision'=>$rev,'gateId'=>$id,'reason'=>$reason],['revision','gateId','reason'],true],
        ['move_task','Переместить задачу','Переносит задачу в другой этап или меняет её порядок. ID, отметка и комментарии сохраняются. Позиция с нуля, по умолчанию конец этапа.',['revision'=>$rev,'taskId'=>$id,'gateId'=>$id,'position'=>['type'=>'integer','minimum'=>0]],['revision','taskId','gateId'],true],
        ['delete_research','Удалить исследование','Удаляет документ по точному ID; копия полного текста сохраняется на 7 дней.',['revision'=>$rev,'id'=>$id,'reason'=>$reason],['revision','id','reason'],true],
        ['delete_timeline_entry','Удалить событие дня','Удаляет событие хронологии по дате исследования; перед удалением резервирует всё содержимое.',['revision'=>$rev,'researchDate'=>$date,'reason'=>$reason],['revision','researchDate','reason'],true],
        ['list_backups','Посмотреть резервные копии за 7 дней','Закрытый список автоматических копий с причиной, автором, revision и сроком хранения. Нужен OAuth владельца. Копии нельзя удалить или отключить через MCP.',['offset'=>['type'=>'integer','minimum'=>0],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200]],[],false],
        ['get_backup','Прочитать резервную копию','Закрытый просмотр копии до ошибочного изменения. summary показывает состав, остальные section возвращают исходный JSON по страницам. Для восстановления отдельных записей прочти копию и перенеси нужные данные через edit_site с текущей revision. Полный откат всего сайта выполняет restore_backup.',['backupId'=>$str('ID из list_backups',120),'section'=>['type'=>'string','enum'=>['summary',...$section['enum']]],...$page],['backupId'],false],
        ['restore_backup','Восстановить весь сайт из копии','Возвращает ВСЁ содержимое и план к указанной копии: более поздние изменения тоже откатываются. Сначала прочти get_backup и уточни соответствие запрошенному восстановлению. Для одной записи используй edit_site. Текущее состояние предварительно резервируется на 7 дней; revision всегда увеличивается, история операций остаётся текущей.',['revision'=>$rev,'backupId'=>$str('ID копии',120),'reason'=>$reason],['revision','backupId','reason'],true],
    ];
    return array_map(function($d){[$name,$title,$description,$properties,$required,$write]=$d;
        $protected=in_array($name,['list_backups','get_backup'],true);
        $security=($write||$protected)?[['type'=>'oauth2','scopes'=>['atlas:write']]]:[['type'=>'noauth']];
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
    foreach (['id','taskId','gateId','commentId'] as $key) if (isset($args[$key]) && !preg_match('/^[A-Za-z0-9_-]+$/D',$args[$key])) fail(422,'Неверный ID');
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
    if (($write || in_array($name,['list_backups','get_backup'],true)) && (!$grant || !in_array('atlas:write',explode(' ',$grant['scope']),true))) authChallenge();
    if($name==='list_backups') return [...listAtlasBackups($a['offset']??0,$a['limit']??50),'revision'=>atlasData()['revision']];
    if($name==='get_backup') {
        $b=readAtlasBackup($a['backupId']);$snapshot=$b['snapshot'];$section=$a['section']??'summary';
        if($section==='summary') return ['metadata'=>$b['metadata'],'tasks'=>count(allAtlasTasks($snapshot)),'research'=>count($snapshot['content']['research']),'gates'=>count($snapshot['content']['gates'])];
        return ['metadata'=>$b['metadata'],'section'=>$section,...textPage(json_encode(siteSection($snapshot,$section),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),$a['offset']??0,$a['limit']??20000)];
    }
    if($name==='restore_backup') {
        $data=restoreAtlasBackup($a['revision'],$a['backupId'],['actor'=>'AI: '.$grant['name'],'reason'=>$a['reason']]);
        return ['saved'=>true,'revision'=>$data['revision'],'updatedAt'=>$data['updatedAt'],'restoredFrom'=>$a['backupId'],'backupId'=>$data['backupId'],'backupExpiresAt'=>$data['backupExpiresAt']];
    }
    if (!$write) {
        $data=atlasData();$meta=['revision'=>$data['revision'],'updatedAt'=>$data['updatedAt']??null,'readAt'=>gmdate('c')];
        if ($name==='get_site_section') return [...$meta,'section'=>$a['section'],...textPage(json_encode(siteSection($data,$a['section']),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),$a['offset']??0,$a['limit']??20000)];
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
    $audit=['actor'=>$actor,'action'=>$name,'target'=>$a['taskId']??$a['id']??$a['gateId']??$a['researchDate']??$a['folder']??'','source'=>$a['source']??null,'evidenceLevel'=>$a['evidenceLevel']??null,'reason'=>$a['reason']??null,'commentId'=>$a['commentId']??null];
    $data=atlasMutate($a['revision'],function($data)use($name,$a,$actor,&$result){
        switch($name) {
            case 'import_research_folder':
                $scan=scanResearchFolder($a['folder']);$merge=mergeResearchFiles($data['content']['research'],$scan['files']);
                $data['content']['research']=$merge['research'];$result=['folder'=>$scan['folder'],'added'=>$merge['added'],'updated'=>$merge['updated'],'warnings'=>$scan['warnings']];break;
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
                $record=['updatedAt'=>gmdate('c'),'id'=>$a['id'],'title'=>$a['title'],'summary'=>$a['summary'],'topic'=>$a['topic'],'group'=>$actor,'date'=>$a['researchDate'],'format'=>$a['format'],'content'=>$a['content'],'source'=>$a['source'],'evidenceLevel'=>$a['evidenceLevel']];
                $index=array_search($a['id'],array_column($data['content']['research'],'id'),true);
                if ($index===false) $data['content']['research'][]=[...$record,'createdAt'=>gmdate('c')];else $data['content']['research'][$index]=array_merge($data['content']['research'][$index],$record);
                $result['researchId']=$a['id'];break;
            case 'update_timeline_entry':
                $index=array_search($a['researchDate'],array_column($data['content']['days'],'date'),true);
                if($index===false) fail(404,'Событие не найдено. Для новой даты используйте save_timeline_entry');
                $data['content']['days'][$index]=[...$data['content']['days'][$index],'title'=>$a['title'],'text'=>evidenceText($a,$a['text']),'source'=>$a['source'],'evidenceLevel'=>$a['evidenceLevel']];
                $result['date']=$a['researchDate'];break;
            case 'save_timeline_entry':
                $index=array_search($a['researchDate'],array_column($data['content']['days'],'date'),true);
                $text=evidenceText($a,$a['text']);
                if($index===false) $data['content']['days'][]=['date'=>$a['researchDate'],'title'=>$a['title'],'text'=>$text,'tags'=>[$a['evidenceLevel'],'ИИ']];
                else $data['content']['days'][$index]['text'].="\n\n".$a['title']."\n".$text;
                $result['date']=$a['researchDate'];break;
            default: return manageSiteMutation($name,$a,$data,$actor,$result);
        }
        return ['content'=>$data['content'],'state'=>$data['state']];
    },$audit);
    return ['saved'=>true,'revision'=>$data['revision'],'updatedAt'=>$data['updatedAt'],'backupId'=>$data['backupId'],'backupExpiresAt'=>$data['backupExpiresAt'],...$result];
}
