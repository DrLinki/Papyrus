<?php
namespace App;
use App\Controller;
use App\Inc\Session;

class Dispatcher
{
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        Router::parse($this->request->url, $this->request);
        $controller = $this->loadController();
        $action = $this->request->action;
        if ($this->request->prefix) {
            $action = $this->request->prefix . '_' . $action;
        }
        if (!in_array($action, array_diff(get_class_methods($controller), get_class_methods(Controller::class)))) {
            $this->error('Le controller ' . $this->request->controller . ' n\'a pas de méthode ' . $action);
        }

        $this->loadAllRepositories();
        call_user_func_array([$controller, $action], $this->request->params);
        $this->request->reloadData($this->request->data);

        $controller->render($action);
    }

    /**
     * Loads all repositories.
     * @return void This method returns nothing.
     **/
    private function loadAllRepositories(): void
    {
        $cdir = scandir(SRC . DS . 'Repository');
        foreach ($cdir as $key => $value){
            if (!in_array($value,[".",".."])){
                $file = SRC . DS . 'Repository' . DS . $value;
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }

    /**
     * Shows a 404 error with a custom message.
     * @param string $message Error message to display.
     * @return void This method returns nothing.
     **/
    private function error(string $message): void
    {
        $controller = new Controller($this->request);
        $controller->Session = new Session();
        $controller->e404($message);
    }

    /**
     * Loads the controller corresponding to the current request.
     * @return mixed The controller loaded.
     */
    private function loadController(): mixed
    {
        $name = ucfirst($this->request->controller) . 'Controller';
        $file = SRC . DS . 'Controller' . DS . $name . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            $this->error($this->request->controller . ' controller not found');
        }
        $controllerName = "App\\Controller\\".$name;
        $controller = new $controllerName($this->request);
        $controller->initialize();

        return $controller;
    }
}
?>
