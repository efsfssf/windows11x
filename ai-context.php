<?php
declare(strict_types=1);
require __DIR__.'/api/bootstrap.php';
require __DIR__.'/api/atlas.php';
$data=atlasData();
header('Content-Type: text/plain; charset=utf-8');
header('X-Atlas-Revision: '.$data['revision']);
echo contextMarkdown($data,($_GET['full']??'')==='1');
