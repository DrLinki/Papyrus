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

    private function parsePage(): void {
        $page = $_GET['page'] ?? 1;
        $this->page = max(1, filter_var($page, FILTER_VALIDATE_INT) ?: 1);
    }

    private function parsePostData(): void {
        if (!empty($_POST)) {
            $this->data = new stdClass();
            foreach ($_POST as $key => $value) {
                $this->parseDataKey($key, $value);
            }
        }
    }

    private function parseDataKey(string $key, mixed $value): void {
        if (str_contains($key, '--')) {
            $keys = explode('--', $key);
            $this->parseNestedData($keys, $value);
        } else {
            $this->data->$key = $value;
        }
    }

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

    private function parseGetParameters(): void {
        $this->parameters = new stdClass();
        foreach ($_GET as $key => $value) {
            if ($key !== 'page') {
                $this->parameters->$key = $value;
            }
        }
    }

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
    
    private function updateData(string $key, $value, string $parent): void {
        $fullKey = $parent ? $parent . $key : $key;
        if (!isset($this->data->$fullKey)) {
            $this->data->$fullKey = $value;
        }
    }
    
}
?>