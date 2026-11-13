<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/api/bootstrap.php';
require dirname(__DIR__,3).'/api/oauth-lib.php';
reply(200,resourceMetadata());
