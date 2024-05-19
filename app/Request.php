<?php
class Request
{
    public string $url;
    public int $page = 1;
    public bool $prefix = false;
    public stdClass|array|null $data = null;
    public stdClass $parameters;
	public string $controller;
	public string $action;
	public array $params;

    public function __construct() {
        $this->url = $_SERVER['PATH_INFO'] ?? '/';
        $this->parsePage();
        $this->parsePostData();
        $this->parseGetParameters();
    }

    /**
     * Parses the page number from query parameters and updates it.
     * @return void This method returns nothing.
     **/
    private function parsePage(): void {
        $page = $_GET['page'] ?? 1;
        $this->page = max(1, filter_var($page, FILTER_VALIDATE_INT) ?: 1);
    }

    /**
     * Parses POST data and stores it in the data object.
     * @return void This method returns nothing.
     **/
    private function parsePostData(): void {
        if (!empty($_POST)) {
            $this->data = new stdClass();
            foreach ($_POST as $key => $value) {
                $this->parseDataKey($key, $value);
            }
        }
    }

    /**
     * Parses a data key and its value, then stores them in the data object.
     * @param string $key The data key to analyze.
     * @param mixed $value The value associated with the key.
     * @return void This method returns nothing.
     **/
    private function parseDataKey(string $key, mixed $value): void {
        if (str_contains($key, '--')) {
            $keys = explode('--', $key);
            $this->parseNestedData($keys, $value);
        } else {
            $this->data->$key = $value;
        }
    }

    /**
     * Parses nested data and stores it in the data object.
     * @param array $keys An array containing the keys for the nested data.
     * @param mixed $value The value to store for nested data.
     * @return void This method returns nothing.
     **/
    private function parseNestedData(array $keys, mixed $value): void {
        $current = &$this->data;
        foreach ($keys as $index => $key) {
            if ($index === count($keys) - 1) {
                $current->$key = $value;
            } else {
                if (!isset($current->$key) || !is_object($current->$key)) {
                    $current->$key = new stdClass();
                }
                $current = &$current->$key;
            }
        }
    }

    /**
     * Parses GET request parameters and stores them in the parameters object, excluding the 'page' key.
     * @return void This method returns nothing.
     **/
    private function parseGetParameters(): void {
        $this->parameters = new stdClass();
        foreach ($_GET as $key => $value) {
            if ($key !== 'page') {
                $this->parameters->$key = $value;
            }
        }
    }

    /**
     * Reloads the data into the object, merging the provided data with the existing data.
     * @param stdClass|null $data The data to be reloaded may be null.
     * @param string $parent The prefix for nested data keys.
     * @return void This method returns nothing.
     */
    public function reloadData(stdClass|null $data, string $parent = ''): void {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_object($value) && !empty((array)$value)) {
                    $this->reloadData($value, $parent . $key . '--');
                } else {
                    $this->updateData($key, $value, $parent);
                }
            }
        }
    }
    
    /**
     * Updates the object's data with the provided key and value, under an optional parent key.
     * @param string $key The data key to update.
     * @param mixed $value The value to assign to the data key.
     * @param string $parent The optional prefix for the data's parent key.
     * @return void This method returns nothing.
     **/
    private function updateData(string $key, $value, string $parent): void {
        $fullKey = $parent ? $parent . $key : $key;
        if (!isset($this->data->$fullKey)) {
            $this->data->$fullKey = $value;
        }
    }
    
}
?>