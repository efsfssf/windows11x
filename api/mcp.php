<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/atlas.php';
require __DIR__.'/oauth-lib.php';
require __DIR__.'/mcp-tools.php';
$origin=$_SERVER['HTTP_ORIGIN']??null;
if ($origin!==null && !in_array($origin,[atlasOrigin(),'https://chatgpt.com','https://claude.ai'],true)) fail(403,'Origin not allowed');
$method=$_SERVER['REQUEST_METHOD'];
if ($method==='GET' || $method==='DELETE') { header('Allow: POST');fail(405,'Stateless Streamable HTTP: use POST; no SSE stream'); }
if ($method!=='POST') fail(405,'Use POST');
if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')) fail(415,'Use application/json');
$raw=file_get_contents('php://input',false,null,0,2500001);
if ($raw===false || strlen($raw)>2500000) fail(413,'Request too large');
$id=null;
function rpcResult(mixed $id,array $result): never { reply(200,['jsonrpc'=>'2.0','id'=>$id,'result'=>$result?:new stdClass()]); }
function rpcError(mixed $id,int $code,string $message): never { reply(200,['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]]); }
try {
    try{$rpc=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){rpcError(null,-32700,'Parse error');}
    if (!is_array($rpc) || array_is_list($rpc) || ($rpc['jsonrpc']??'')!=='2.0' || !is_string($rpc['method']??null)) rpcError(null,-32600,'Invalid request');
    $id=$rpc['id']??null;
    if(array_key_exists('id',$rpc) && !is_string($id) && !is_int($id)) rpcError(null,-32600,'Invalid request ID');
    $params=$rpc['params']??[];if(!is_array($params)) rpcError($id,-32602,'Invalid params');
    $grant=bearerGrant();
    if(!array_key_exists('id',$rpc)) {if(!str_starts_with($rpc['method'],'notifications/')) {http_response_code(400);exit;} http_response_code(202);exit;}
    switch($rpc['method']) {
        case 'initialize':
            $version=$params['protocolVersion']??'';
            if(!in_array($version,['2025-03-26','2025-06-18','2025-11-25'],true)) $version='2025-11-25';
            rpcResult($id,['protocolVersion'=>$version,'serverInfo'=>['name'=>'windows11x-atlas','version'=>'1.0.0'],'capabilities'=>['tools'=>new stdClass(),'resources'=>new stdClass(),'prompts'=>new stdClass()],'instructions'=>aiInstructions()]);
        case 'ping': rpcResult($id,[]);
        case 'tools/list': rpcResult($id,['tools'=>mcpTools()]);
        case 'tools/call':
            if(!is_string($params['name']??null) || (isset($params['arguments']) && !is_array($params['arguments']))) rpcError($id,-32602,'Invalid tool arguments');
            try {
                $result=callAtlasTool($params['name'],$params['arguments']??[],$grant);
                rpcResult($id,['content'=>[['type'=>'text','text'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]],'structuredContent'=>$result,'isError'=>false]);
            } catch(AtlasError $e) {
                $error=['error'=>$e->getMessage(),'status'=>$e->status];
                if($e->status===409) $error['currentRevision']=atlasData()['revision'];
                $result=['content'=>[['type'=>'text','text'=>json_encode($error,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]],'isError'=>true];
                if($e->status===401) $result['_meta']=['mcp/www_authenticate'=>['Bearer resource_metadata="'.atlasOrigin().'/api/oauth.php?action=resource", scope="atlas:write"']];
                rpcResult($id,$result);
            }
        case 'resources/list': rpcResult($id,['resources'=>[['uri'=>'atlas://context','name'=>'Актуальный контекст Windows11X','mimeType'=>'text/plain'],['uri'=>'atlas://full','name'=>'Контекст и полные исследования','mimeType'=>'text/plain']]]);
        case 'resources/read':
            if(!in_array($params['uri']??'', ['atlas://context','atlas://full'],true)) rpcError($id,-32602,'Unknown resource');
            rpcResult($id,['contents'=>[['uri'=>$params['uri'],'mimeType'=>'text/plain','text'=>contextMarkdown(atlasData(),$params['uri']==='atlas://full')]]]);
        case 'prompts/list': rpcResult($id,['prompts'=>[['name'=>'research_with_current_context','description'=>'Начать исследование с чтения свежих данных']]]);
        case 'prompts/get':
            if(($params['name']??'')!=='research_with_current_context') rpcError($id,-32602,'Unknown prompt');
            rpcResult($id,['messages'=>[['role'=>'user','content'=>['type'=>'text','text'=>aiInstructions()]]]]);
        default: rpcError($id,-32601,'Method not found');
    }
} catch (AtlasError $e) { reply($e->status,['error'=>$e->getMessage()]); }
