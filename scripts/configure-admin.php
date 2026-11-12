<?php
/* Run from SSH/hosting terminal: php scripts/configure-admin.php */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$directory = getenv('ATLAS_PRIVATE_DIR') ?: dirname(__DIR__,2).'/windows11x-private';
if (!is_dir($directory)) mkdir($directory,0700,true);
$directory = realpath($directory);
$root = realpath(dirname(__DIR__));
if (!$directory || $directory === $root || str_starts_with($directory,$root.DIRECTORY_SEPARATOR)) exit("Выберите папку вне корня сайта.\n");
echo "Логин [admin]: "; $username=trim((string)fgets(STDIN)) ?: 'admin';
$hidden=function_exists('shell_exec') && stripos(PHP_OS,'WIN')!==0 && stream_isatty(STDIN);
if ($hidden) shell_exec('stty -echo');
try { echo "Пароль (не менее 12 символов): "; $password=rtrim((string)fgets(STDIN),"\r\n"); echo "\nПовторите пароль: "; $repeat=rtrim((string)fgets(STDIN),"\r\n"); }
finally { if ($hidden) shell_exec('stty echo'); echo "\n"; }
if (strlen($password)<12 || $password!==$repeat) exit("Пароли не совпали или пароль слишком короткий.\n");
$config=['username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
$path=$directory.'/config.php';
if (file_put_contents($path,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false) exit("Не удалось сохранить настройки.\n");
chmod($path,0600); echo "Учётная запись сохранена. Откройте сайт и нажмите «Войти».\n";
