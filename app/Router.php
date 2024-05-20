<?php
namespace App;

class Router
{
    static array $routes = [];
    static array $prefixes = [];

    /**
     * Associates a prefix with a specified URL.
     * @param string $url The URL the prefix will be associated with.
     * @param string $prefix The prefix to associate with the URL.
     * @return void This method returns nothing.
     **/
    static function prefix(string $url, string $prefix): void {
        self::$prefixes[$url] = $prefix;
    }

    /**
     * Parses a URL and updates query properties with details.
     * @param string $url The URL to parse.
     * @param object $request The query object to update with the parsed information.
     * @return bool Always returns true after updating query properties.
     **/
    static function parse(string $url, object $request): bool {
        $url = trim($url, '/');
        if (empty($url)) {
            $url = Router::$routes[0]['url'];
        } else {
            $match = false;
            foreach (Router::$routes as $route) {
                if (!$match && preg_match($route['redirreg'], $url, $match)) {
                    $url = $route['origin'];
					if ($match && is_array($match)) {
						foreach ($match as $k => $v) {
							$url = str_replace(":$k:", $v, $url);
						}
                    }
                    $match = true;
                }
            }
        }

        $params = explode('/', $url);
        if (array_key_exists($params[0], self::$prefixes)) {
            $request->prefix = self::$prefixes[$params[0]];
            array_shift($params);
        }
        $request->controller = $params[0];
        $request->action = $params[1] ?? 'index';

        foreach (self::$prefixes as $prefix => $value) {
            if (strpos($request->action, $value . '_') === 0) {
                $request->prefix = $value;
                $request->action = str_replace($value . '_', '', $request->action);
            }
        }

        $request->params = array_slice($params, 2);
        return true;
    }

    /**
     * Connects a URL to a specific route with redirection rules.
     * @param string $redir The redirect chain which can contain dynamic parameters.
     * @param string $url The original URL to connect to a specific action.
     * @return void This method returns nothing.
     **/
    static function connect(string $redir, string $url): void {
        $route = [];
        $route['params'] = [];
        $route['url'] = $url;
        $route['originreg'] = preg_replace('/([a-z0-9]+):([^\/]+)/', '${1}:(?P<${1}>${2})', $url);
        $route['originreg'] = str_replace('/*', '(?P<args>/?.*)', $route['originreg']);
        $route['originreg'] = '/^' . str_replace('/', '\/', $route['originreg']) . '$/';
        $route['origin'] = preg_replace('/([a-z0-9]+):([^\/]+)/', ':${1}:', $url);
        $route['origin'] = str_replace('/*', ':args:', $route['origin']);
        $params = explode('/', $url);
        foreach ($params as $k => $v) {
            if (strpos($v, ':')) {
                $p = explode(':', $v);
                $route['params'][$p[0]] = $p[1];
            }
        }
        $route['redirreg'] = $redir;
        $route['redirreg'] = str_replace('/*', '(?P<args>/?.*)', $route['redirreg']);
        foreach ($route['params'] as $k => $v) {
            $route['redirreg'] = str_replace(":$k", "(?P<$k>$v)", $route['redirreg']);
        }
        $route['redirreg'] = '/^' . str_replace('/', '\/', $route['redirreg']) . '$/';
        $route['redir'] = preg_replace('/:([a-z0-9]+)/', ':${1}:', $redir);
        $route['redir'] = str_replace('/*', ':args:', $route['redir']);
        self::$routes[] = $route;
    }

    /**
     * Generates a URL based on the configured routes and a possible layout.
     * @param string $url The base URL to transform.
     * @param string|null $layout (optional) Prefix to add to the URL.
     * @return string The URL transformed based on routes and layout.
     **/
    static function url(string $url = '', ?string $layout = null): string {
        if ($layout && in_array($layout, self::$prefixes)) {
            $url = $layout . '/' . $url;
        }

        trim($url, '/');
        foreach (self::$routes as $route) {
            if (preg_match($route['originreg'], $url, $match)) {
                $url = $route['redir'];
                foreach ($match as $k => $w) {
                    $url = str_replace(":$k:", $w, $url);
                }
            }
        }
        foreach (self::$prefixes as $k => $v) {
            if (strpos($url, $v) === 0) {
                $url = str_replace($v, $k, $url);
            }
        }
        if (DIRPATH != '' && DIRPATH != '/') {
            return DIRPATH . '/' . $url;
        } elseif ($url != '/') {
            return '/' . $url;
        } else {
            return $url;
        }
    }

    /**
     * Transforms a relative URL into an absolute URL by adding the root path.
     * @param string $url The relative URL to transform.
     * @return string The absolute URL with the root path appended.
     **/
    static function webroot(string $url): string {
        trim($url, '/');
        if (DIRPATH != '' && DIRPATH != '/') {
            return DIRPATH . '/' . $url;
        } elseif ($url != '/') {
            return '/' . $url;
        } else {
            return $url;
        }
    }
}
?>
