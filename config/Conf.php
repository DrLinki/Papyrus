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

	public function __construct(){
        self::$settings = new stdClass();
        self::$entities = new stdClass();
        self::$data = new stdClass();
        self::$processes = new stdClass();

		$parametersXml = simplexml_load_file(ROOT . DS . 'config' . DS . 'parameters.xml');
        $parameters = json_decode(json_encode($parametersXml), true);
        $parameters = $this->buildParameters($parameters);

        foreach ($parameters as $key => $value) {
            if(isset($this::${$key})){
				if($value)
                	$this::${$key} = $value;
			}
        }
	}

	/**
     * Constructs a stdClass object from an array of arguments.
     * @param array $arguments The argument array to convert to a stdClass object.
     * @return stdClass The stdClass object constructed from the argument array.
     **/
	public function buildParameters(array $arguments): stdClass
	{
		$out = new stdClass;
		
		foreach ($arguments as $key => $value) {
			if (empty($value)) {
				$out->{$key} = null;
			} elseif ($value === "true") {
				$out->{$key} = true;
			} elseif ($value === "false") {
				$out->{$key} = false;
			} elseif (is_array($value)) {
				$noChange = true;
				
				foreach ($value as $k => $v) {
					if (!is_numeric($k)) {
						$noChange = false;
						break;
					}
				}
				
				if ($noChange) {
					$out->{$key} = $value;
				} else {
					$out->{$key} = $this->buildParameters($value);
				}
			} else {
				$out->{$key} = $value;
			}
		}
		
		return $out;
	}
}

include 'routes' . DS . 'redirections.php';

?>
