<?php
namespace App\Core;

use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

class Router {
    private $dispatcher;

    public function __construct() {
        $this->initializeRoutes();
    }

    private function initializeRoutes() {
        $routes = require __DIR__ . '/../../routes/web.php';
        $this->dispatcher = simpleDispatcher($routes);
    }

    public function dispatch() {
    try {
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $routeInfo = $this->dispatcher->dispatch($httpMethod, rawurldecode($uri));

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $this->renderError(404, '404.php');
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $this->renderError(405, '405.php');
                break;

            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars    = $routeInfo[2];

                if ($handler instanceof \Closure) {
                    call_user_func_array($handler, array_values($vars));
                    break;
                }

                if (is_array($handler)) {
                    [$controller, $method] = $handler;
                    $this->callController($controller, $method, $vars);
                    break;
                }

                throw new \Exception('Handler de ruta no válido');
        }
    } catch (\Throwable $e) {
        throw $e;
    }
}


    private function callController($controller, $method, $vars) {
        $controllerClass = "App\\Controllers\\$controller";

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controlador $controllerClass no encontrado.");
        }

        $controllerInstance = new $controllerClass();

        if (!method_exists($controllerInstance, $method)) {
            throw new \Exception("Método $method no encontrado en $controllerClass.");
        }

        call_user_func_array([$controllerInstance, $method], $vars);
    }

    private function renderError($statusCode, $viewPath) {
        http_response_code($statusCode);
        require __DIR__ . '/../Views/errors/' . $viewPath;
        exit;
    }
}
