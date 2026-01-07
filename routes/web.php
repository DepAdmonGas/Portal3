<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

        $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
        $r->addRoute('POST', '/login', ['LoginController', 'login']);

        $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
        $r->addRoute('GET', '/logout', Route::auth(['AuthController', 'logout']));
        
};

?>