<?php
/* PHP 8.1+. Credentials and runtime data live OUTSIDE public_html. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $status, array $data): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
class AtlasError extends RuntimeException { public function __construct(public int $status, string $message) { parent::__construct($message); } }
function fail(int $status, string $message): never { throw new AtlasError($status, $message); }
function atomicWrite(string $path, array $data): void {
    $temp = tempnam(dirname($path), '.atlas-');
    if ($temp === false) throw new RuntimeException('Temporary file unavailable');
    try {
        if (file_put_contents($temp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Write failed');
        chmod($temp, 0600);
        if (!rename($temp, $path)) throw new RuntimeException('Rename failed');
    } finally { if (is_file($temp)) unlink($temp); }
}
function readJson(string $path, array $fallback): array {
    if (!is_file($path)) return $fallback;
    $value = json_decode((string)file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Invalid data');
    return $value;
}
function textField(array $value, string $key, int $limit = 2000000): void {
    if (!isset($value[$key]) || !is_string($value[$key]) || strlen($value[$key]) > $limit) fail(422, 'Неверное поле: '.$key);
}
function collection(array $value, string $key, int $limit): array {
    if (!isset($value[$key]) || !is_array($value[$key]) || !array_is_list($value[$key]) || count($value[$key]) > $limit) fail(422, 'Неверный список: '.$key);
    return $value[$key];
}
function validId(array $row, array &$ids, int $limit = 100): void {
    textField($row, 'id', $limit);
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $row['id']) || isset($ids[$row['id']])) fail(422, 'ID должен быть уникальным: латиница, цифры, _ и -');
    $ids[$row['id']] = true;
}
function validDate(string $date): bool { $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date); return $d && $d->format('Y-m-d') === $date; }
function validatePayload(array $payload): void {
    if (!isset($payload['content'], $payload['state']) || !is_array($payload['content']) || !is_array($payload['state'])) fail(422, 'Нужны содержимое и состояние плана');
    $c = $payload['content'];
    textField($c, 'snapshot', 80); textField($c, 'artifactHash', 200);
    if (!isset($c['site'], $c['texts'], $c['architecture']) || !is_array($c['site']) || !is_array($c['texts']) || !is_array($c['architecture'])) fail(422, 'Неверная структура сайта');
    foreach (['name','subtitle','latest','footer','footerNote','featuredResearchId'] as $key) textField($c['site'], $key, 2000);
    foreach ($c['texts'] as $key => $text) if (!is_string($key) || !is_string($text) || strlen($text)>100000) fail(422, 'Неверный текст');
    if (!isset($c['site']['nav']) || !is_array($c['site']['nav'])) fail(422,'Нужны названия разделов');
    foreach (['overview','plan','architecture','timeline','library','glossary'] as $key) textField($c['site']['nav'],$key,200);
    if (!isset($c['overviewBlocks']) || !is_array($c['overviewBlocks'])) fail(422,'Нужны карточки обзора');
    foreach (['proofSteps','corrections','flow'] as $key) foreach (collection($c['overviewBlocks'],$key,100) as $row) {
        if (!is_array($row)) fail(422,'Неверная карточка'); textField($row,'title',20000);
        if ($key!=='flow') textField($row,'text',20000);
        if ($key==='proofSteps' && !is_bool($row['complete']??null)) fail(422,'Неверная ступень');
        if ($key==='flow' && !is_bool($row['current']??null)) fail(422,'Неверный шаг');
    }
    foreach (collection($c,'myths',100) as $row) { if (!is_array($row)) fail(422,'Неверное исправление'); foreach (['before','after','text'] as $key) textField($row,$key,20000); }
    foreach (['volumes'=>['letter','title','text'],'phases'=>['label','title','text','when']] as $list=>$fields) foreach (collection($c['architecture'],$list,100) as $row) {
        if (!is_array($row)) fail(422,'Неверная карточка архитектуры'); foreach ($fields as $key) textField($row,$key,20000);
    }
    $gateIds=[]; $taskIds=[];
    foreach (collection($c,'gates',100) as $g) {
        if (!is_array($g)) fail(422,'Неверный этап'); validId($g,$gateIds);
        foreach (['title','subtitle','statusText','context'] as $key) textField($g,$key,20000);
        if (!in_array($g['status']??'', ['partial','wait','future','complete'], true)) fail(422,'Неверный статус этапа');
        if (isset($g['focus']) && !is_bool($g['focus'])) fail(422,'Неверный фокус этапа');
        foreach (collection($g,'tasks',500) as $t) {
            if (!is_array($t)) fail(422,'Неверная задача'); validId($t,$taskIds);
            foreach (['title','description','proof'] as $key) textField($t,$key,20000);
            if (!is_bool($t['done']??null)) fail(422,'Неверная отметка задачи');
        }
    }
    $dates=[];
    foreach (collection($c,'days',1000) as $d) {
        if (!is_array($d)) fail(422,'Неверная дата'); textField($d,'date',10);
        if (!validDate($d['date']) || isset($dates[$d['date']])) fail(422,'Дата должна быть уникальной, формат ГГГГ-ММ-ДД'); $dates[$d['date']]=true;
        foreach (['title','text'] as $key) textField($d,$key,20000);
        foreach (collection($d,'tags',100) as $tag) if (!is_string($tag)) fail(422,'Неверный тег');
    }
    foreach (collection($c,'terms',1000) as $term) if (!is_array($term) || count($term)!==2 || !is_string($term[0]??null) || !is_string($term[1]??null)) fail(422,'Неверный термин');
    foreach (['nt','fos'] as $mode) foreach (collection($c['architecture'],$mode,100) as $node) {
        if (!is_array($node)) fail(422,'Неверный узел'); foreach (['title','short','detail','evidence'] as $key) textField($node,$key,20000);
    }
    $ids=[];
    foreach (collection($c,'research',2000) as $r) {
        if (!is_array($r)) fail(422,'Неверный документ'); validId($r,$ids);
        foreach (['title','summary','topic','group','date','format','content'] as $key) textField($r,$key);
        if (!validDate($r['date']) || !in_array($r['format'],['md','json','csv','txt','ps1','py'],true)) fail(422,'Неверная дата или формат документа');
    }
    $s = $payload['state'];
    if (($s['version']??0)!==1 || !is_array($s['done']??null) || !is_array($s['notes']??null)) fail(422,'Неверное состояние плана');
    foreach ($s['done'] as $id=>$done) if (!is_string($id) || !is_bool($done)) fail(422,'Неверная отметка');
    foreach ($s['notes'] as $id=>$note) if (!is_string($id) || !is_string($note) || strlen($note)>100000) fail(422,'Неверная заметка');
    if (isset($s['comments'])) {
        if (!is_array($s['comments']) || count($s['comments'])>10000) fail(422,'Неверные комментарии');
        $commentIds=[];
        foreach ($s['comments'] as $taskId=>$list) {
            if (!is_string($taskId) || !is_array($list) || !array_is_list($list) || count($list)>1000) fail(422,'Неверный список комментариев');
            foreach ($list as $comment) {
                if (!is_array($comment)) fail(422,'Неверный комментарий'); validId($comment,$commentIds,200); textField($comment,'text',100000);
                if (!trim($comment['text'])) fail(422,'Комментарий не может быть пустым');
                if (!array_key_exists('researchDate',$comment) || ($comment['researchDate']!==null && (!is_string($comment['researchDate']) || !validDate($comment['researchDate'])))) fail(422,'Неверная дата исследования');
                if (!array_key_exists('createdAt',$comment) || ($comment['createdAt']!==null && (!is_string($comment['createdAt']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/D',$comment['createdAt']) || strtotime($comment['createdAt'])===false))) fail(422,'Неверная дата комментария');
            }
        }
    }
    foreach (collection($s,'custom',2000) as $t) {
        if (!is_array($t)) fail(422,'Неверный шаг'); validId($t,$taskIds);
        foreach (['title','description','proof','gate'] as $key) textField($t,$key,20000);
        if (!isset($gateIds[$t['gate']]) || ($t['custom']??null)!==true || !is_bool($t['done']??null)) fail(422,'Неверный этап своего шага');
    }
}

function atlasSession(): bool {
    global $privateDir, $configured, $username, $passwordHash;
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
    session_name('windows11x_session');
    session_save_path($privateDir);
    session_start(['use_strict_mode'=>1, 'use_only_cookies'=>1, 'cookie_httponly'=>1, 'cookie_secure'=>$https, 'cookie_samesite'=>'Strict', 'cookie_path'=>'/', 'gc_maxlifetime'=>28800]);
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    if (isset($_SESSION['expires']) && $_SESSION['expires']<time()) unset($_SESSION['editor']);
    $editor = $configured && !empty($_SESSION['editor']) && hash_equals((string)($_SESSION['credential']??''), hash('sha256',$username.$passwordHash));
    return $editor;
}

set_exception_handler(function(Throwable $e): void {
    if ($e instanceof AtlasError) reply($e->status,['error'=>$e->getMessage()]);
    error_log('Windows11X API: '.$e->getMessage());
    reply(503,['error'=>'Сервер временно недоступен']);
});
    $webRoot = realpath(dirname(__DIR__));
    $privateDir = getenv('ATLAS_PRIVATE_DIR') ?: dirname($webRoot).'/windows11x-private';
    // Resolve before reading anything, never allow a public runtime directory.
    if (!is_dir($privateDir) && !mkdir($privateDir,0700,true) && !is_dir($privateDir)) throw new RuntimeException('Private directory unavailable');
    $privateDir = realpath($privateDir);
    if (!$privateDir || $privateDir===$webRoot || str_starts_with($privateDir,$webRoot.DIRECTORY_SEPARATOR)) throw new RuntimeException('Private directory must be outside web root');
    $config = is_file($privateDir.'/config.php') ? require $privateDir.'/config.php' : [];
    $passwordHash = getenv('ATLAS_ADMIN_PASSWORD_HASH') ?: ($config['password_hash']??'');
    $username = getenv('ATLAS_ADMIN_USER') ?: ($config['username']??'admin');
    $configured = is_string($passwordHash) && !empty(password_get_info($passwordHash)['algo']);
