<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

        $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
        $r->addRoute('POST', '/login', ['LoginController', 'login']);
        $r->addRoute('GET', '/logout', Route::auth(['AuthController', 'logout']));



        $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
        

        $r->addRoute('GET', '/grupos', Route::auth(['GrupoController', 'index']));
        $r->addRoute('GET', '/grupos/datatable', Route::auth(['GrupoController', 'datatableGrupos']));
        $r->addRoute('POST', '/grupos/create', Route::auth(['GrupoController', 'createGrupo']));
        $r->addRoute('POST', '/grupos/delete', Route::auth(['GrupoController', 'deleteGrupo']));
        // Estaciones
        $r->addRoute('GET', '/estaciones', Route::auth(['EstacionController', 'viewIndex']));
        $r->addRoute('GET', '/estaciones/datatable', Route::auth(['EstacionController', 'datatableEstaciones']));
        $r->addRoute('GET', '/estaciones/crear', Route::auth(['EstacionController', 'viewCrear']));
        $r->addRoute('POST', '/estaciones/create-estacion', ['EstacionController', 'crearEstacion']);

        // Puestos
        $r->addRoute('GET', '/puestos', Route::auth(['PuestoController', 'index']));
        $r->addRoute('GET', '/puestos/datatable', Route::auth(['PuestoController', 'datatablePuestos']));

        // Personal

        $r->addRoute('GET', '/usuarios', Route::auth(['UsuarioController', 'index']));
        $r->addRoute('GET', '/usuarios/datatable', Route::auth(['UsuarioController', 'datatableUsuarios']));
        
        
};

?>