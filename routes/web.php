<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

        $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
        $r->addRoute('POST', '/login', ['LoginController', 'login']);
        $r->addRoute('GET', '/logout', Route::auth(['AuthController', 'logout']));

        // Home
        $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
        
        // Grupos
        $r->addGroup('/grupos', function (RouteCollector $r) {
                $r->addRoute('GET',  '',            Route::auth(['GrupoController', 'index']));
                $r->addRoute('GET',  '/datatable',  Route::auth(['GrupoController', 'datatableGrupos']));
                $r->addRoute('POST', '/create',     Route::auth(['GrupoController', 'createGrupo']));
                $r->addRoute('POST', '/update',     Route::auth(['GrupoController', 'updateGrupo']));
                $r->addRoute('POST', '/delete',     Route::auth(['GrupoController', 'deleteGrupo']));
        });

        // Estaciones
        $r->addGroup('/estaciones', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['EstacionController', 'viewIndex']));
                $r->addRoute('GET', '/datatable', Route::auth(['EstacionController', 'datatableEstaciones']));
                $r->addRoute('GET', '/crear', Route::auth(['EstacionController', 'viewCrear']));
                $r->addRoute('POST', '/create-estacion', ['EstacionController', 'crearEstacion']);
        });

        // Puestos
        $r->addGroup('/puestos', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['PuestoController', 'index']));
                $r->addRoute('GET', '/datatable', Route::auth(['PuestoController', 'datatablePuestos']));
        });

        // Usuarios
        $r->addGroup('/usuarios', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['UsuarioController', 'index']));
                $r->addRoute('GET', '/datatable', Route::auth(['UsuarioController', 'datatableUsuarios']));
        });
        
        // Bitacora Aditivo
        $r->addGroup('/bitacora-aditivo', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['AditivoController', 'index']));
                $r->addRoute('GET', '/datatable', Route::auth(['AditivoController', 'datatableAditivo']));
                $r->addRoute('GET', '/reporte', Route::auth(['AditivoController', 'reporte']));
                $r->addRoute('GET', '/inventario', Route::auth(['AditivoController', 'inventario']));
        });

        // Solicitud de Gafetes
        $r->addGroup('/solicitud-gafetes', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['GafetesController', 'index']));
                $r->addRoute('GET', '/datatable', Route::auth(['GafetesController', 'datatableGafetes']));
        });

        // Solicitud de Tarjetas
        $r->addGroup('/solicitud-tarjetas', function (RouteCollector $r) {
                $r->addRoute('GET', '', Route::auth(['TarjetasController', 'index']));
                $r->addRoute('GET', '/datatable', Route::auth(['TarjetasController', 'datatableTarjetas']));
        });
        

};

?>