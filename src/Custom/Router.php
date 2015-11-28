<?php

namespace Custom;

class Router
{
    protected $routes;
    protected $request;
    protected $config;

    public function __construct($config)
    {
        $env = \Slim\Environment::getInstance();
        $this->request = new \Slim\Http\Request($env);
        $this->routes = [];
        $this->config = $config;
    }

    public function addRoutes($routes)
    {
        foreach ($routes as $route => $path) {
            $method = "any";

            if (strpos($path, "@") !== false){
                list($path, $method) = explode("@", $path);
            }

            $func = $this->processCallback($path);

            $r = new \Slim\Route($route, $func);
            $r->setHttpMethods(strtoupper($method));

            array_push($this->routes, $r);
        }
    }

    protected function processCallback($path)
    {
        $class = "Main";

        if (strpos($path, ":") !== false) {
            list($class, $path) = explode(":", $path);
        }

        $function = ($path != "") ? $path : "index";

        $func = function () use ($class, $function) {
            $class = '\Controllers\\' . $class;
            $class = new $class();

            //add middleware
            $class->add(new \Slim\Middleware\JwtAuthentication($this->config['middleware']));

            $args = func_get_args();

            return call_user_func_array([$class, $function], $args);
        };

        return $func;
    }

    public function run()
    {
        $display404 = true;
        $uri = $this->request->getResourceUri();
        $method = $this->request->getMethod();

        foreach ($this->routes as $i => $route) {
            if ($route->matches($uri)) {
                if ($route->supportsHttpMethod($method) || 
                    $route->supportsHttpMethod("ANY")) {
                    // var_dump($route->getCallable());
                    call_user_func_array($route->getCallable(), 
                        array_values($route->getParams()));
                    $display404 = false;
                }
            }
        }

        if ($display404) {
            echo "404 - route not found";
        }
    }
}