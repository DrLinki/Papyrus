<?php
namespace Config;
use stdClass;

class Conf{
	static int $debug = 1;
	static array $databases = [
		'default' => [
			'host' => 'localhost',
			'prefix' => 'papyrus_',
			'database' => 'papyrus',
			'login' => 'root',
			'password' => ''
		],
		'local' => [
			'host' => 'localhost',
			'prefix' => 'papyrus_',
			'database' => 'papyrus',
			'login' => 'root',
			'password' => ''
		]
	];
	static array $parameters = [];
	static stdClass $settings;
	static stdClass $entities;
	static stdClass|null $processes;
	static stdClass $data;
	static array $texts = [];
}

include 'routes' . DS . 'redirections.php';

?>
