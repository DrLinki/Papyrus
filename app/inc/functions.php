<?php
use Config\Conf;
use App\Router;

/**
 * Shows debug information if debug level is greater than 0.
 * @param mixed $var The variable to debug.
 * @return void This method returns nothing.
 **/
function debug($var): void
{
	if (Conf::$debug > 0) {
		$debug = debug_backtrace();
		echo '<div class="backtrace">';
		echo '<a href="#"><strong>' . $debug[0]['file'] . '</strong> l. ' . $debug[0]['line'] . '</a>';
		echo '<ol>';
		foreach ($debug as $k => $v) {
			if ($k > 0) {
				if (isset($v['file']) && isset($v['line'])) {
					echo '<li><strong>' . $v['file'] . '</strong> l. ' . $v['line'] . '</li>';
				}
			}
		}
		echo '</ol>';
		echo '<pre>';
		print_r($var);
		echo '</pre>';
		echo '</div>';
	}
}

/**
 * Generates a random string of specified length.
 * @param int $len The length of the random string to generate. Default: 8.
 * @param string $chars The characters used to generate the random string. Default: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.
 * @return string The generated random string.
 **/
function randomString(int $len = 8, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
{
    $chars_len = strlen($chars) - 1;
    $str = '';
    for($i = 0; $i < $len; $i++) {
        $pos = mt_rand(0, $chars_len);
        $char = $chars[$pos];
        $str .= $char;
    }
    return $str;
}

/**
 * Converts an object to an associative array.
 * @param stdClass $stdClass The stdClass object to convert to an array.
 * @return array The associative array generated from the object.
 **/
function stdClassToArray(stdClass $stdClass): array
{
	$array = json_decode(json_encode($stdClass), true);
	return $array;
}

/**
 * Checks if a setting exists in the configuration.
 * @param string $type The type of parameter to check.
 * @param string $path The path of the parameter in the configuration.
 * @return bool True if the parameter exists, otherwise False.
 **/
function isParameter(string $type, string $path): bool
{
	if(isset(Conf::${$type}))
		$parameters = Conf::${$type};
	else
		return false;
	$items = explode('/', $path);
	if(!empty($items)){
		foreach($items as $k => $v){
			if(isset($parameters->$v))
				$parameters = $parameters->$v;
			else
				return false;
		}
	}else{
		return false;
	}
	
	return true;
}

/**
 * Formats a date in the specified format.
 * @param string $date The date to format (in dd/mm/yyyy format).
 * @param string $format The date output format ('Y-m-d' for year-month-day).
 * @return string The formatted date.
 **/
function dateFormat(string $date, string $format): string
{
	$dt = new DateTime();
	$dt->setDate(substr($date, 6, 4), substr($date, 3, 2), substr($date, 0, 2));
	return $dt->format($format);
}

/**
 * Checks if a file exists locally or via URL.
 * @param string $file The file path or URL.
 * @return mixed Returns the file path if it exists, otherwise false.
 **/
function fileExists(string $file): mixed
{
	if(file_exists($file)){
		$return = Router::webroot($file);
	}else{
		$file_headers = @get_headers($file);
		if($file_headers[0] == 'HTTP/1.1 404 Not Found')
			$return = false;
		else
			$return = $file;
	}
	return $return;
}

?>