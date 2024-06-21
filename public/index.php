<?php
header('Content-Type: text/html; charset=UTF-8');
define('WEB', __DIR__);
define('ROOT', dirname(WEB));
define('DS', DIRECTORY_SEPARATOR);
define('APP', ROOT . DS . 'app');
define('SRC', APP . DS . 'src');
define('DIRPATH', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')));

require APP . DS . 'Includer.php';

use App\Dispatcher;
new Dispatcher();
?>
