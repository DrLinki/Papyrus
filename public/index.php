<?php
//declare(strict_types=1);
//$startTime = microtime(true);
header('Content-Type: text/html; charset=UTF-8');

define('WEB', __DIR__);
define('ROOT', dirname(WEB));
define('DS', DIRECTORY_SEPARATOR);
define('APP', ROOT . DS . 'app');
define('SRC', APP . DS . 'src');
define('DIRPATH', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')));

require APP . DS . 'Includer.php';
new Dispatcher();
// echo 'Time generation: ' . round((microtime(true) - $startTime), 5) . ' seconds';
?>
