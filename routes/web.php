<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

        $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
        $r->addRoute('POST', '/login', ['LoginController', 'login']);
        $r->addRoute('GET', '/logout', Route::auth(['AuthController', 'logout']));



        $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
        

        $r->addRoute('GET', '/grupos', Route::auth(['GrupoController', 'index']));
        $r->addRoute('POST', '/grupos/datatable', Route::auth(['GrupoController', 'datatableGrupos']));
        // Estaciones
        $r->addRoute('GET', '/estaciones', Route::auth(['EstacionController', 'viewIndex']));
        $r->addRoute('GET', '/estaciones/listar', Route::auth(['EstacionController', 'listar']));
        $r->addRoute('GET', '/estaciones/crear', Route::auth(['EstacionController', 'viewCrear']));
        $r->addRoute('POST', '/estaciones/create-estacion', ['EstacionController', 'crearEstacion']);

        // Personal

        $r->addRoute('GET', '/usuarios', Route::auth(['UsuarioController', 'index']));
        
        
};

?>