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


// =========== SISTEMAS APARTADOS ===========
// ========== Configuración Sistemas ==========
$r->addGroup('/configuracion-sistemas', function (RouteCollector $r) {

// ========== CATALOGO DE NODULOS ==========
$r->addRoute('GET', '/catalogo-modulos',  Route::auth(['CatalogoController', 'index']));
$r->addRoute('GET', '/catalogo-modulos/datatable', Route::auth(['CatalogoController', 'datatableCatalogos']));
$r->addRoute('POST', '/catalogo-modulos/create', Route::auth(['CatalogoController', 'createModuloCatalogo']));
$r->addRoute('POST', '/catalogo-modulos/update', Route::auth(['CatalogoController', 'updateModuloCatalogo']));


// ========== CONFIGURACION PUESTO ==========
$r->addRoute('GET', '/configuracion-modulos-puesto',  Route::auth(['EstructuraPuestoController', 'index']));
$r->addRoute('GET', '/configuracion-modulos-puesto/{id:\d+}',  Route::auth(['EstructuraPuestoController', 'indexEstructuraPuesto']));
$r->addRoute('POST', '/configuracion-modulos-puesto/modulos/create', Route::auth(['EstructuraPuestoController', 'createModuloPuesto']));
$r->addRoute('POST', '/configuracion-modulos-puesto/submodulos/create', Route::auth(['EstructuraPuestoController', 'createSubmoduloPuesto']));
$r->addRoute('POST', '/configuracion-modulos-puesto/submodulos/delete', Route::auth(['EstructuraPuestoController', 'deleteSubmoduloPuesto']));
$r->addRoute('GET','/configuracion-modulos-puesto/{puesto:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraPuestoController', 'detallePermisosPuesto']));
$r->addRoute('PUT','/configuracion-modulos-puesto-permiso/{id:\d+}',Route::auth(['EstructuraPuestoController', 'updatePermisosModuloPuesto']));

// ========== CONFIGURACION USUARIOS ==========
$r->addRoute('GET', '/configuracion-modulos-usuario',  Route::auth(['EstructuraUsuarioController', 'index']));
$r->addRoute('GET', '/configuracion-modulos-usuario/{id:\d+}',  Route::auth(['EstructuraUsuarioController', 'indexEstructuraUsuario']));
$r->addRoute('POST', '/configuracion-modulos-usuario/modulos/create', Route::auth(['EstructuraUsuarioController', 'createModuloUsuario']));
$r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/create', Route::auth(['EstructuraUsuarioController', 'createSubmoduloUsuario']));
$r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/delete', Route::auth(['EstructuraUsuarioController', 'deleteSubmoduloUsuario']));
$r->addRoute('GET','/configuracion-modulos-usuario/{usuario:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraUsuarioController', 'detallePermisosUsuario']));
$r->addRoute('PUT','/configuracion-modulos-usuario-permiso/{id:\d+}',Route::auth(['EstructuraUsuarioController', 'updatePermisosModuloUsuario']));


});

// ========== RUTA DINÁMICA UNIVERSAL ==========
$r->addRoute('GET', '/{url:.+}', Route::auth(['ModuloController', 'RutasModulos']));

};

?>