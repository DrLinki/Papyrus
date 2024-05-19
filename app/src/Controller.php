<?php
class Controller
{
    // Propriétés de la classe
    public ?Request $request;
    private array $vars = [];
    private array $repositories = [];
    public string $layout = 'default';
    public string $url_prefix = '';
    private bool $rendered = false;
    public ?DateTime $datetime = null;
    //public ?User $user = null;
    public string $table = '';

    public Session $Session;
    public Pagination $Pagination;
    public Form $Form;

    /**
     * Constructeur
     * @param Request|null $request Request object of our application
     **/
    public function __construct(?Request $request = null)
    {
        $this->Session = new Session();
        $this->Pagination = new Pagination($this);
        $this->Form = new Form($this);

        if ($request) {
            $this->request = $request;
            require ROOT . DS . 'config' . DS . 'hook.php';
        }
    }

    /**
     * Rendering a view.
     * @param string $view The name of the view to render.
     * @return bool Returns true if the view was rendered successfully, otherwise false.
     **/
    public function render(string $view): bool
    {
        if ($this->rendered) return false;

        extract($this->vars);
        if (strpos($view, DS) === 0) 
            $view = SRC . DS . 'view' . $view . '.php';
        else
            $view = SRC . DS . 'view' . DS . $this->request->controller . DS . $view . '.php';

        //if ($this->request->action === 'view')
        //    $this->incrementViewsCounter();

        ob_start();
        require $view;
        $content_for_layout = ob_get_clean();
        require SRC . DS . 'view' . DS . 'layout' . DS . $this->layout . '.php';

        $this->rendered = true;
        return true;
    }

    /**
     * Makes a request to a controller and calls a specific action.
     * @param string $controller The name of the controller to load.
     * @param string $action The name of the action to call.
     * @param mixed $args (optional) The arguments to take action.
     * @return mixed The result of the called action.
     **/
    public function request(string $controller, string $action, mixed $args = null): mixed
    {
        $controller .= 'Controller';
        require_once(SRC . DS . 'Controller' . DS . $controller . '.php');
        $c = new $controller();
        
        if ($args !== null)
            return $c->$action($args);
        else
            return $c->$action();
    }

    /**
     * Sets variables for the view.
     * @param array|string $key An associative array of variables to define, or an individual key.
     * @param mixed $value The value associated with the individual key (ignored if $key is an array).
     * @return void This method returns nothing.
     **/
    public function set(array|string $key, mixed $value = null): void
    {
        if (is_array($key))
            $this->vars = array_merge($this->vars, $key);
        else
            $this->vars[$key] = $value;
    }

    /**
     * Redirects to a specific URL.
     * @param string $url The URL to redirect to.
     * @param int|null $code (optional) The HTTP status code for the redirection.
     * @return void This method returns nothing.
     **/
    public function redirect(string $url, ?int $code = null): void
    {
        $this->Session->write('redirect', true);

        if ($code === 301)
            header("HTTP/1.1 301 Moved Permanently");
        elseif ($code !== null)
            header("HTTP/1.1 $code");

        header("Location: " . Router::url($url));
        exit();
    }

    /**
     * Loads and initializes a repository.
     * @param string $name The name of the repository to load.
     * @return void This method returns nothing.
     **/
    public function loadRepository(string $name): void
    {
        $file = SRC . DS . 'Repository' . DS . $name . '.php';
        require_once $file;

        if (!isset($this->$name))
        {
            $this->$name = new $name();

            if (isset($this->Form))
                $this->$name->Form = $this->Form;

            if (!in_array(strtolower($name), $this->repositories, true))
                $this->repositories[] = strtolower($name);
        }
    }

    /**
     * Loads and initializes a controller.
     * @param string $name The name of the controller to load.
     * @return void This method returns nothing.
     **/
    public function loadController(string $name): void
    {
        if (strpos($name, 'Controller') === false)
            $name .= 'Controller';
        
        $file = SRC . DS . 'Controller' . DS . $name . '.php';
        require_once $file;

        if (!isset($this->$name))
            $this->$name = new $name();
    }

    /**
     * Handles 403 errors and displays an error message.
     * @param string $message The error message to display.
     * @return void This method returns nothing.
     **/
    public function e403(string $message): void
    {
        header("HTTP/1.1 403 Unauthorized");
        $this->set('error', $message);
        $this->render(DS . 'errors' . DS . '403');
        exit();
    }

    /**
     * Handles 404 errors and displays an error message.
     * @param string $message The error message to display.
     * @return void This method returns nothing.
     **/
    public function e404(string $message): void
    {
        header("HTTP/1.1 404 Not Found");
        $this->set('error', $message);
        $this->render(DS . 'errors' . DS . '404');
        exit();
    }

    /**
     * Retrieves additional fields for a given entity and adds them to the entity.
     * @param object $entity The entity to add the additional fields to.
     * @param string|null $class_name (optional) The name of the class, used to filter additional fields.
     *                                IIf not specified, the lowercase controller name will be used.
     * @return void This method returns nothing.
     **/
    public function getExtraFields(object $entity, ?string $class_name = null): void
    {
        if ($class_name === null)
            $class_name = strtolower($this->request->controller);

        $this->loadRepository('Extra');
        $extras = $this->Extra->find([
            'fields' => 'field_key, field_value',
            'conditions' => [
                'class_name' => $class_name,
                'class_row_id' => $entity->id,
            ],
        ]);

        if (!empty($extras)) {
            foreach ($extras as $extra) {
                $entity->{$extra->field_key} = $extra->field_value;
            }
        }
    }

    /**
     * Converts an array of additional fields to a formatted string.
     * @param array|string|null $fields Additional fields to format.
     *                                  Can be an associative array or an already formatted string.
     * @return string|null The formatted string of additional fields or null if the fields are empty.
     **/
    public function setExtra(array|string|null $fields): ?string
    {
        if (isset($fields) && !empty($fields)) {
            $extra = '';
            if (is_array($fields)) {
                foreach ($fields as $key => $value) {
                    if (!empty($extra) && !empty($value))
                        $extra .= '|';
                    if (!empty($value))
                        $extra .= $key . ':=' . $value;
                }
            } else {
                $extra = $fields;
            }
            return $extra;
        }
        return null;
    }

    /**
     * Converts a name to a unique and valid code for a given table.
     * @param string $name The name to convert to code.
     * @param string|null $table (optional) The name of the table to use for the conversion. By default, uses the current table of the instance.
     * @return string The code generated from the name, ensuring uniqueness within the specified table.
     **/
    public function name2code(string $name, ?string $table = null): string
    {
        if (!$table) $table = $this->table;
        
        $this->loadRepository(ucfirst($table));
        
        $code = str_replace('$amp;', '&', addslashes(substr(strtolower(preg_replace('/[^a-zA-Z0-9_.]/', '-', htmlspecialchars($name))), 0, 100)));

        $occ = $this->{ucfirst($table)}->findFirst([
            'conditions' => ['code' => $code]
        ]);

        if (empty($occ)) {
            return $code;
        } else {
            $i = 0;
            $slash = '';
            while (!empty($occ)) {
                $i++;
                $slash = $code . "-" . $i;
                $occ = $this->{ucfirst($table)}->findFirst([
                    'conditions' => ['code' => $slash]
                ]);
            }
            return $slash;
        }
    }

    /**
     * Validates a date according to a given format.
     * @param string|array $date The date to validate. Can be a string or an array with the 'date' and 'time' keys.
     * @param string $format (optional) The format of the date. Defaults to 'Y-m-d H:i:s'.
     * @return bool Returns true if the date is valid and matches the given format, otherwise false.
     **/
    public function validateDate(string|array $date, string $format = 'Y-m-d H:i:s'): bool
    {
        if (is_array($date) && isset($date['date']) && isset($date['time']))
            $date = $date['date'] . ' ' . $date['time'];
        $d = DateTime::createFromFormat($format, $date);
        return $d !== false && $d->format($format) === $date;
    }

	/**
     * Méthode pour initialiser les propriétés et les paramètres de la classe
     **/
    public function initialize(): void
    {
        $this->table = strtolower(str_replace('Controller', '', get_class($this)));
        $this->datetime = new DateTime();
        
        $parametersXml = simplexml_load_file(ROOT . DS . 'config' . DS . 'parameters.xml');
        $parameters = json_decode(json_encode($parametersXml), true);
        $parameters = $this->buildParameters($parameters);

        foreach ($parameters as $key => $value) {
            if(isset(Conf::${$key}))
                Conf::${$key} = $value;
        }
    }

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

	public function changeParameter(string $entity, string $node, string $value): void
	{
		$dom = new DOMDocument();
		$dom->load(ROOT . DS . 'conf' . DS . 'parameters.xml');
		$dom->formatOutput = true;

		$xpath = new DOMXPath($dom);

		$nodes = explode('/', $node);
		$query = "//{$entity}";

		foreach ($nodes as $nodeName) {
			$query .= "/{$nodeName}";
		}

		$elements = $xpath->query($query);

		if ($elements->length > 0) {
			$targetNode = $elements->item(0);
			$targetNode->nodeValue = '';
			$targetNode->appendChild($dom->createTextNode($value));
		}

		$dom->save(ROOT . DS . 'conf' . DS . 'parameters.xml');
	}

}
?>
