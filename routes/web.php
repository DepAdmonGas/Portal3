<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

    // ---------------- AUTH ----------------
    $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
    $r->addRoute('GET', '/login', Route::guest(['LoginController', 'index']));
    $r->addRoute('POST', '/login', ['LoginController', 'login']);
    // SECURITY: BAJO #33 - Endpoint para refrescar access token
    $r->addRoute('POST', '/refresh-token', Route::auth(['LoginController', 'refreshToken']));
    // SECURITY: BAJO #34 - Logout via POST (más seguro que GET)
    $r->addRoute('POST', '/logout', Route::auth(['AuthController', 'logout']));

    $r->addRoute('GET', '/download', Route::auth(['DownloadController', 'download']));

    // ---------------- HOME ----------------
    $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
    
    // ---------------- SWITCHEO DE LA SESION DE LA ESTACION ----------------
    $r->addRoute('POST','/switch-estacion',Route::auth(['SwitchEstacionController', 'switchSessionEstacion']));


    $r->addRoute('GET', '/menu', Route::auth(['MenuController', 'index']));

    // ---------------- PROCEDIMIENTOS ----------------
    $r->addGroup('/procedimientos', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['ProcedimientosController', 'index']));
        $r->addRoute('GET', '/actividades-tecnicas/datatable', Route::auth(['ProcedimientosController', 'datatableActividadesTec']));
        $r->addRoute('GET', '/visita-estacion/datatable', Route::auth(['ProcedimientosController', 'datatableVisitaES']));
    });

    // ---------------- EMPRESA ----------------
        $r->addGroup('/empresa', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['EmpresaController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['EmpresaController', 'datatableEmpresa']));

    });

    // ---------------- SEGURO ----------------
        $r->addGroup('/seguro', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['SeguroController', 'index']));
        $r->addRoute('GET', '/poliza-seguro/datatable', Route::auth(['SeguroController', 'datatablePolizaSeguro']));
        $r->addRoute('GET', '/poliza-seguro-cobertura/datatable', Route::auth(['SeguroController', 'datatablePolizaSeguroCobertura']));

        $r->addRoute('POST', '/create-poliza-seguro', Route::auth(['SeguroController', 'createPolizaSeguro']));
        $r->addRoute('POST', '/create-cobertura-poliza-seguro', Route::auth(['SeguroController', 'createPolizaSeguroCobertura']));

        $r->addRoute('POST', '/delete-poliza-seguro', Route::auth(['SeguroController', 'deletePolizaSeguro']));
        $r->addRoute('POST', '/delete-poliza-seguro-cobertura', Route::auth(['SeguroController', 'deletePolizaSeguroCobertura']));

    });


    // ---------------- GRUPOS ----------------
    $r->addGroup('/grupos', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['GrupoController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['GrupoController', 'datatableGrupos']));
        $r->addRoute('POST', '/create', Route::auth(['GrupoController', 'createGrupo']));
        $r->addRoute('POST', '/update', Route::auth(['GrupoController', 'updateGrupo']));
        $r->addRoute('POST', '/delete', Route::auth(['GrupoController', 'deleteGrupo']));
    });

    // ---------------- ESTACIONES ----------------
    $r->addGroup('/estaciones', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['EstacionController', 'viewIndex']));
        $r->addRoute('GET', '/datatable', Route::auth(['EstacionController', 'datatableEstaciones']));
        $r->addRoute('GET', '/crear', Route::auth(['EstacionController', 'viewCrear']));
        $r->addRoute('POST', '/create-estacion', ['EstacionController', 'crearEstacion']);
    });

    // ---------------- PUESTOS ----------------
    $r->addGroup('/puestos', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['PuestoController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['PuestoController', 'datatablePuestos']));
        $r->addRoute('GET', '/get-puestos', Route::auth(['PuestoController', 'getPuestos']));
        
    });

    // ---------------- USUARIOS ----------------
    $r->addGroup('/usuarios', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['UsuarioController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['UsuarioController', 'datatableUsuarios']));
    });

    // ---------------- BITACORA ADITIVO ----------------
    $r->addGroup('/bitacora-aditivo', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['AditivoController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['AditivoController', 'datatableAditivo']));
        $r->addRoute('GET', '/reporte', Route::auth(['AditivoController', 'reporte']));

        $r->addRoute('GET', '/inventario', Route::auth(['AditivoController', 'inventario']));

        $r->addRoute('POST', '/create', Route::auth(['AditivoController', 'createAditivo']));
        $r->addRoute('POST', '/delete', Route::auth(['AditivoController', 'deleteAditivo']));
        $r->addRoute('POST', '/update', Route::auth(['AditivoController', 'updateAditivo']));
        $r->addRoute('GET', '/totalInventario', Route::auth(['AditivoController', 'totalInventario']));

        //Inventario
        $r->addRoute('GET', '/datatable-inventario', Route::auth(['AditivoController', 'datatableInventario']));
        $r->addRoute('POST', '/create-inventario', Route::auth(['AditivoController', 'createInventario']));
        //Reporte
        $r->addRoute('GET', '/datatable-reporte', Route::auth(['AditivoController', 'datatableReporte']));
        $r->addRoute('POST', '/create-reporte', Route::auth(['AditivoController', 'createReporte']));
        $r->addRoute('POST', '/delete-reporte', Route::auth(['AditivoController', 'deleteReporte']));
                    
        
    });

    // ---------------- GAFETES ----------------
    $r->addGroup('/solicitud-gafetes', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['GafetesController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['GafetesController', 'datatableGafetes']));
        $r->addRoute('POST', '/create-reporte', Route::auth(['GafetesController', 'createReporte']));
        $r->addRoute('POST', '/delete-reporte', Route::auth(['GafetesController', 'deleteReporte']));

        $r->addRoute('GET','/formulario/{idEstacion}/{noReporte}',Route::auth(['GafetesController', 'formularioReporte']));
        $r->addRoute('GET','/datatable-formulario/{idEstacion}/{noReporte}',Route::auth(['GafetesController', 'datatableGafetesFormulario']));
        $r->addRoute('POST', '/create-reporte-formulario', Route::auth(['GafetesController', 'createReporteFormulario']));
        $r->addRoute('POST', '/delete-reporte-registro-formulario', Route::auth(['GafetesController', 'deleteReporteFormulario']));


        $r->addRoute('GET','/detalle/{idEstacion}/{noReporte}',Route::auth(['GafetesController', 'formularioSeguimiento']));
        $r->addRoute('GET','/datatable-detalle/{idEstacion}/{noReporte}',Route::auth(['GafetesController', 'datatableGafetesFormulario']));
        $r->addRoute('GET','/seguimiento/timeline/{idEstacion}/{noReporte}',Route::auth(['GafetesController', 'timelineSeguimiento']));
        $r->addRoute('POST', '/seguimiento/update', Route::auth(['GafetesController', 'updateSeguimientoGafetes']));
        
    });

    // ---------------- TARJETAS ----------------
    $r->addGroup('/solicitud-tarjetas', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['TarjetasController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['TarjetasController', 'datatableTarjetas']));
        $r->addRoute('POST', '/create-reporte', Route::auth(['TarjetasController', 'createReporte']));
        $r->addRoute('POST', '/delete-reporte', Route::auth(['TarjetasController', 'deleteReporte']));

        $r->addRoute('GET','/formulario/{idEstacion}/{noSolicitud}',Route::auth(['TarjetasController', 'formularioReporte']));
        $r->addRoute('GET','/datatable-formulario/{idEstacion}/{noSolicitud}',Route::auth(['TarjetasController', 'datatableTarjetasFormulario']));
        $r->addRoute('POST', '/create-reporte-formulario', Route::auth(['TarjetasController', 'createReporteFormulario']));
        $r->addRoute('POST', '/update-reporte-formulario', Route::auth(['TarjetasController', 'updateReporteFormulario']));
        $r->addRoute('POST', '/delete-reporte-formulario', Route::auth(['TarjetasController', 'deleteReporteFormulario']));

        $r->addRoute('GET','/seguimiento/timeline/{idEstacion}/{noSeguimiento}',Route::auth(['TarjetasController', 'timelineSeguimiento']));
        $r->addRoute('GET','/archivo/{idEstacion}/{noSeguimiento}',Route::auth(['TarjetasController', 'obtenerArchivoTarjeta']));
        $r->addRoute('POST', '/seguimiento/update', Route::auth(['TarjetasController', 'updateSeguimientoTarjetas']));

        $r->addRoute('GET','/detalle/{idEstacion}/{noSolicitud}',Route::auth(['TarjetasController', 'formularioSeguimiento']));
        $r->addRoute('POST', '/comentarios/update', Route::auth(['TarjetasController', 'updateComentarioTarjetas']));
    });


    // ---------------- CONFIGURACION ----------------
    $r->addGroup('/configuracion', function (RouteCollector $r) {
    $r->addRoute('GET','',Route::auth(['ConfiguracionController', 'index']));

    //----- Modulos 
    $r->addRoute('GET','/modulos',Route::auth(['ConfiguracionController', 'modulosIndex']));
    $r->addRoute('GET', '/datatable-modulos', Route::auth(['ConfiguracionController', 'datatableModulos']));
    $r->addRoute('POST', '/create-modulos', Route::auth(['ConfiguracionController', 'createModulos']));
    $r->addRoute('POST', '/update-modulos', Route::auth(['ConfiguracionController', 'updateModulos']));
    $r->addRoute('POST', '/delete-modulos', Route::auth(['ConfiguracionController', 'deleteModulos']));

    //----- Modulos puestos (Portal)
    $r->addRoute('GET','/modulos-puestos',Route::auth(['ConfiguracionController', 'modulosPuestosIndex']));
    $r->addRoute('GET', '/modulos-puestos/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosPuestosFormulario']));
    $r->addRoute('GET', '/datatable-modulos-puestos/idPuesto/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosPuestos']));
    $r->addRoute('POST', '/modulos-puestos/create', Route::auth(['ConfiguracionController', 'createModulosPuestos']));
    $r->addRoute('POST', '/modulos-puestos/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosPuestos']));
    $r->addRoute('POST', '/modulos-puestos/delete', Route::auth(['ConfiguracionController', 'deleteModulosPuestos']));
    $r->addRoute('GET', '/modulos-puestos/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosPuestos']));
    $r->addRoute('GET','/modulos-puestos/modulo/{id:\d+}/{idActual:\d+}',Route::auth(['ConfiguracionController', 'getModulosPuestos']));
    $r->addRoute('GET', '/modulos-puestos/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosPuestosDetalle']));
    
    //----- Modulos usuarios (Portal)
    $r->addRoute('GET','/modulos-usuarios',Route::auth(['ConfiguracionController', 'modulosUsuariosIndex']));
    $r->addRoute('GET', '/modulos-usuarios/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosUsuariosFormulario']));
    $r->addRoute('GET', '/datatable-modulos-usuarios/idUsuario/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosUsuarios']));
    $r->addRoute('POST', '/modulos-usuarios/create', Route::auth(['ConfiguracionController', 'createModulosUsuarios']));
    $r->addRoute('POST', '/modulos-usuarios/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosUsuarios']));
    $r->addRoute('POST', '/modulos-usuarios/delete', Route::auth(['ConfiguracionController', 'deleteModulosUsuarios']));
    $r->addRoute('GET', '/modulos-usuarios/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosUsuarios']));
    $r->addRoute('GET','/modulos-usuarios/modulo/{id:\d+}/{idActual:\d+}',Route::auth(['ConfiguracionController', 'getModulosUsuarios']));
    $r->addRoute('GET', '/modulos-usuarios/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosUsuariosDetalle']));

    //----- Modulos (Dpto. Operativo)
    $r->addRoute('GET','/modulos-operativo',Route::auth(['ConfiguracionController', 'modulosDptoOperativoIndex']));
    $r->addRoute('GET', '/datatable-modulos-operativo', Route::auth(['ConfiguracionController', 'datatableModulosDptoOperativo']));
    $r->addRoute('POST', '/create-modulos-operativo', Route::auth(['ConfiguracionController', 'createModulosDptoOperativo']));
    $r->addRoute('POST', '/update-modulos-operativo', Route::auth(['ConfiguracionController', 'updateModulosDptoOperativo']));
    $r->addRoute('POST', '/delete-modulos-operativo', Route::auth(['ConfiguracionController', 'deleteModulosDptoOperativo']));
    
    //----- Submodulos (Dpto. Operativo)  
    $r->addRoute('GET', '/modulos-operativo/{id:\d+}', Route::auth(['ConfiguracionController', 'submodulosDptoOperativoIndex']));
    $r->addRoute('GET', '/datatable-submodulos-operativo/idModulo/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableSubmodulosDptoOperativo']));
    $r->addRoute('POST', '/create-submodulos-operativo', Route::auth(['ConfiguracionController', 'createSubmodulosDptoOperativo']));
    $r->addRoute('POST', '/update-submodulos-operativo', Route::auth(['ConfiguracionController', 'updateSubmodulosDptoOperativo']));
    $r->addRoute('POST', '/delete-submodulos-operativo', Route::auth(['ConfiguracionController', 'deleteSubmodulosDptoOperativo']));

    //----- Modulos puestos (Dpto. Operativo)
    $r->addRoute('GET','/modulos-operativo-puestos',Route::auth(['ConfiguracionController', 'modulosPuestosDptoOperativoIndex']));
    $r->addRoute('GET', '/modulos-operativo-puestos/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosPuestosDptoOperativoFormulario']));
    $r->addRoute('GET', '/datatable-modulos-operativo-puestos/idPuesto/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosPuestosDptoOperativo']));
    $r->addRoute('GET', '/modulos-operativo-puestos/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestos']));
    $r->addRoute('GET','/modulos-operativo-puestos/modulo/{id:\d+}/{idActual:\d+}',Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestos']));
    $r->addRoute('GET','/submodulos-operativo-puestos/modulo/{idModulo:\d+}/puesto/{idPuesto:\d+}',Route::auth(['ConfiguracionController', 'getSubmodulosDptoOperativoPuestos']));
    $r->addRoute('GET', '/modulos-operativo-puestos/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestosDetalle']));
    $r->addRoute('POST', '/modulos-operativo-puestos/create', Route::auth(['ConfiguracionController', 'createModulosDptoOperativoPuestos']));
    $r->addRoute('POST', '/modulos-operativo-puestos/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosDptoOperativoPuestos']));
    $r->addRoute('POST', '/modulos-operativo-puestos/delete', Route::auth(['ConfiguracionController', 'deleteModulosDptoOperativoPuestos']));

    //----- Modulos usuario (Dpto. Operativo)
    $r->addRoute('GET','/modulos-operativo-usuarios',Route::auth(['ConfiguracionController', 'modulosUsuariosDptoOperativoIndex']));
    $r->addRoute('GET', '/modulos-operativo-usuarios/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosUsuariosDptoOperativoFormulario']));
    $r->addRoute('GET', '/datatable-modulos-operativo-usuarios/idUsuario/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosUsuariosDptoOperativo']));
    $r->addRoute('GET', '/modulos-operativo-usuarios/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuarios']));
    $r->addRoute('GET','/modulos-operativo-usuarios/modulo/{id:\d+}/{idActual:\d+}',Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuarios']));
    $r->addRoute('GET','/submodulos-operativo-usuarios/modulo/{idModulo:\d+}/usuario/{idUsuario:\d+}',Route::auth(['ConfiguracionController', 'getSubmodulosDptoOperativoUsuarios']));
    $r->addRoute('GET', '/modulos-operativo-usuarios/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuariosDetalle']));
    $r->addRoute('POST', '/modulos-operativo-usuarios/create', Route::auth(['ConfiguracionController', 'createModulosDptoOperativoUsuarios']));
    $r->addRoute('POST', '/modulos-operativo-usuarios/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosDptoOperativoUsuarios']));
    $r->addRoute('POST', '/modulos-operativo-usuarios/delete', Route::auth(['ConfiguracionController', 'deleteModulosDptoOperativUsuarios']));

    });


    // ---------------- DEPARTAMENTO DE SISTEMAS ----------------
    $r->addGroup('/departamento-sistemas', function (RouteCollector $r) {

   
    });


    
    // ---------------- DEPARTAMENTO OPERATIVO ----------------
    $r->addGroup('/departamento-operativo', function (RouteCollector $r) {
    $r->addRoute('GET', '', Route::auth(['DptoOperativoController', 'index']));

    //----- 1. Corporativo
    $r->addRoute('GET','/corporativo',Route::auth(['DptoOperativoController', 'corporativoIndex']));

    //----- Corte Diario
    $r->addRoute('GET','/corporativo/corte-diario/{idYear:\d+}/{idMes:\d+}',Route::auth(['CorporativoController', 'corteDiarioIndex']));
    $r->addRoute('GET','/corporativo/corte-diario-datatable/{idYear:\d+}/{idMes:\d+}',Route::auth(['CorporativoController', 'corteDiarioDatatable']));


    //----- 2. Recursos Humanos
    $r->addRoute('GET','/recursos-humanos',Route::auth(['DptoOperativoController', 'recursosHumanosIndex']));

    //----- 3. Importacion
    $r->addRoute('GET','/importacion',Route::auth(['DptoOperativoController', 'importacionIndex']));

    //----- 4. Almacen
    $r->addRoute('GET','/almacen',Route::auth(['DptoOperativoController', 'almacenIndex']));

    //----- 5. Comercializadora
    $r->addRoute('GET','/comercializadora',Route::auth(['DptoOperativoController', 'comercializadoraIndex']));
    });


    // ---------------- CONFIGURACION SISTEMAS ----------------
    $r->addGroup('/configuracion-sistemas', function (RouteCollector $r) {

        // Catalogo modulos
        $r->addRoute('GET', '/catalogo-modulos', Route::auth(['CatalogoController', 'index']));
        $r->addRoute('GET', '/catalogo-modulos/datatable', Route::auth(['CatalogoController', 'datatableCatalogos']));
        $r->addRoute('POST', '/catalogo-modulos/create', Route::auth(['CatalogoController', 'createModuloCatalogo']));
        $r->addRoute('POST', '/catalogo-modulos/update', Route::auth(['CatalogoController', 'updateModuloCatalogo']));
        $r->addRoute('POST', '/catalogo-modulos/delete', Route::auth(['CatalogoController', 'deleteModuloCatalogo']));

        // Puestos
        $r->addRoute('GET', '/configuracion-modulos-puesto', Route::auth(['EstructuraPuestoController', 'index']));
        $r->addRoute('GET', '/configuracion-modulos-puesto/{id:\d+}', Route::auth(['EstructuraPuestoController', 'indexEstructuraPuesto']));
        $r->addRoute('POST', '/configuracion-modulos-puesto/modulos/create', Route::auth(['EstructuraPuestoController', 'createModuloPuesto']));
        $r->addRoute('POST', '/configuracion-modulos-puesto/submodulos/create', Route::auth(['EstructuraPuestoController', 'createSubmoduloPuesto']));
        $r->addRoute('POST', '/configuracion-modulos-puesto/submodulos/delete', Route::auth(['EstructuraPuestoController', 'deleteSubmoduloPuesto']));
        $r->addRoute('GET','/configuracion-modulos-puesto/{puesto:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraPuestoController', 'detallePermisosPuesto']));
        $r->addRoute('PUT','/configuracion-modulos-puesto-permiso/{id:\d+}', Route::auth(['EstructuraPuestoController', 'updatePermisosModuloPuesto']));

        // Usuarios
        $r->addRoute('GET', '/configuracion-modulos-usuario', Route::auth(['EstructuraUsuarioController', 'index']));
        $r->addRoute('GET', '/configuracion-modulos-usuario/{id:\d+}', Route::auth(['EstructuraUsuarioController', 'indexEstructuraUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/modulos/create', Route::auth(['EstructuraUsuarioController', 'createModuloUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/create', Route::auth(['EstructuraUsuarioController', 'createSubmoduloUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/delete', Route::auth(['EstructuraUsuarioController', 'deleteSubmoduloUsuario']));
        $r->addRoute('GET','/configuracion-modulos-usuario/{usuario:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraUsuarioController', 'detallePermisosUsuario']));
        $r->addRoute('PUT','/configuracion-modulos-usuario-permiso/{id:\d+}', Route::auth(['EstructuraUsuarioController', 'updatePermisosModuloUsuario']));
    });

    //----------------- Lista de asistencia ------------

    $r->addRoute('GET', '/datatable-lista-asistencia/elemento/{idsasisopa:\d+}', Route::auth(['ListaAsistenciaController', 'datatableListaAsistencia']));
    $r->addRoute('GET', '/table-lista-asistencia-firma/id/{id:\d+}', Route::auth(['ListaAsistenciaController', 'datatableFirmaListaAsistencia']));
    $r->addRoute('POST', '/lista-asistencia/delete', Route::auth(['ListaAsistenciaController', 'deleteListaAsistencia']));
    $r->addRoute('POST', '/lista-asistencia/create', Route::auth(['ListaAsistenciaController', 'createListaAsistencia']));
    $r->addRoute('POST', '/lista-asistencia/update', Route::auth(['ListaAsistenciaController', 'updateListaAsistencia']));
    $r->addRoute('GET', '/lista-asistencia/pdf/{id:\d+}', Route::auth(['ListaAsistenciaController', 'pdfListaAsistencia']));
    $r->addRoute('GET', '/lista-asistencia/{id:\d+}', Route::auth(['ListaAsistenciaController', 'indexListaAsistencia']));
    $r->addRoute('POST', '/lista-asistencia-firma/create', Route::auth(['ListaAsistenciaController', 'createFirmaListaAsistencia']));
    $r->addRoute('POST', '/lista-asistencia-firma/delete', Route::auth(['ListaAsistenciaController', 'deleteFirmaListaAsistencia']));

    $r->addRoute('GET', '/cursos/descargar/{id:\d+}', Route::auth(['CursosController', 'descargar']));
    $r->addRoute('GET', '/cursos/descargar/{year:\d+}/{idModulo:\d+}', Route::auth(['CursosController', 'descargarAll']));
    
    // ---------------- SASISOPA ----------------
    $r->addGroup('/sasisopa', function (RouteCollector $r) {

        $r->addRoute('GET', '', Route::auth(['SasisopaController', 'index']));

        // Elemento 1
        $r->addRoute('GET', '/politica', Route::auth(['PoliticaController', 'politica']));

        $r->addRoute('GET', '/politica/datatable-lista-comprobacion', Route::auth(['PoliticaController', 'datatableListaComprobacion']));
        $r->addRoute('POST', '/politica/update', Route::auth(['PoliticaController', 'updatePolitica']));
        $r->addRoute('GET', '/politica/pdf', Route::auth(['PoliticaController', 'descargarPolitica']));
        $r->addRoute('POST', '/politica/lista-comprobacion/create', Route::auth(['PoliticaController', 'createListaComprobacion']));
        $r->addRoute('POST', '/politica/lista-comprobacion/update', Route::auth(['PoliticaController', 'updateListaComprobacion']));
        $r->addRoute('POST', '/politica/lista-comprobacion/delete', Route::auth(['PoliticaController', 'deleteListaComprobacion']));
        $r->addRoute('GET', '/politica/lista-comprobacion/{id:\d+}', Route::auth(['PoliticaController', 'getListaComprobacion']));
        $r->addRoute('GET', '/politica/lista-comprobacion/pdf/{id:\d+}', Route::auth(['PoliticaController', 'descargarListaComprobacion']));


        // Elemento 2
        $r->addRoute('GET', '/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', Route::auth(['SasisopaController', 'identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales']));
        $r->addRoute('GET', '/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/aspectos-ambientales-pdf', Route::auth(['SasisopaController', 'pdfAspectosAmbientales']));
        $r->addRoute('GET', '/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/riesgos-peligros-pdf', Route::auth(['SasisopaController', 'pdfRiesgosPeligros']));
        $r->addRoute('GET', '/datatable-lista-analisis-riesgo', Route::auth(['SasisopaController', 'datatableListaAnalisisRiesgo']));
        $r->addRoute('GET', '/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/analisis-riesgo-anexos/{id:\d+}', Route::auth(['SasisopaController', 'anexosAnalisisRiesgo']));


        // Elemento 3
        $r->addRoute('GET', '/requisitos-legales', Route::auth(['RequisitosLegalesController', 'requisitosLegales']));
        $r->addRoute('GET', '/requisitos-legales/configuracion', Route::auth(['RequisitosLegalesController', 'requisitosLegalesConfiguracion']));
        $r->addRoute('GET', '/requisitos-legales/calendario-pdf', Route::auth(['RequisitosLegalesController', 'calendarioRequisitosLegales']));

        $r->addRoute('GET', '/requisitos-legales/datatable-configuracion', Route::auth(['RequisitosLegalesController', 'datatableConfiguracion']));
        $r->addRoute('POST', '/requisitos-legales/delete-configuracion', Route::auth(['RequisitosLegalesController', 'deleteConfiguracion']));
        
        
        $r->addRoute('GET', '/requisitos-legales/dependencias', Route::auth(['RequisitosLegalesController', 'getDependencias']));
        
        $r->addRoute('POST', '/requisitos-legales/create-configuracion', Route::auth(['RequisitosLegalesController', 'createConfiguracion']));

        $r->addRoute('GET', '/requisitos-legales/{nGobierno:[a-zA-Z0-9\-]+}', Route::auth(['RequisitosLegalesController', 'requisitosLegalesDetalle']));
        $r->addRoute('GET', '/requisitos-legales/datatable-detalle/{nGobierno:[a-zA-Z0-9\-]+}', Route::auth(['RequisitosLegalesController', 'datatableDetalle']));
        $r->addRoute('POST', '/requisitos-legales/delete-detalle', Route::auth(['RequisitosLegalesController', 'deleteDetalle']));

        $r->addRoute('GET', '/requisitos-legales/permisos/{nGobierno:[a-zA-Z0-9\-]+}/{sgm:\d+}', Route::auth(['RequisitosLegalesController', 'getPermisos']));
        $r->addRoute('GET','/requisitos-legales/permisos/{nGobierno:[a-zA-Z0-9\-]+}/{sgm:\d+}/{idActual:\d+}', Route::auth(['RequisitosLegalesController', 'getPermisos']));
        $r->addRoute('POST', '/requisitos-legales/create-permiso-detalle', Route::auth(['RequisitosLegalesController', 'createPermisoDetalle']));
        $r->addRoute('POST', '/requisitos-legales/update-permiso-detalle/{id:\d+}', Route::auth(['RequisitosLegalesController', 'updatePermisoDetalle']));
        
        $r->addRoute('GET', '/requisitos-legales/detalle/{id:\d+}', Route::auth(['RequisitosLegalesController', 'getDetalle']));
        $r->addRoute('GET', '/requisitos-legales/historial/{id:\d+}', Route::auth(['RequisitosLegalesController', 'getHistorialDetalle']));
        $r->addRoute('POST', '/requisitos-legales/historial/create/{id:\d+}', Route::auth(['RequisitosLegalesController', 'createHistorialDetalle']));
        $r->addRoute('POST', '/requisitos-legales/historial/update/{id:\d+}', Route::auth(['RequisitosLegalesController', 'updateHistorialDetalle']));
        $r->addRoute('POST', '/requisitos-legales/historial/delete', Route::auth(['RequisitosLegalesController', 'deleteHistorialDetalle']));


        // Elemento 4
        $r->addRoute('GET', '/objetivos-metas-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'index']));
        
        $r->addRoute('GET', '/datatable-seguimiento-objetivosmetas', Route::auth(['objetivosMetasIndicadoresController', 'datatableSeguimientoObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/create-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'createObjetivosMetas']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/get-objetivos-metas/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'getObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/update-objetivos-metas/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'updateObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/delete-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'deleteObjetivosMetas']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/pdf-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'pdfObjetivosMetas']));
        
        $r->addRoute('GET', '/datatable-seguimiento-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'datatableSeguimientoIndicadores']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/create-reporte-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'createReporteIndicadores']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/update-reporte-indicadores/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'updateReporteIndicadores']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/pdf-reporte-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'pdfReporteIndicadores']));
        
        $r->addRoute('GET', '/objetivos-metas-indicadores/get-reporte-indicadores/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'getReporteIndicadores']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/delete-reporte-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'deleteReporteIndicadores']));
        
        $r->addRoute('GET', '/objetivos-metas-indicadores/capacitacion-personal', Route::auth(['objetivosMetasIndicadoresController', 'capacitacionPersonal']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/resumen-capacitacion-personal', Route::auth(['objetivosMetasIndicadoresController', 'resumenCapacitacionPermosal']));
        
        $r->addRoute('GET', '/objetivos-metas-indicadores/experiencia-cliente', Route::auth(['objetivosMetasIndicadoresController', 'ExperienciaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/datatable-experiencia-cliente', Route::auth(['objetivosMetasIndicadoresController', 'datatableExperienciaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/chart-experiencia-cliente', Route::auth(['objetivosMetasIndicadoresController', 'chartExperienciaCliente']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/create-experiencia-cliente', Route::auth(['objetivosMetasIndicadoresController', 'createExperienciaCliente']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/delete-experiencia-cliente', Route::auth(['objetivosMetasIndicadoresController', 'deleteExperienciaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/editar-experiencia-cliente/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'editarExperienciaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/detalle-experiencia-cliente/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'detalleExperienciaCliente']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/agregar-encuesta-cliente', Route::auth(['objetivosMetasIndicadoresController', 'agregarEncuestaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/lista-encuesta-cliente', Route::auth(['objetivosMetasIndicadoresController', 'getListaClientes']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/detalle-encuesta-cliente', Route::auth(['objetivosMetasIndicadoresController', 'detalleEncuestaCliente']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/finalizar-encuesta', Route::auth(['objetivosMetasIndicadoresController', 'finalizarEncuesta']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/chart-experiencia-cliente-preguntas', Route::auth(['objetivosMetasIndicadoresController', 'chartExperienciaClientePreguntas']));

        $r->addRoute('GET', '/objetivos-metas-indicadores/indicador-ventas', Route::auth(['objetivosMetasIndicadoresController', 'indicadorVentas']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/get-indicador-ventas', Route::auth(['objetivosMetasIndicadoresController', 'getIndicadorVentas']));
        
        // Elemento 5
        $r->addRoute('GET', '/funciones-responsabilidades-autoridad', Route::auth(['SasisopaController', 'funcionesResponsabilidadesAutoridad']));
        $r->addRoute('GET', '/funciones-responsabilidades-autoridad/datatable-lista-representante-tecnico', Route::auth(['SasisopaController', 'datatableListaRepresentanteTecnico']));
        $r->addRoute('POST', '/funciones-responsabilidades-autoridad/create-representante-tecnico', Route::auth(['SasisopaController', 'createRepresentanteTecnico']));
        $r->addRoute('POST', '/funciones-responsabilidades-autoridad/delete-representante-tecnico', Route::auth(['SasisopaController', 'deleteRepresentanteTecnico']));

        
        // Elemento 6
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento', Route::auth(['CompetenciaPersonalCapacitacionController', 'index']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/perfiles-puesto-trabajo', Route::auth(['CompetenciaPersonalCapacitacionController', 'perfilesPuestoTrabajo']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/perfiles-personal', Route::auth(['PerfilPersonalController', 'perfilesPersonal']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/datatable-perfiles-personal', Route::auth(['PerfilPersonalController', 'datatablePerfilesPersonal']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/ficha-personal/{id:\d+}', Route::auth(['PerfilPersonalController', 'fichaPersonal']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf', Route::auth(['PerfilPersonalController', 'fichaPersonalPdf']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf/{id:\d+}', Route::auth(['PerfilPersonalController', 'fichaPersonalIndividualPdf']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/update-ficha-personal', Route::auth(['PerfilPersonalController', 'updateFichaPersonal']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-falimiares/{id:\d+}', Route::auth(['PerfilPersonalController', 'getFamiliares']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-dato-familiar', Route::auth(['PerfilPersonalController', 'deleteDatoFamiliar']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-dato-familiar', Route::auth(['PerfilPersonalController', 'createDatoFamiliar']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-formacion-academica/{id:\d+}', Route::auth(['PerfilPersonalController', 'getFormacionAcademica']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-formacion', Route::auth(['PerfilPersonalController', 'createFormacion']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-formacion', Route::auth(['PerfilPersonalController', 'deleteFormacion']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-experiencia/{id:\d+}', Route::auth(['PerfilPersonalController', 'getExperiencia']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-experiencia', Route::auth(['PerfilPersonalController', 'createExperiencia']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-experiencia', Route::auth(['PerfilPersonalController', 'deleteExperiencia']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-experiencia-empresa/{id:\d+}', Route::auth(['PerfilPersonalController', 'getExperienciaEmpresa']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-experiencia-empresa', Route::auth(['PerfilPersonalController', 'createExperienciaEmpresa']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-experiencia-empresa', Route::auth(['PerfilPersonalController', 'deleteExperienciaEmpresa']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/update-experiencia-empresa', Route::auth(['PerfilPersonalController', 'updateExperienciaEmpresa']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/update-firma', Route::auth(['PerfilPersonalController', 'updateFirma']));

        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/capacitacion-interna', Route::auth(['CapacitacionInternaController', 'index']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/datatable-capacitacion-interna/{idTema:\d+}', Route::auth(['CapacitacionInternaController', 'datatableCapacitacionInterna']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/capacitacion-interna/{idModulo:\d+}/{idTema:\d+}', Route::auth(['CapacitacionInternaController', 'capacitacionInterna']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-programacion-interna', Route::auth(['CapacitacionInternaController', 'createProgramacionInterna']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-cursos-internos/{idUsuario:\d+}/{idTema:\d+}', Route::auth(['CapacitacionInternaController', 'getCursosInternos']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-curso-interno', Route::auth(['CapacitacionInternaController', 'deleteCursosInterno']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/buscar-capacitacion-interna/{year:\d+}', Route::auth(['CapacitacionInternaController', 'buscarCapacitacionInterna']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/descargar-capacitacion-interna/{year:\d+}/{idModulo:\d+}', Route::auth(['CapacitacionInternaController', 'descargarCapacitacionInterna']));

        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/capacitacion-externa', Route::auth(['CapacitacionExternaController', 'index']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/datatable-capacitacion-externa', Route::auth(['CapacitacionExternaController', 'datatableCapacitacionExterna']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-capacitacion-externa', Route::auth(['CapacitacionExternaController', 'createCapacitacionExterna']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/update-capacitacion-externa/{id:\d+}', Route::auth(['CapacitacionExternaController', 'updateCapacitacionExterna']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-capacitacion-externa', Route::auth(['CapacitacionExternaController', 'deleteCapacitacionExterna']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/get-personal/{id:\d+}', Route::auth(['CapacitacionExternaController', 'getPersonal']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/create-personal', Route::auth(['CapacitacionExternaController', 'createPersonal']));
        $r->addRoute('POST', '/competencia-personal-capacitacion-entrenamiento/delete-personal', Route::auth(['CapacitacionExternaController', 'deletePersonal']));
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa/{id:\d+}', Route::auth(['CapacitacionExternaController', 'pdfCapacitacionExterna']));

        // Elemento 7
        $r->addRoute('GET', '/comunicacion-participacion-consulta', Route::auth(['ComunicacionParticipacionConsultaController', 'index']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/datatable-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'datatableRegistroComunicacion']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/create-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'createRegistroComunicacion']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/delete-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'deleteRegistroComunicacion']));
        $r->addRoute('POST','/comunicacion-participacion-consulta/update-registro-comunicacion',Route::auth(['ComunicacionParticipacionConsultaController','updateRegistroComunicacion']));

        $r->addRoute('POST','/comunicacion-participacion-consulta/create-evidencia',Route::auth(['ComunicacionParticipacionConsultaController','createEvidencia']));
        $r->addRoute('GET','/comunicacion-participacion-consulta/get-evidencias/{id}',Route::auth(['ComunicacionParticipacionConsultaController','getEvidencias']));
        $r->addRoute('POST','/comunicacion-participacion-consulta/delete-evidencia',Route::auth(['ComunicacionParticipacionConsultaController','deleteEvidencia']));
        $r->addRoute('GET','/comunicacion-participacion-consulta/get-detalle-comunicacion/{id}',Route::auth(['ComunicacionParticipacionConsultaController','getDetalleComunicacion']));
        $r->addRoute('GET','/comunicacion-participacion-consulta/pdf-registro-comunicacion',Route::auth(['ComunicacionParticipacionConsultaController','pdfRegistroComunicacion']));

        $r->addRoute('GET', '/comunicacion-participacion-consulta/datatable-quejas-sugerencias', Route::auth(['ComunicacionParticipacionConsultaController', 'datatableQuejasSugerencias']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/create-quejas-sugerencias', Route::auth(['ComunicacionParticipacionConsultaController', 'createQuejaSugerencia']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/delete-quejas-sugerencias', Route::auth(['ComunicacionParticipacionConsultaController', 'deleteQuejaSugerencia']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/pdf-quejas-sugerencias/{id:\d+}', Route::auth(['ComunicacionParticipacionConsultaController', 'pdfQuejaSugerencia']));


        // Elemento 8
        $r->addRoute('GET', '/control-documentos-registros', Route::auth(['DocumentosRegistrosController', 'index']));
        $r->addRoute('GET', '/control-documentos-registros/requisitos-legales', Route::auth(['DocumentosRegistrosController', 'requisitosLegales']));
        $r->addRoute('GET', '/control-documentos-registros/pdf-requisitos-legales', Route::auth(['DocumentosRegistrosController', 'pdfRequisitosLegales']));
        $r->addRoute('GET', '/control-documentos-registros/sistema-administracion', Route::auth(['DocumentosRegistrosController', 'sistemaAdministracion']));
        $r->addRoute('GET', '/control-documentos-registros/pdf-sistema-administracion', Route::auth(['DocumentosRegistrosController', 'pdfSistemaAdministracion']));


        // Elemento 9
        $r->addRoute('GET', '/mejores-practicas-estandares', Route::auth(['MejoresPracticasEstandaresController', 'index']));
        $r->addRoute('GET', '/mejores-practicas-estandares/datatable-diseno-construccion', Route::auth(['MejoresPracticasEstandaresController', 'datatableDisenoConstruccion']));
        $r->addRoute('POST', '/mejores-practicas-estandares/create-diseno-construccion', Route::auth(['MejoresPracticasEstandaresController', 'createDisenoConstruccion']));
        $r->addRoute('POST', '/mejores-practicas-estandares/delete-diseno-construccion', Route::auth(['MejoresPracticasEstandaresController', 'deleteDisenoConstruccion']));
        $r->addRoute('GET', '/mejores-practicas-estandares/pdf-diseno-construccion', Route::auth(['MejoresPracticasEstandaresController', 'pdfDisenoConstruccion']));

        $r->addRoute('GET', '/mejores-practicas-estandares/datatable-operacion-mantenimiento', Route::auth(['MejoresPracticasEstandaresController', 'datatableOperacionMantenimiento']));
        $r->addRoute('POST', '/mejores-practicas-estandares/create-operacion-mantenimiento', Route::auth(['MejoresPracticasEstandaresController', 'createOperacionMantenimiento']));
        $r->addRoute('POST', '/mejores-practicas-estandares/delete-operacion-mantenimiento', Route::auth(['MejoresPracticasEstandaresController', 'deleteOperacionMantenimiento']));
        $r->addRoute('GET', '/mejores-practicas-estandares/pdf-operacion-mantenimiento', Route::auth(['MejoresPracticasEstandaresController', 'pdfOperacionMantenimiento']));

        // Elemento 10
        $r->addRoute('GET', '/control-actividades-procesos', Route::auth(['ControlActividadesProcesosController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/programa-anual-mantenimiento', Route::auth(['ControlActividadesProcesosController', 'programaAnual']));
        $r->addRoute('POST', '/control-actividades-procesos/create-programa-mantenimiento', Route::auth(['ControlActividadesProcesosController', 'createProgramaAnualMantenimiento']));

        $r->addRoute('GET', '/control-actividades-procesos/programa-anual-mantenimiento/{id:\d+}', Route::auth(['ControlActividadesProcesosController', 'detalleProgramaAnual']));
        $r->addRoute('GET', '/control-actividades-procesos/datatable-programa-anual-mantenimiento/{id:\d+}', Route::auth(['ControlActividadesProcesosController', 'datatableProgramaMantenimiento']));
        $r->addRoute('GET', '/control-actividades-procesos/equipos-programa-anual-mantenimiento/{id:\d+}', Route::auth(['ControlActividadesProcesosController', 'equipoProgramaMantenimiento']));
        $r->addRoute('POST', '/control-actividades-procesos/create-programa-anual-mantenimiento', Route::auth(['ControlActividadesProcesosController', 'createProgramaMantenimiento']));
        $r->addRoute('POST', '/control-actividades-procesos/delete-programa-anual-mantenimiento', Route::auth(['ControlActividadesProcesosController', 'deleteProgramaMantenimiento']));
        $r->addRoute('GET', '/control-actividades-procesos/get-programa-anual-mantenimiento/{id:\d+}', Route::auth(['ControlActividadesProcesosController', 'getProgramaMantenimiento']));
        $r->addRoute('POST', '/control-actividades-procesos/update-programa-anual-mantenimiento', Route::auth(['ControlActividadesProcesosController', 'updateProgramaMantenimiento']));
        $r->addRoute('GET', '/control-actividades-procesos/pdf-programa-anual-mantenimiento/{id:\d+}', Route::auth(['ControlActividadesProcesosController', 'pdfProgramaMantenimiento']));

        // Elemento 11
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento', Route::auth(['IntegridadMecanicaController', 'index']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/datatable-equipo-critico', Route::auth(['IntegridadMecanicaController', 'datatableEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/delete-equipo-critico', Route::auth(['IntegridadMecanicaController', 'deleteEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/baja-equipo-critico', Route::auth(['IntegridadMecanicaController', 'bajaEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/create-equipo-critico', Route::auth(['IntegridadMecanicaController', 'createEquipoCritico']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/pdf-equipo-critico', Route::auth(['IntegridadMecanicaController', 'pdfEquipoCritico']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/bitacoras', Route::auth(['IntegridadMecanicaController', 'bitacoras']));

        // Elemento 12
        $r->addRoute('GET', '/seguridad-contratistas', Route::auth(['SasisopaController', 'seguridadContratistas']));
        // Elemento 13
        $r->addRoute('GET', '/preparacion-emergencias', Route::auth(['SasisopaController', 'preparacionEmergencias']));
        // Elemento 14
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion', Route::auth(['SasisopaController', 'monitoreoVerificacionEvaluacion']));
        // Elemento 15
        $r->addRoute('GET', '/auditorias', Route::auth(['SasisopaController', 'auditorias']));
        // Elemento 16
        $r->addRoute('GET', '/investigacion-incidentes-accidentes', Route::auth(['SasisopaController', 'investigacionIncidentesAccidentes']));
        // Elemento 17
        $r->addRoute('GET', '/revision-resultados', Route::auth(['SasisopaController', 'revisionResultados']));
        // Elemento 18
        $r->addRoute('GET', '/informes-desempeno', Route::auth(['SasisopaController', 'informesDesempeno']));


        // Otros
        $r->addRoute('GET', '/calendario', Route::auth(['SasisopaController', 'calendario']));
        $r->addRoute('GET', '/cursos', Route::auth(['SasisopaController', 'cursos']));
    });

    // ---------------- SGM ----------------
    $r->addGroup('/sgm', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['SgmController', 'index']));

        $r->addRoute('GET', '/estructura-sistema-medicion', Route::auth(['SgmController', 'estructuraSistemaMedicion']));
        $r->addRoute('GET', '/datatable-lista-revision-sgm', Route::auth(['SgmController', 'datatableListaRevisionSgm']));
        
        $r->addRoute('GET', '/control-documental-sistema-gestion-medicion', Route::auth(['SgmController', 'controlDocumentalSistemaGestionMedicion']));
        $r->addRoute('GET', '/responsabilidades-direccion', Route::auth(['SgmController', 'responsabilidadesDireccion']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente', Route::auth(['SgmController', 'establecimientoObjetivosEnfocadosCliente']));
        $r->addRoute('GET', '/normatividad-aplicable-mediciones', Route::auth(['SgmController', 'normatividadAplicableMediciones']));
        $r->addRoute('GET', '/gestion-recursos', Route::auth(['SgmController', 'gestionRecursos']));
        $r->addRoute('GET', '/procesos-medicion', Route::auth(['SgmController', 'procesosMedicion']));
        $r->addRoute('GET', '/gestion-riesgos-impactan-medicion', Route::auth(['SgmController', 'gestionRiesgosImpactanMedicion']));
        $r->addRoute('GET', '/establecimiento-seguimiento-confirmacion-metrologica', Route::auth(['SgmController', 'establecimientoSeguimientoConfirmacionMetrologica']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos', Route::auth(['SgmController', 'auditoriasInternasExternasAtencionHallazgos']));
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion', Route::auth(['SgmController', 'evaluacionCumplimientoObjetivosRevisionDireccion']));
    });

    // ---------------- RUTA FINAL ----------------
    //$r->addRoute('GET', '/{url:.+}', Route::auth(['ModuloController', 'RutasModulos']));
};
