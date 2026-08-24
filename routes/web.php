<?php

use FastRoute\RouteCollector;
use App\Core\Route;

return function (RouteCollector $r) {

    // ---------------- AUTH ----------------

    $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
    $r->addRoute('GET', '/login', Route::guest(['LoginController', 'index']));
    $r->addRoute('POST', '/login', Route::guest(['LoginController', 'login']));
    $r->addRoute('POST', '/refresh-token', Route::auth(['LoginController', 'refreshToken']));
    // Logout via POST
    $r->addRoute('POST', '/logout', Route::auth(['AuthController', 'logout']));

    $r->addRoute('GET', '/download', Route::auth(['DownloadController', 'download']));

    // ---------------- HOME ----------------
    $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));

    // ---------------- SWITCHEO DE LA SESION DE LA ESTACION ----------------
    $r->addRoute('POST', '/switch-estacion', Route::auth(['SwitchEstacionController', 'switchSessionEstacion']));
    $r->addRoute('POST', '/api/module-context/set', Route::auth(['ModuleContextController', 'setContext']));


    $r->addRoute('GET', '/menu', Route::auth(['MenuController', 'index']));

    // ---------------- PERSONAL ----------------
    $r->addGroup('/personal', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['PersonalController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['PersonalController', 'datatablePersonal']));
        $r->addRoute('POST', '/delete', Route::auth(['PersonalController', 'deletePersonal']));
        $r->addRoute('POST', '/create', Route::auth(['PersonalController', 'createPersonal']));
        $r->addRoute('POST', '/update', Route::auth(['PersonalController', 'updatePersonal']));
        $r->addRoute('GET', '/sgm/pdf', Route::auth(['PersonalController', 'sgmPdf']));
        $r->addRoute('GET', '/{modulo}', Route::auth(['PersonalController', 'index']));
    });

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

        $r->addRoute('GET', '/formulario/{idEstacion}/{noReporte}', Route::auth(['GafetesController', 'formularioReporte']));
        $r->addRoute('GET', '/datatable-formulario/{idEstacion}/{noReporte}', Route::auth(['GafetesController', 'datatableGafetesFormulario']));
        $r->addRoute('POST', '/create-reporte-formulario', Route::auth(['GafetesController', 'createReporteFormulario']));
        $r->addRoute('POST', '/delete-reporte-registro-formulario', Route::auth(['GafetesController', 'deleteReporteFormulario']));


        $r->addRoute('GET', '/detalle/{idEstacion}/{noReporte}', Route::auth(['GafetesController', 'formularioSeguimiento']));
        $r->addRoute('GET', '/datatable-detalle/{idEstacion}/{noReporte}', Route::auth(['GafetesController', 'datatableGafetesFormulario']));
        $r->addRoute('GET', '/seguimiento/timeline/{idEstacion}/{noReporte}', Route::auth(['GafetesController', 'timelineSeguimiento']));
        $r->addRoute('POST', '/seguimiento/update', Route::auth(['GafetesController', 'updateSeguimientoGafetes']));
    });

    // ---------------- TARJETAS ----------------
    $r->addGroup('/solicitud-tarjetas', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['TarjetasController', 'index']));
        $r->addRoute('GET', '/datatable', Route::auth(['TarjetasController', 'datatableTarjetas']));
        $r->addRoute('POST', '/create-reporte', Route::auth(['TarjetasController', 'createReporte']));
        $r->addRoute('POST', '/delete-reporte', Route::auth(['TarjetasController', 'deleteReporte']));

        $r->addRoute('GET', '/formulario/{idEstacion}/{noSolicitud}', Route::auth(['TarjetasController', 'formularioReporte']));
        $r->addRoute('GET', '/datatable-formulario/{idEstacion}/{noSolicitud}', Route::auth(['TarjetasController', 'datatableTarjetasFormulario']));
        $r->addRoute('POST', '/create-reporte-formulario', Route::auth(['TarjetasController', 'createReporteFormulario']));
        $r->addRoute('POST', '/update-reporte-formulario', Route::auth(['TarjetasController', 'updateReporteFormulario']));
        $r->addRoute('POST', '/delete-reporte-formulario', Route::auth(['TarjetasController', 'deleteReporteFormulario']));

        $r->addRoute('GET', '/seguimiento/timeline/{idEstacion}/{noSeguimiento}', Route::auth(['TarjetasController', 'timelineSeguimiento']));
        $r->addRoute('GET', '/archivo/{idEstacion}/{noSeguimiento}', Route::auth(['TarjetasController', 'obtenerArchivoTarjeta']));
        $r->addRoute('POST', '/seguimiento/update', Route::auth(['TarjetasController', 'updateSeguimientoTarjetas']));

        $r->addRoute('GET', '/detalle/{idEstacion}/{noSolicitud}', Route::auth(['TarjetasController', 'formularioSeguimiento']));
        $r->addRoute('POST', '/comentarios/update', Route::auth(['TarjetasController', 'updateComentarioTarjetas']));
    });


    // ---------------- CONFIGURACION ----------------
    $r->addGroup('/configuracion', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['ConfiguracionController', 'index']));

        //----- Modulos 
        $r->addRoute('GET', '/modulos', Route::auth(['ConfiguracionController', 'modulosIndex']));
        $r->addRoute('GET', '/datatable-modulos', Route::auth(['ConfiguracionController', 'datatableModulos']));
        $r->addRoute('POST', '/create-modulos', Route::auth(['ConfiguracionController', 'createModulos']));
        $r->addRoute('POST', '/update-modulos', Route::auth(['ConfiguracionController', 'updateModulos']));
        $r->addRoute('POST', '/delete-modulos', Route::auth(['ConfiguracionController', 'deleteModulos']));

        //----- Modulos puestos (Portal)
        $r->addRoute('GET', '/modulos-puestos', Route::auth(['ConfiguracionController', 'modulosPuestosIndex']));
        $r->addRoute('GET', '/modulos-puestos/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosPuestosFormulario']));
        $r->addRoute('GET', '/datatable-modulos-puestos/idPuesto/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosPuestos']));
        $r->addRoute('POST', '/modulos-puestos/create', Route::auth(['ConfiguracionController', 'createModulosPuestos']));
        $r->addRoute('POST', '/modulos-puestos/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosPuestos']));
        $r->addRoute('POST', '/modulos-puestos/delete', Route::auth(['ConfiguracionController', 'deleteModulosPuestos']));
        $r->addRoute('GET', '/modulos-puestos/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosPuestos']));
        $r->addRoute('GET', '/modulos-puestos/modulo/{id:\d+}/{idActual:\d+}', Route::auth(['ConfiguracionController', 'getModulosPuestos']));
        $r->addRoute('GET', '/modulos-puestos/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosPuestosDetalle']));

        //----- Modulos usuarios (Portal)
        $r->addRoute('GET', '/modulos-usuarios', Route::auth(['ConfiguracionController', 'modulosUsuariosIndex']));
        $r->addRoute('GET', '/modulos-usuarios/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosUsuariosFormulario']));
        $r->addRoute('GET', '/datatable-modulos-usuarios/idUsuario/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosUsuarios']));
        $r->addRoute('POST', '/modulos-usuarios/create', Route::auth(['ConfiguracionController', 'createModulosUsuarios']));
        $r->addRoute('POST', '/modulos-usuarios/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosUsuarios']));
        $r->addRoute('POST', '/modulos-usuarios/delete', Route::auth(['ConfiguracionController', 'deleteModulosUsuarios']));
        $r->addRoute('GET', '/modulos-usuarios/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosUsuarios']));
        $r->addRoute('GET', '/modulos-usuarios/modulo/{id:\d+}/{idActual:\d+}', Route::auth(['ConfiguracionController', 'getModulosUsuarios']));
        $r->addRoute('GET', '/modulos-usuarios/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosUsuariosDetalle']));

        //----- Modulos (Dpto. Operativo)
        $r->addRoute('GET', '/modulos-operativo', Route::auth(['ConfiguracionController', 'modulosDptoOperativoIndex']));
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
        $r->addRoute('GET', '/modulos-operativo-puestos', Route::auth(['ConfiguracionController', 'modulosPuestosDptoOperativoIndex']));
        $r->addRoute('GET', '/modulos-operativo-puestos/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosPuestosDptoOperativoFormulario']));
        $r->addRoute('GET', '/datatable-modulos-operativo-puestos/idPuesto/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosPuestosDptoOperativo']));
        $r->addRoute('GET', '/modulos-operativo-puestos/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestos']));
        $r->addRoute('GET', '/modulos-operativo-puestos/modulo/{id:\d+}/{idActual:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestos']));
        $r->addRoute('GET', '/submodulos-operativo-puestos/modulo/{idModulo:\d+}/puesto/{idPuesto:\d+}', Route::auth(['ConfiguracionController', 'getSubmodulosDptoOperativoPuestos']));
        $r->addRoute('GET', '/modulos-operativo-puestos/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoPuestosDetalle']));
        $r->addRoute('POST', '/modulos-operativo-puestos/create', Route::auth(['ConfiguracionController', 'createModulosDptoOperativoPuestos']));
        $r->addRoute('POST', '/modulos-operativo-puestos/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosDptoOperativoPuestos']));
        $r->addRoute('POST', '/modulos-operativo-puestos/delete', Route::auth(['ConfiguracionController', 'deleteModulosDptoOperativoPuestos']));

        //----- Modulos usuario (Dpto. Operativo)
        $r->addRoute('GET', '/modulos-operativo-usuarios', Route::auth(['ConfiguracionController', 'modulosUsuariosDptoOperativoIndex']));
        $r->addRoute('GET', '/modulos-operativo-usuarios/{id:\d+}', Route::auth(['ConfiguracionController', 'modulosUsuariosDptoOperativoFormulario']));
        $r->addRoute('GET', '/datatable-modulos-operativo-usuarios/idUsuario/{id:\d+}', Route::auth(['ConfiguracionController', 'datatableModulosUsuariosDptoOperativo']));
        $r->addRoute('GET', '/modulos-operativo-usuarios/modulo/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuarios']));
        $r->addRoute('GET', '/modulos-operativo-usuarios/modulo/{id:\d+}/{idActual:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuarios']));
        $r->addRoute('GET', '/submodulos-operativo-usuarios/modulo/{idModulo:\d+}/usuario/{idUsuario:\d+}', Route::auth(['ConfiguracionController', 'getSubmodulosDptoOperativoUsuarios']));
        $r->addRoute('GET', '/modulos-operativo-usuarios/detalle/{id:\d+}', Route::auth(['ConfiguracionController', 'getModulosDptoOperativoUsuariosDetalle']));
        $r->addRoute('POST', '/modulos-operativo-usuarios/create', Route::auth(['ConfiguracionController', 'createModulosDptoOperativoUsuarios']));
        $r->addRoute('POST', '/modulos-operativo-usuarios/update/{id}', Route::auth(['ConfiguracionController', 'updateModulosDptoOperativoUsuarios']));
        $r->addRoute('POST', '/modulos-operativo-usuarios/delete', Route::auth(['ConfiguracionController', 'deleteModulosDptoOperativUsuarios']));
    });


    // ---------------- DEPARTAMENTO DE SISTEMAS ----------------
    $r->addGroup('/departamento-sistemas', function (RouteCollector $r) {});



    // ---------------- DEPARTAMENTO OPERATIVO ----------------
    $r->addGroup('/departamento-operativo', function (RouteCollector $r) {

        $r->addRoute('GET', '', Route::auth(['DptoOperativoController', 'index']));

        //----- 1. Corporativo
        $r->addRoute('GET', '/corporativo', Route::auth(['DptoOperativoController', 'corporativoIndex']));

        //----- Corte Diario
        $r->addRoute('GET', '/corporativo/corte-diario', Route::auth(['CorporativoController', 'corteDiarioRedirect']));
        $r->addRoute('GET', '/corporativo/corte-diario/{idYear:\d+}/{idMes:\d+}', Route::auth(['CorporativoController', 'corteDiarioIndex']));
        $r->addRoute('GET', '/corporativo/corte-diario-datatable/{idYear:\d+}/{idMes:\d+}', Route::auth(['CorporativoController', 'corteDiarioDatatable']));
        $r->addRoute('PUT', '/corporativo/corte-diario/editar', Route::auth(['CorporativoController', 'corteDiarioEditar']));
        $r->addRoute('GET', '/corporativo/corte-diario/resumen', Route::auth(['CorporativoController', 'corteDiarioGetResumen']));
        $r->addRoute('GET', '/corporativo/corte-diario/detalle', Route::auth(['CorporativoController', 'corteDiarioGetDetalle']));
        $r->addRoute('GET', '/corporativo/corte-diario/historial', Route::auth(['CorporativoController', 'corteDiarioGetHistorial']));
        $r->addRoute('POST', '/corporativo/corte-diario/activar', Route::auth(['CorporativoController', 'corteDiarioActivar']));

        //----- Corte Diario Evaluación (KPI's)
        $r->addRoute('GET', '/corte-diario-evaluacion/{idYear:\d+}/{idMes:\d+}', Route::auth(['CorteDiarioEvaluacionController', 'index']));
        $r->addRoute('GET', '/corte-diario-evaluacion/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['CorteDiarioEvaluacionController', 'getData']));

        //----- Concentrado de Ventas
        $r->addRoute('GET', '/concentrado-ventas/{idYear:\d+}/{idMes:\d+}', Route::auth(['ConcentradoVentasController', 'index']));
        $r->addRoute('GET', '/concentrado-ventas/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['ConcentradoVentasController', 'getData']));

        //----- Ventas (dentro del grupo /departamento-operativo)
        $r->addRoute('GET', '/ventas/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}', Route::auth(['VentasController', 'index']));
        $r->addRoute('GET', '/ventas/data/{idDia:\d+}', Route::auth(['VentasController', 'getData']));
        $r->addRoute('POST', '/ventas/{idDia:\d+}/nueva-venta', Route::auth(['VentasController', 'newVenta']));
        $r->addRoute('PUT', '/ventas/editar-venta', Route::auth(['VentasController', 'editVenta']));
        $r->addRoute('PUT', '/ventas/editar-venta-otros', Route::auth(['VentasController', 'editVentaOtros']));
        $r->addRoute('PUT', '/ventas/editar-prosegur', Route::auth(['VentasController', 'editProsegur']));
        $r->addRoute('PUT', '/ventas/editar-tarjeta', Route::auth(['VentasController', 'editTarjeta']));
        $r->addRoute('PUT', '/ventas/editar-controlgas', Route::auth(['VentasController', 'editControlgas']));
        $r->addRoute('PUT', '/ventas/editar-pago-cliente', Route::auth(['VentasController', 'editPagoCliente']));
        $r->addRoute('PUT', '/ventas/editar-aceite', Route::auth(['VentasController', 'editAceite']));
        $r->addRoute('PUT', '/ventas/editar-observaciones', Route::auth(['VentasController', 'editObservaciones']));
        $r->addRoute('POST', '/ventas/{idDia:\d+}/subir-documento', Route::auth(['VentasController', 'uploadDocumento']));
        $r->addRoute('POST', '/ventas/eliminar-documento', Route::auth(['VentasController', 'deleteDocumento']));
        $r->addRoute('POST', '/ventas/firmar', Route::auth(['VentasController', 'firmar']));
        $r->addRoute('POST', '/ventas/crear-token', Route::auth(['VentasController', 'crearToken']));
        $r->addRoute('POST', '/ventas/firmar-token', Route::auth(['VentasController', 'firmarConToken']));
        $r->addRoute('GET', '/ventas/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}/pdf', Route::auth(['VentasController', 'downloadPdf']));

        //----- TPV / Cierre Lote
        $r->addRoute('GET', '/cierre-lote/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}', Route::auth(['TpvController', 'index']));
        $r->addRoute('GET', '/cierre-lote/data/{idDia:\d+}', Route::auth(['TpvController', 'getData']));
        $r->addRoute('POST', '/cierre-lote/crear', Route::auth(['TpvController', 'crear']));
        $r->addRoute('POST', '/cierre-lote/editar', Route::auth(['TpvController', 'editar']));
        $r->addRoute('POST', '/cierre-lote/pendiente', Route::auth(['TpvController', 'pendiente']));
        $r->addRoute('GET', '/cierre-lote/totales/{idDia:\d+}', Route::auth(['TpvController', 'getTotales']));
        $r->addRoute('GET', '/cierre-lote/excel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['TpvController', 'descargarExcel']));

        //----- Impuestos
        $r->addRoute('GET', '/impuestos/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}', Route::auth(['ImpuestoController', 'index']));
        $r->addRoute('GET', '/impuestos/data/{idDia:\d+}', Route::auth(['ImpuestoController', 'getData']));

        //----- Resumen Impuestos
        $r->addRoute('GET', '/resumen-impuestos/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenImpuestosController', 'index']));
        $r->addRoute('GET', '/resumen-impuestos/dias/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenImpuestosController', 'getDias']));
        $r->addRoute('GET', '/resumen-impuestos/detalle-dia/{idDia:\d+}', Route::auth(['ResumenImpuestosController', 'getDetalleDia']));
        $r->addRoute('GET', '/resumen-impuestos/totales/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenImpuestosController', 'getTotales']));

        //----- Monedero
        $r->addRoute('GET', '/monedero/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}', Route::auth(['MonederoController', 'index']));
        $r->addRoute('GET', '/monedero/data/{idDia:\d+}', Route::auth(['MonederoController', 'getData']));

        //----- Clientes
        $r->addRoute('GET', '/clientes/{idYear:\d+}/{idMes:\d+}/{idDia:\d+}', Route::auth(['ClienteController', 'index']));
        $r->addRoute('GET', '/clientes/data/{idDia:\d+}', Route::auth(['ClienteController', 'getData']));
        $r->addRoute('GET', '/clientes/lista', Route::auth(['ClienteController', 'getClientes']));
        $r->addRoute('POST', '/clientes/agregar/pago', Route::auth(['ClienteController', 'agregarPago']));
        $r->addRoute('POST', '/clientes/agregar/consumo', Route::auth(['ClienteController', 'agregarConsumo']));
        $r->addRoute('POST', '/clientes/eliminar', Route::auth(['ClienteController', 'eliminar']));

        //----- Clientes Lista
        $r->addRoute('GET', '/clientes-lista', Route::auth(['ClientesListaController', 'index']));
        $r->addRoute('GET', '/clientes-lista/data', Route::auth(['ClientesListaController', 'getLista']));
        $r->addRoute('POST', '/clientes-lista/guardar-contexto', Route::auth(['ClientesListaController', 'guardarContexto']));
        $r->addRoute('POST', '/clientes-lista/crear', Route::auth(['ClientesListaController', 'crear']));
        $r->addRoute('POST', '/clientes-lista/editar', Route::auth(['ClientesListaController', 'editar']));
        $r->addRoute('POST', '/clientes-lista/toggle', Route::auth(['ClientesListaController', 'toggle']));

        //----- Clientes Mes (Resumen Mensual)
        $r->addRoute('GET', '/clientes-mes/{idYear:\d+}/{idMes:\d+}', Route::auth(['ClienteMesController', 'index']));
        $r->addRoute('GET', '/clientes-mes/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['ClienteMesController', 'getData']));
        $r->addRoute('POST', '/clientes-mes/actualizar', Route::auth(['ClienteMesController', 'actualizar']));
        $r->addRoute('POST', '/clientes-mes/finalizar', Route::auth(['ClienteMesController', 'finalizar']));
        $r->addRoute('POST', '/clientes-mes/editar-saldo-inicial', Route::auth(['ClienteMesController', 'editarSaldoInicial']));
        $r->addRoute('GET', '/clientes-mes/excel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['ClienteMesController', 'descargarExcel']));

        //----- Resumen Monedero
        $r->addRoute('GET', '/resumen-monedero/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenMonederoController', 'index']));
        $r->addRoute('GET', '/resumen-monedero/periodo/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenMonederoController', 'resumenPeriodo']));
        $r->addRoute('GET', '/resumen-monedero/periodo-data/{idYear:\d+}/{idMes:\d+}', Route::auth(['ResumenMonederoController', 'resumenPeriodoData']));
        $r->addRoute('GET', '/resumen-monedero/data', Route::auth(['ResumenMonederoController', 'getData']));
        $r->addRoute('GET', '/resumen-monedero/documentos', Route::auth(['ResumenMonederoController', 'getDocumentos']));
        $r->addRoute('GET', '/resumen-monedero/edi', Route::auth(['ResumenMonederoController', 'getEdi']));
        $r->addRoute('GET', '/resumen-monedero/lista-documentos', Route::auth(['ResumenMonederoController', 'getListaDocumentos']));
        $r->addRoute('POST', '/resumen-monedero/crear-documento', Route::auth(['ResumenMonederoController', 'createDocumento']));
        $r->addRoute('POST', '/resumen-monedero/editar-documento', Route::auth(['ResumenMonederoController', 'updateDocumento']));
        $r->addRoute('POST', '/resumen-monedero/eliminar-documento', Route::auth(['ResumenMonederoController', 'deleteDocumento']));
        $r->addRoute('POST', '/resumen-monedero/crear-edi', Route::auth(['ResumenMonederoController', 'createEdi']));
        $r->addRoute('POST', '/resumen-monedero/eliminar-edi', Route::auth(['ResumenMonederoController', 'deleteEdi']));
        $r->addRoute('POST', '/resumen-monedero/crear-lista-documento', Route::auth(['ResumenMonederoController', 'createListaDocumento']));
        $r->addRoute('POST', '/resumen-monedero/eliminar-lista-documento', Route::auth(['ResumenMonederoController', 'deleteListaDocumento']));
        $r->addRoute('GET', '/resumen-monedero/excel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['ResumenMonederoController', 'descargarExcel']));
        $r->addRoute('GET', '/resumen-monedero/kpi-evaluacion/{idYear:\d+}', Route::auth(['ResumenMonederoController', 'kpiEvaluacion']));
        $r->addRoute('GET', '/resumen-monedero/kpi-evaluacion/data/{idYear:\d+}', Route::auth(['ResumenMonederoController', 'kpiEvaluacionData']));

        //----- Ingresos vs Facturación
        $r->addRoute('GET', '/corporativo/ingresos-facturacion', Route::auth(['IngresosFacturacionController', 'redirect']));
        $r->addRoute('GET', '/corporativo/ingresos-facturacion/{idYear:\d+}', Route::auth(['IngresosFacturacionController', 'index']));
        $r->addRoute('GET', '/ingresos-facturacion/data', Route::auth(['IngresosFacturacionController', 'getData']));
        $r->addRoute('POST', '/ingresos-facturacion/update-cell', Route::auth(['IngresosFacturacionController', 'updateCell']));
        $r->addRoute('GET', '/ingresos-facturacion/totales', Route::auth(['IngresosFacturacionController', 'getTotales']));
        $r->addRoute('POST', '/ingresos-facturacion/upload-file', Route::auth(['IngresosFacturacionController', 'uploadFile']));
        $r->addRoute('GET', '/ingresos-facturacion/list-files', Route::auth(['IngresosFacturacionController', 'listFiles']));
        $r->addRoute('POST', '/ingresos-facturacion/delete-file', Route::auth(['IngresosFacturacionController', 'deleteFile']));

        //----- Despacho vs Ventas
        $r->addRoute('GET', '/corporativo/despacho-ventas', Route::auth(['DespachoVentasController', 'redirect']));
        $r->addRoute('GET', '/corporativo/despacho-ventas/{idYear:\d+}/{idMes:\d+}', Route::auth(['DespachoVentasController', 'index']));
        $r->addRoute('GET', '/despacho-ventas/data', Route::auth(['DespachoVentasController', 'getData']));
        $r->addRoute('POST', '/despacho-ventas/update-cell', Route::auth(['DespachoVentasController', 'updateCell']));

        //----- Contratos
        $r->addRoute('GET', '/corporativo/contratos', Route::auth(['ContratosController', 'index']));
        $r->addRoute('GET', '/almacen/contratos', Route::auth(['ContratosController', 'index']));
        $r->addRoute('GET', '/contratos/data', Route::auth(['ContratosController', 'getData']));
        $r->addRoute('GET', '/contratos/detalle', Route::auth(['ContratosController', 'getDetalle']));
        $r->addRoute('POST', '/contratos/guardar', Route::auth(['ContratosController', 'guardar']));
        $r->addRoute('POST', '/contratos/editar', Route::auth(['ContratosController', 'editar']));
        $r->addRoute('POST', '/contratos/eliminar', Route::auth(['ContratosController', 'eliminar']));

        //----- Comparativo XML
        $r->addRoute('GET', '/corporativo/comparativo-xml', Route::auth(['ComparativoXmlController', 'redirect']));
        $r->addRoute('GET', '/corporativo/comparativo-xml/{idYear:\d+}', Route::auth(['ComparativoXmlController', 'index']));
        $r->addRoute('GET', '/comparativo-xml/data', Route::auth(['ComparativoXmlController', 'getData']));
        $r->addRoute('POST', '/comparativo-xml/update-cell', Route::auth(['ComparativoXmlController', 'updateCell']));
        $r->addRoute('POST', '/comparativo-xml/update-sat-cell', Route::auth(['ComparativoXmlController', 'updateSatCell']));
        $r->addRoute('GET', '/comparativo-xml/sat-data', Route::auth(['ComparativoXmlController', 'getSatData']));
        $r->addRoute('POST', '/comparativo-xml/add-comment', Route::auth(['ComparativoXmlController', 'addComment']));
        $r->addRoute('GET', '/comparativo-xml/comments', Route::auth(['ComparativoXmlController', 'getComments']));
        $r->addRoute('GET', '/comparativo-xml/documents', Route::auth(['ComparativoXmlController', 'getDocuments']));
        $r->addRoute('POST', '/comparativo-xml/add-document', Route::auth(['ComparativoXmlController', 'addDocument']));
        $r->addRoute('POST', '/comparativo-xml/delete-document', Route::auth(['ComparativoXmlController', 'deleteDocument']));
        $r->addRoute('POST', '/comparativo-xml/save-observations', Route::auth(['ComparativoXmlController', 'saveObservations']));
        $r->addRoute('GET', '/comparativo-xml/logs', Route::auth(['ComparativoXmlController', 'getLogs']));
        $r->addRoute('GET', '/comparativo-xml/excel/{idEstacion:\d+}/{idYear:\d+}', Route::auth(['ComparativoXmlController', 'descargarExcel']));

        //----- Comparativo XML Seguimiento
        $r->addRoute('GET', '/corporativo/comparativo-xml/seguimiento/{idYear:\d+}/{idEstacion:\d+}', Route::auth(['ComparativoXmlController', 'seguimiento']));
        $r->addRoute('GET', '/corporativo/comparativo-xml/seguimiento/{idYear:\d+}', Route::auth(['ComparativoXmlController', 'seguimiento']));

        //----- Aclaracion Voucher
        $r->addRoute('GET', '/corporativo/aclaracion-voucher', Route::auth(['AclaracionVoucherController', 'index']));
        $r->addRoute('GET', '/corporativo/aclaracion-voucher/{idYear:\d+}', Route::auth(['AclaracionVoucherController', 'index']));
        $r->addRoute('GET', '/corporativo/aclaracion-voucher/{idYear:\d+}/{idMes:\d+}', Route::auth(['AclaracionVoucherController', 'index']));
        $r->addRoute('GET', '/corporativo/aclaracion-voucher/get-data', Route::auth(['AclaracionVoucherController', 'getData']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/add', Route::auth(['AclaracionVoucherController', 'add']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/edit', Route::auth(['AclaracionVoucherController', 'edit']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/delete', Route::auth(['AclaracionVoucherController', 'delete']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/finalizar', Route::auth(['AclaracionVoucherController', 'finalizar']));
        $r->addRoute('GET', '/corporativo/aclaracion-voucher/get-comentarios', Route::auth(['AclaracionVoucherController', 'getComentarios']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/add-comentario', Route::auth(['AclaracionVoucherController', 'addComentario']));
        $r->addRoute('GET', '/corporativo/aclaracion-voucher/get-anexos', Route::auth(['AclaracionVoucherController', 'getAnexos']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/add-anexo', Route::auth(['AclaracionVoucherController', 'addAnexo']));
        $r->addRoute('POST', '/corporativo/aclaracion-voucher/delete-anexo', Route::auth(['AclaracionVoucherController', 'deleteAnexo']));

        //----- Solicitud de Vales
        $r->addRoute('GET', '/corporativo/solicitud-vales', Route::auth(['SolicitudValeController', 'redirect']));
        $r->addRoute('GET', '/corporativo/solicitud-vales/{idYear:\d+}', Route::auth(['SolicitudValeController', 'index']));
        $r->addRoute('GET', '/corporativo/solicitud-vales/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudValeController', 'index']));
        $r->addRoute('GET', '/corporativo/solicitud-vales/get-data', Route::auth(['SolicitudValeController', 'getData']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/add', Route::auth(['SolicitudValeController', 'add']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/edit', Route::auth(['SolicitudValeController', 'edit']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/delete', Route::auth(['SolicitudValeController', 'delete']));
        $r->addRoute('GET', '/corporativo/solicitud-vales/get-comentarios', Route::auth(['SolicitudValeController', 'getComentarios']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/add-comentario', Route::auth(['SolicitudValeController', 'addComentario']));
        $r->addRoute('GET', '/corporativo/solicitud-vales/get-documentos', Route::auth(['SolicitudValeController', 'getDocumentos']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/add-documento', Route::auth(['SolicitudValeController', 'addDocumento']));
        $r->addRoute('POST', '/corporativo/solicitud-vales/delete-documento', Route::auth(['SolicitudValeController', 'deleteDocumento']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-nuevo/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudValeController', 'crear']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-nuevo/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['SolicitudValeController', 'crear']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-nuevo/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}/{idDepto:\d+}', Route::auth(['SolicitudValeController', 'crear']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-editar/{id:\d+}', Route::auth(['SolicitudValeController', 'editar']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-editar/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}/{id:\d+}', Route::auth(['SolicitudValeController', 'editar']));
        $r->addRoute('GET', '/corporativo/solicitud-vales-pdf/{id:\d+}', Route::auth(['SolicitudValeController', 'pdf']));

        //----- Factura Monedero
        $r->addRoute('GET', '/corporativo/factura-monedero', Route::auth(['FacturaMonederoController', 'redirect']));
        $r->addRoute('GET', '/corporativo/factura-monedero/{idYear:\d+}', Route::auth(['FacturaMonederoController', 'index']));
        $r->addRoute('GET', '/corporativo/factura-monedero/{idYear:\d+}/{idMes:\d+}', Route::auth(['FacturaMonederoController', 'index']));
        $r->addRoute('GET', '/corporativo/factura-monedero/get-data', Route::auth(['FacturaMonederoController', 'getData']));
        $r->addRoute('GET', '/corporativo/factura-monedero/get-detalle/{id:\d+}', Route::auth(['FacturaMonederoController', 'getDetalle']));
        $r->addRoute('POST', '/corporativo/factura-monedero/add', Route::auth(['FacturaMonederoController', 'add']));
        $r->addRoute('POST', '/corporativo/factura-monedero/edit', Route::auth(['FacturaMonederoController', 'edit']));
        $r->addRoute('POST', '/corporativo/factura-monedero/delete', Route::auth(['FacturaMonederoController', 'delete']));
        $r->addRoute('GET', '/corporativo/factura-monedero/get-pendientes', Route::auth(['FacturaMonederoController', 'getPendientesData']));
        $r->addRoute('GET', '/corporativo/factura-monedero/get-comentarios', Route::auth(['FacturaMonederoController', 'getComentarios']));
        $r->addRoute('POST', '/corporativo/factura-monedero/add-comentario', Route::auth(['FacturaMonederoController', 'addComentario']));
        $r->addRoute('GET', '/corporativo/factura-monedero-excel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['FacturaMonederoController', 'descargarExcel']));
        $r->addRoute('GET', '/corporativo/factura-monedero-pdf/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['FacturaMonederoController', 'downloadPdf']));

        //----- Seguros
        $r->addRoute('GET', '/corporativo/seguros', Route::auth(['SegurosController', 'index']));
        $r->addRoute('GET', '/seguros/data', Route::auth(['SegurosController', 'getData']));
        $r->addRoute('GET', '/seguros/detalle-incidencia', Route::auth(['SegurosController', 'getDetalleIncidencia']));
        $r->addRoute('POST', '/seguros/guardar-incidencia', Route::auth(['SegurosController', 'guardarIncidencia']));
        $r->addRoute('POST', '/seguros/editar-incidencia', Route::auth(['SegurosController', 'editarIncidencia']));
        $r->addRoute('POST', '/seguros/eliminar-incidencia', Route::auth(['SegurosController', 'eliminarIncidencia']));
        $r->addRoute('GET', '/seguros/polizas', Route::auth(['SegurosController', 'getPolizas']));
        $r->addRoute('GET', '/seguros/detalle-poliza', Route::auth(['SegurosController', 'getDetallePoliza']));
        $r->addRoute('POST', '/seguros/guardar-poliza', Route::auth(['SegurosController', 'guardarPoliza']));
        $r->addRoute('POST', '/seguros/editar-poliza', Route::auth(['SegurosController', 'editarPoliza']));
        $r->addRoute('POST', '/seguros/eliminar-poliza', Route::auth(['SegurosController', 'eliminarPoliza']));
        $r->addRoute('GET', '/seguros/vencimiento', Route::auth(['SegurosController', 'getVencimiento']));

        //----- Estimulo Fiscal
        $r->addRoute('GET', '/corporativo/estimulo-fiscal', Route::auth(['EstimuloFiscalController', 'index']));
        $r->addRoute('GET', '/estimulo-fiscal/data', Route::auth(['EstimuloFiscalController', 'getData']));
        $r->addRoute('GET', '/estimulo-fiscal/detalle', Route::auth(['EstimuloFiscalController', 'getDetalle']));
        $r->addRoute('POST', '/estimulo-fiscal/guardar', Route::auth(['EstimuloFiscalController', 'guardar']));
        $r->addRoute('POST', '/estimulo-fiscal/editar', Route::auth(['EstimuloFiscalController', 'editar']));
        $r->addRoute('POST', '/estimulo-fiscal/eliminar', Route::auth(['EstimuloFiscalController', 'eliminar']));

        //----- Control Volumétrico
        $r->addRoute('GET', '/control-volumetrico/{idYear:\d+}/{idMes:\d+}', Route::auth(['ControlVolumetricoController', 'index']));
        $r->addRoute('GET', '/control-volumetrico/data', Route::auth(['ControlVolumetricoController', 'getData']));
        $r->addRoute('POST', '/control-volumetrico/editar-resumen', Route::auth(['ControlVolumetricoController', 'editarResumen']));
        $r->addRoute('POST', '/control-volumetrico/editar-comentario-resumen', Route::auth(['ControlVolumetricoController', 'editarComentarioResumen']));
        $r->addRoute('POST', '/control-volumetrico/editar-aceite', Route::auth(['ControlVolumetricoController', 'editarAceite']));
        $r->addRoute('POST', '/control-volumetrico/editar-prefijo', Route::auth(['ControlVolumetricoController', 'editarPrefijo']));
        $r->addRoute('POST', '/control-volumetrico/agregar-comentario', Route::auth(['ControlVolumetricoController', 'agregarComentario']));
        $r->addRoute('POST', '/control-volumetrico/subir-documento', Route::auth(['ControlVolumetricoController', 'uploadDocumento']));
        $r->addRoute('POST', '/control-volumetrico/eliminar-documento', Route::auth(['ControlVolumetricoController', 'eliminarDocumento']));
        $r->addRoute('GET', '/control-volumetrico/documentos-list', Route::auth(['ControlVolumetricoController', 'getDocumentosList']));
        $r->addRoute('GET', '/control-volumetrico/comentarios-list', Route::auth(['ControlVolumetricoController', 'getComentariosList']));

        //----- Embarques
        $r->addRoute('GET', '/embarques/{idYear:\d+}/{idMes:\d+}', Route::auth(['EmbarquesController', 'index']));
        $r->addRoute('GET', '/embarques/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['EmbarquesController', 'getDatos']));
        $r->addRoute('POST', '/embarques/store', Route::auth(['EmbarquesController', 'store']));
        $r->addRoute('POST', '/embarques/update', Route::auth(['EmbarquesController', 'update']));
        $r->addRoute('POST', '/embarques/delete', Route::auth(['EmbarquesController', 'destroy']));
        $r->addRoute('GET', '/embarques/comentarios', Route::auth(['EmbarquesController', 'getComentarios']));
        $r->addRoute('POST', '/embarques/store-comentario', Route::auth(['EmbarquesController', 'storeComentario']));
        $r->addRoute('GET', '/embarques/catalogos', Route::auth(['EmbarquesController', 'getCatalogos']));


        //----- Solicitud de Cheque
        $r->addRoute('GET', '/solicitud-cheques', Route::auth(['SolicitudChequeController', 'redirectToPeriod']));
        $r->addRoute('GET', '/solicitud-cheque', Route::auth(['SolicitudChequeController', 'redirectToPeriod']));
        $r->addRoute('GET', '/solicitud-cheque/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'index']));
        $r->addRoute('GET', '/solicitud-cheque-crear/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'crear']));
        $r->addRoute('GET', '/solicitud-cheque-crear/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['SolicitudChequeController', 'crear']));
        $r->addRoute('GET', '/solicitud-cheque-crear/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}/{idDepto:\d+}', Route::auth(['SolicitudChequeController', 'crear']));
        $r->addRoute('GET', '/solicitud-cheque-editar/{id}', Route::auth(['SolicitudChequeController', 'editar']));
        $r->addRoute('GET', '/solicitud-cheque-firmar/{id}', Route::auth(['SolicitudChequeController', 'firmarPage']));
        $r->addRoute('GET', '/solicitud-cheque/factura-status/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}/{idDepto:\d+}', Route::auth(['SolicitudChequeController', 'getFacturaStatusEndpoint']));
        $r->addRoute('GET', '/solicitud-cheque/pending-counts/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'getPendingCountsEndpoint']));
        $r->addRoute('GET', '/solicitud-cheque/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'getData']));
        $r->addRoute('POST', '/solicitud-cheque/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'getData']));
        $r->addRoute('POST', '/solicitud-cheque/store', Route::auth(['SolicitudChequeController', 'store']));
        $r->addRoute('POST', '/solicitud-cheque/update', Route::auth(['SolicitudChequeController', 'update']));
        $r->addRoute('POST', '/solicitud-cheque/delete', Route::auth(['SolicitudChequeController', 'destroy']));
        $r->addRoute('GET', '/solicitud-cheque/detalle', Route::auth(['SolicitudChequeController', 'getDetalle']));
        $r->addRoute('GET', '/solicitud-cheque/documentos', Route::auth(['SolicitudChequeController', 'getDocumentos']));
        $r->addRoute('POST', '/solicitud-cheque/store-documento', Route::auth(['SolicitudChequeController', 'storeDocumento']));
        $r->addRoute('POST', '/solicitud-cheque/delete-documento', Route::auth(['SolicitudChequeController', 'deleteDocumento']));
        $r->addRoute('GET', '/solicitud-cheque/comentarios', Route::auth(['SolicitudChequeController', 'getComentarios']));
        $r->addRoute('POST', '/solicitud-cheque/store-comentario', Route::auth(['SolicitudChequeController', 'storeComentario']));
        $r->addRoute('POST', '/solicitud-cheque/crear-token', Route::auth(['SolicitudChequeController', 'crearToken']));
        $r->addRoute('POST', '/solicitud-cheque/firmar', Route::auth(['SolicitudChequeController', 'firmar']));
        $r->addRoute('GET', '/solicitud-cheque/firmas', Route::auth(['SolicitudChequeController', 'getFirmas']));
        $r->addRoute('GET', '/solicitud-cheque/telcel', Route::auth(['SolicitudChequeController', 'getTelcel']));
        $r->addRoute('POST', '/solicitud-cheque/store-telcel', Route::auth(['SolicitudChequeController', 'storeTelcel']));
        $r->addRoute('POST', '/solicitud-cheque/delete-telcel', Route::auth(['SolicitudChequeController', 'deleteTelcel']));
        $r->addRoute('POST', '/solicitud-cheque/update-pago-telcel', Route::auth(['SolicitudChequeController', 'updatePagoTelcel']));
        $r->addRoute('GET', '/solicitud-cheque/telcel-global', Route::auth(['SolicitudChequeController', 'getTelcelGlobal']));
        $r->addRoute('POST', '/solicitud-cheque/store-telcel-global', Route::auth(['SolicitudChequeController', 'storeTelcelGlobal']));
        $r->addRoute('POST', '/solicitud-cheque/delete-comprobante-telcel', Route::auth(['SolicitudChequeController', 'deleteComprobanteTelcel']));
        $r->addRoute('GET', '/solicitud-cheque/pagos', Route::auth(['SolicitudChequeController', 'getPagos']));
        $r->addRoute('POST', '/solicitud-cheque/store-pago', Route::auth(['SolicitudChequeController', 'storePago']));
        $r->addRoute('POST', '/solicitud-cheque/delete-pago', Route::auth(['SolicitudChequeController', 'deletePago']));
        $r->addRoute('GET', '/solicitud-cheque/selector-opciones', Route::auth(['SolicitudChequeController', 'getSelectorOpciones']));
        $r->addRoute('GET', '/solicitud-cheque-pdf/{idSolicitud:\d+}', Route::auth(['SolicitudChequeController', 'downloadPdf']));
        $r->addRoute('GET', '/solicitud-cheque/excel/{idYear:\d+}/{idMes:\d+}', Route::auth(['SolicitudChequeController', 'downloadExcel']));
        $r->addRoute('GET', '/solicitud-cheque-telcel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['SolicitudChequeController', 'facturaTelcel']));
        $r->addRoute('GET', '/solicitud-cheque-telcel/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}/{idDepto:\d+}', Route::auth(['SolicitudChequeController', 'facturaTelcel']));
        $r->addRoute('GET', '/solicitud-cheque/factura-telcel/directorio/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['SolicitudChequeController', 'getDirectorioData']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/store-directorio', Route::auth(['SolicitudChequeController', 'storeDirectorio']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/update-directorio', Route::auth(['SolicitudChequeController', 'updateDirectorio']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/delete-directorio', Route::auth(['SolicitudChequeController', 'deleteDirectorio']));
        $r->addRoute('GET', '/solicitud-cheque/factura-telcel/list/{idYear:\d+}/{idMes:\d+}/{idEstacion:\d+}', Route::auth(['SolicitudChequeController', 'getFacturasTelcelList']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/store', Route::auth(['SolicitudChequeController', 'storeFacturaTelcel']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/delete', Route::auth(['SolicitudChequeController', 'deleteFacturaTelcel']));
        $r->addRoute('POST', '/solicitud-cheque/factura-telcel/store-comentario', Route::auth(['SolicitudChequeController', 'storeFacturaTelcelComentario']));

        //----- Analisis de Compras
        $r->addRoute('GET', '/analisis-compra/{idYear:\d+}/{idMes:\d+}', Route::auth(['AnalisisCompraController', 'index']));
        $r->addRoute('GET', '/analisis-compra/{idYear:\d+}/{idMes:\d+}/excel', Route::auth(['AnalisisCompraController', 'descargarExcel']));
        $r->addRoute('POST', '/analisis-compra/notac', Route::auth(['AnalisisCompraController', 'updateNotac']));
        $r->addRoute('POST', '/analisis-compra/status', Route::auth(['AnalisisCompraController', 'updateStatus']));

        //----- Aceites / Resumen Aceites
        $r->addRoute('GET', '/aceites-mes/{idYear:\d+}/{idMes:\d+}', Route::auth(['AceitesController', 'index']));
        $r->addRoute('GET', '/aceites-mes/data', Route::auth(['AceitesController', 'data']));
        $r->addRoute('POST', '/aceites-mes/editar-campo', Route::auth(['AceitesController', 'editarCampo']));
        $r->addRoute('GET', '/aceites-mes/documentos', Route::auth(['AceitesController', 'getDocumentos']));
        $r->addRoute('POST', '/aceites-mes/upload-documento', Route::auth(['AceitesController', 'uploadDocumento']));
        $r->addRoute('POST', '/aceites-mes/actualizar-documento', Route::auth(['AceitesController', 'actualizarDocumento']));
        $r->addRoute('POST', '/aceites-mes/eliminar-documento', Route::auth(['AceitesController', 'eliminarDocumento']));
        $r->addRoute('POST', '/aceites-mes/evaluar-documento', Route::auth(['AceitesController', 'evaluarDocumento']));
        $r->addRoute('GET', '/aceites-mes/facturas', Route::auth(['AceitesController', 'getFacturas']));
        $r->addRoute('POST', '/aceites-mes/upload-factura', Route::auth(['AceitesController', 'uploadFactura']));
        $r->addRoute('POST', '/aceites-mes/eliminar-factura', Route::auth(['AceitesController', 'eliminarFactura']));
        $r->addRoute('POST', '/aceites-mes/evaluar-factura', Route::auth(['AceitesController', 'evaluarFactura']));
        $r->addRoute('GET', '/aceites-mes/diferencias', Route::auth(['AceitesController', 'getDiferencias']));
        $r->addRoute('POST', '/aceites-mes/agregar-diferencia', Route::auth(['AceitesController', 'agregarDiferencia']));
        $r->addRoute('POST', '/aceites-mes/actualizar-documento-diferencia', Route::auth(['AceitesController', 'actualizarDocumentoDiferencia']));
        $r->addRoute('POST', '/aceites-mes/finalizar', Route::auth(['AceitesController', 'finalizar']));
        $r->addRoute('GET', '/aceites-mes/resumen-puntajes', Route::auth(['AceitesController', 'getResumenPuntajes']));
        $r->addRoute('POST', '/aceites-mes/importar-facturas', Route::auth(['AceitesController', 'importarFacturas']));
        $r->addRoute('GET', '/aceites-mes/{idYear:\d+}/{idMes:\d+}/excel', Route::auth(['AceitesController', 'descargarExcel']));
        $r->addRoute('GET', '/resumen-aceites-mes/{idYear:\d+}/{idMes:\d+}', Route::auth(['AceitesController', 'resumenImpuestos']));
        $r->addRoute('GET', '/resumen-aceites-mes/data/{idYear:\d+}/{idMes:\d+}', Route::auth(['AceitesController', 'resumenImpuestosData']));
        $r->addRoute('GET', '/resumen-kpi-aceites/{idYear:\d+}', Route::auth(['AceitesController', 'kpiAceites']));
        $r->addRoute('GET', '/resumen-kpi-aceites/data/{idYear:\d+}/{tipo:\d+}', Route::auth(['AceitesController', 'kpiAceitesData']));
        $r->addRoute('GET', '/corporativo/lista-aceites', Route::auth(['AceitesController', 'listaAceites']));
        $r->addRoute('GET', '/corporativo/lista-aceites/data', Route::auth(['AceitesController', 'listaAceitesData']));
        $r->addRoute('POST', '/corporativo/lista-aceites/guardar', Route::auth(['AceitesController', 'listaAceitesGuardar']));
        $r->addRoute('POST', '/corporativo/lista-aceites/nuevo', Route::auth(['AceitesController', 'listaAceitesNuevo']));
        $r->addRoute('POST', '/corporativo/lista-aceites/eliminar', Route::auth(['AceitesController', 'listaAceitesEliminar']));
        $r->addRoute('POST', '/corporativo/lista-aceites/guardar-contexto', Route::auth(['AceitesController', 'guardarContextoListaAceites']));

        //----- 2. Recursos Humanos
        $r->addRoute('GET', '/recursos-humanos', Route::auth(['DptoOperativoController', 'recursosHumanosIndex']));

        //----- Organigrama
        $r->addRoute('GET', '/recursos-humanos/organigrama', Route::auth(['OrganigramaController', 'index']));
        $r->addRoute('GET', '/recursos-humanos/organigrama/get-versions', Route::auth(['OrganigramaController', 'getVersions']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/add', Route::auth(['OrganigramaController', 'add']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/delete', Route::auth(['OrganigramaController', 'delete']));
        $r->addRoute('GET', '/recursos-humanos/organigrama/get-plantilla', Route::auth(['OrganigramaController', 'getPlantilla']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/add-plantilla', Route::auth(['OrganigramaController', 'addPlantilla']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/update-plantilla', Route::auth(['OrganigramaController', 'updatePlantilla']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/update-plantilla-usuario', Route::auth(['OrganigramaController', 'updatePlantillaUsuario']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/delete-plantilla', Route::auth(['OrganigramaController', 'deletePlantilla']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/upload-documento', Route::auth(['OrganigramaController', 'uploadDocumento']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/delete-documento', Route::auth(['OrganigramaController', 'deleteDocumento']));
        $r->addRoute('GET', '/recursos-humanos/organigrama/get-station-info', Route::auth(['OrganigramaController', 'getStationInfo']));
        $r->addRoute('POST', '/recursos-humanos/organigrama/update-station-info', Route::auth(['OrganigramaController', 'updateStationInfo']));
        $r->addRoute('GET', '/recursos-humanos/organigrama/search-personal', Route::auth(['OrganigramaController', 'searchPersonal']));


      //----- Control de Documentos del Personal
$r->addRoute('GET','/recursos-humanos/control-documentos-personal',Route::auth(['ControlDocumentosPersonalController', 'index']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-data',Route::auth(['ControlDocumentosPersonalController', 'getData']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-data-inactivos',Route::auth(['ControlDocumentosPersonalController', 'getDataInactivos']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/add',Route::auth(['ControlDocumentosPersonalController', 'add']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/edit',Route::auth(['ControlDocumentosPersonalController', 'edit']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/delete',Route::auth(['ControlDocumentosPersonalController', 'delete']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-comentarios',Route::auth(['ControlDocumentosPersonalController', 'getComentarios']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/add-comentario',Route::auth(['ControlDocumentosPersonalController', 'addComentario']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/upload-documento',Route::auth(['ControlDocumentosPersonalController', 'uploadDocumento']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/delete-documento',Route::auth(['ControlDocumentosPersonalController', 'deleteDocumento']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/add-baja',Route::auth(['ControlDocumentosPersonalController', 'addBaja']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-acceso',Route::auth(['ControlDocumentosPersonalController', 'getAcceso']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/editar-pin',Route::auth(['ControlDocumentosPersonalController', 'editarPin']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-puestos',Route::auth(['ControlDocumentosPersonalController', 'getPuestos']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-estaciones',Route::auth(['ControlDocumentosPersonalController', 'getEstaciones']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-personal-by-id',Route::auth(['ControlDocumentosPersonalController', 'getPersonalById']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal-excel',Route::auth(['ControlDocumentosPersonalController', 'excel']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal-kpi/{idYear:\d+}',Route::auth(['ControlDocumentosPersonalController', 'kpi']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal-kpi-data/{idYear:\d+}/{tipo:\d+}',Route::auth(['ControlDocumentosPersonalController', 'kpiData']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/asistencia/{id}',Route::auth(['ControlDocumentosPersonalController', 'asistencia']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-asistencia-data',Route::auth(['ControlDocumentosPersonalController', 'getAsistenciaData']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/crear-incidencia',Route::auth(['ControlDocumentosPersonalController', 'crearIncidencia']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/agregar-incidencia',Route::auth(['ControlDocumentosPersonalController', 'agregarIncidencia']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/subir-documento-incidencia',Route::auth(['ControlDocumentosPersonalController', 'subirDocumentoIncidencia']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-incidencias-catalogo',Route::auth(['ControlDocumentosPersonalController', 'getIncidenciasCatalogo']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-incidencia-por-asistencia',Route::auth(['ControlDocumentosPersonalController', 'getIncidenciaPorAsistencia']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/acceso/{id}',Route::auth(['ControlDocumentosPersonalController', 'acceso']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/detalle-baja/{id}',Route::auth(['ControlDocumentosPersonalController', 'bajaDetalle']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-archivos-baja',Route::auth(['ControlDocumentosPersonalController', 'getArchivosBaja']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/upload-baja-archivo',Route::auth(['ControlDocumentosPersonalController', 'uploadBajaArchivo']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/delete-baja-archivo',Route::auth(['ControlDocumentosPersonalController', 'deleteBajaArchivo']));
$r->addRoute('GET','/recursos-humanos/control-documentos-personal/get-comentarios-baja',Route::auth(['ControlDocumentosPersonalController', 'getComentariosBaja']));
$r->addRoute('POST','/recursos-humanos/control-documentos-personal/add-comentario-baja',Route::auth(['ControlDocumentosPersonalController', 'addComentarioBaja']));

      //----- Horario Personal
$r->addRoute('GET','/recursos-humanos/horario-personal',Route::auth(['HorarioPersonalController', 'index']));
$r->addRoute('GET','/recursos-humanos/horario-personal/get-data',Route::auth(['HorarioPersonalController', 'getData']));
$r->addRoute('POST','/recursos-humanos/horario-personal/editar',Route::auth(['HorarioPersonalController', 'editar']));
$r->addRoute('POST','/recursos-humanos/horario-personal/eliminar',Route::auth(['HorarioPersonalController', 'eliminar']));
$r->addRoute('GET','/recursos-humanos/horario-personal/pdf',Route::auth(['HorarioPersonalController', 'pdf']));

      //----- Biometricos
$r->addRoute('GET','/recursos-humanos/biometricos',Route::auth(['BiometricosController', 'index']));
$r->addRoute('GET','/recursos-humanos/biometricos/get-data',Route::auth(['BiometricosController', 'getData']));
$r->addRoute('GET','/recursos-humanos/biometricos/get-incidencias-catalogo',Route::auth(['BiometricosController', 'getIncidenciasCatalogo']));
$r->addRoute('GET','/recursos-humanos/biometricos/get-incidencia-por-asistencia',Route::auth(['BiometricosController', 'getIncidenciaPorAsistencia']));
$r->addRoute('POST','/recursos-humanos/biometricos/agregar-incidencia',Route::auth(['BiometricosController', 'agregarIncidencia']));
$r->addRoute('POST','/recursos-humanos/biometricos/subir-documento-incidencia',Route::auth(['BiometricosController', 'subirDocumentoIncidencia']));
$r->addRoute('POST','/recursos-humanos/biometricos/editar-sueldo-incidencia',Route::auth(['BiometricosController', 'editarSueldoIncidencia']));
$r->addRoute('POST','/recursos-humanos/biometricos/eliminar-incidencia',Route::auth(['BiometricosController', 'eliminarIncidencia']));
$r->addRoute('GET','/recursos-humanos/biometricos/get-reporte',Route::auth(['BiometricosController', 'getReporte']));
$r->addRoute('GET','/recursos-humanos/biometricos/reporte-pdf',Route::auth(['BiometricosController', 'reportePdf']));
$r->addRoute('GET','/recursos-humanos/biometricos/reporte-excel',Route::auth(['BiometricosController', 'reporteExcel']));
$r->addRoute('GET','/recursos-humanos/biometricos/reporte-estaciones-pdf',Route::auth(['BiometricosController', 'reportePdfEstaciones']));
$r->addRoute('GET','/recursos-humanos/biometricos/configuracion',Route::auth(['BiometricosController', 'configuracion']));
$r->addRoute('GET','/recursos-humanos/biometricos/configuracion/{modulo:[a-z-]+}',Route::auth(['BiometricosController', 'configuracionModulo']));
$r->addRoute('GET','/recursos-humanos/biometricos/configuracion/puestos/datatable',Route::auth(['BiometricosController', 'datatablePuestos']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/puestos/create',Route::auth(['BiometricosController', 'createPuesto']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/puestos/update',Route::auth(['BiometricosController', 'updatePuesto']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/puestos/delete',Route::auth(['BiometricosController', 'deletePuesto']));
$r->addRoute('GET','/recursos-humanos/biometricos/configuracion/perfil/datatable',Route::auth(['BiometricosController', 'datatablePerfil']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/perfil/create',Route::auth(['BiometricosController', 'createPerfil']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/perfil/update',Route::auth(['BiometricosController', 'updatePerfil']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/perfil/delete',Route::auth(['BiometricosController', 'deletePerfil']));

$r->addRoute('GET','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/get-retardo-incidencia',Route::auth(['BiometricosController', 'getRetardoIncidencia']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/update-retardo-incidencia',Route::auth(['BiometricosController', 'updateRetardoIncidencia']));
$r->addRoute('GET','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/datatable-horarios',Route::auth(['BiometricosController', 'datatableHorarios']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/create-horario',Route::auth(['BiometricosController', 'createHorario']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/update-horario',Route::auth(['BiometricosController', 'updateHorario']));
$r->addRoute('POST','/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/delete-horario',Route::auth(['BiometricosController', 'deleteHorario']));

//----- Incidencias de Nómina
$r->addRoute('GET','/recursos-humanos/incidencias-nomina/{idYear:\d+}',Route::auth(['IncidenciasNominaController', 'index']));
$r->addRoute('GET','/recursos-humanos/incidencias-nomina/{idYear:\d+}/data',Route::auth(['IncidenciasNominaController', 'data']));
$r->addRoute('GET','/recursos-humanos/incidencias-nomina/{idYear:\d+}/pdf-estaciones',Route::auth(['IncidenciasNominaController', 'pdfEstaciones']));
$r->addRoute('GET','/recursos-humanos/incidencias-nomina/{idYear:\d+}/pdf-individual',Route::auth(['IncidenciasNominaController', 'pdfIndividual']));
$r->addRoute('POST','/recursos-humanos/incidencias-nomina/guardar-contexto',Route::auth(['IncidenciasNominaController', 'guardarContexto']));

//----- Día Doble
$r->addRoute('GET','/recursos-humanos/dia-doble/{idYear:\d+}',Route::auth(['DiaDobleController', 'index']));
$r->addRoute('GET','/recursos-humanos/dia-doble/{idYear:\d+}/data',Route::auth(['DiaDobleController', 'getData']));
$r->addRoute('GET','/recursos-humanos/dia-doble/{idYear:\d+}/data-estaciones',Route::auth(['DiaDobleController', 'getDataEstaciones']));
$r->addRoute('POST','/recursos-humanos/dia-doble/guardar-contexto',Route::auth(['DiaDobleController', 'guardarContexto']));
$r->addRoute('GET','/recursos-humanos/dia-doble/{idYear:\d+}/pdf-estaciones',Route::auth(['DiaDobleController', 'pdfEstaciones']));
$r->addRoute('POST','/recursos-humanos/dia-doble/{idYear:\d+}/add',Route::auth(['DiaDobleController', 'add']));
$r->addRoute('POST','/recursos-humanos/dia-doble/edit-quincena',Route::auth(['DiaDobleController', 'editQuincena']));
$r->addRoute('POST','/recursos-humanos/dia-doble/delete',Route::auth(['DiaDobleController', 'delete']));
$r->addRoute('GET','/recursos-humanos/dia-doble/detail',Route::auth(['DiaDobleController', 'getDetail']));
$r->addRoute('POST','/recursos-humanos/dia-doble/add-personal',Route::auth(['DiaDobleController', 'addPersonal']));
$r->addRoute('POST','/recursos-humanos/dia-doble/delete-personal',Route::auth(['DiaDobleController', 'deletePersonal']));
$r->addRoute('GET','/recursos-humanos/dia-doble/personal',Route::auth(['DiaDobleController', 'getPersonal']));
$r->addRoute('GET','/recursos-humanos/dia-doble/comentarios',Route::auth(['DiaDobleController', 'getComentarios']));
$r->addRoute('POST','/recursos-humanos/dia-doble/add-comentario',Route::auth(['DiaDobleController', 'addComentario']));
$r->addRoute('GET','/recursos-humanos/dia-doble/{idYear:\d+}/pdf-direccion',Route::auth(['DiaDobleController', 'pdfDireccion']));
$r->addRoute('GET','/recursos-humanos/dia-doble/firmas',Route::auth(['DiaDobleController', 'getFirmas']));
$r->addRoute('POST','/recursos-humanos/dia-doble/firmar',Route::auth(['DiaDobleController', 'firmar']));
$r->addRoute('POST','/recursos-humanos/dia-doble/firmar-firma',Route::auth(['DiaDobleController', 'firmarFirma']));
$r->addRoute('POST','/recursos-humanos/dia-doble/crear-token',Route::auth(['DiaDobleController', 'crearToken']));
$r->addRoute('GET','/recursos-humanos/dia-doble-registro/{id:\d+}',Route::auth(['DiaDobleController', 'form']));
$r->addRoute('GET','/recursos-humanos/dia-doble-firma/{id:\d+}',Route::auth(['DiaDobleController', 'firmarPage']));
$r->addRoute('GET','/recursos-humanos/dia-doble/personal-direccion',Route::auth(['DiaDobleController', 'getPersonalDireccion']));

//----- Rol de Comodines
$r->addRoute('GET','/recursos-humanos/rol-comodines',Route::auth(['RolComodinesController', 'index']));
$r->addRoute('GET','/recursos-humanos/rol-comodines/{id:\d+}',Route::auth(['RolComodinesController', 'form']));
$r->addRoute('GET','/recursos-humanos/rol-comodines/data',Route::auth(['RolComodinesController', 'getData']));
$r->addRoute('GET','/recursos-humanos/rol-comodines/detail',Route::auth(['RolComodinesController', 'getDetail']));
$r->addRoute('POST','/recursos-humanos/rol-comodines/add',Route::auth(['RolComodinesController', 'add']));
$r->addRoute('POST','/recursos-humanos/rol-comodines/edit-assignment',Route::auth(['RolComodinesController', 'editAssignment']));
$r->addRoute('POST','/recursos-humanos/rol-comodines/save-dates',Route::auth(['RolComodinesController', 'saveDates']));
$r->addRoute('POST','/recursos-humanos/rol-comodines/finalize',Route::auth(['RolComodinesController', 'finalize']));
$r->addRoute('POST','/recursos-humanos/rol-comodines/delete',Route::auth(['RolComodinesController', 'delete']));
$r->addRoute('GET','/recursos-humanos/rol-comodines/pdf',Route::auth(['RolComodinesController', 'pdf']));
$r->addRoute('GET','/recursos-humanos/rol-comodines/pdf/{id}',Route::auth(['RolComodinesController', 'pdf']));

      

        //----- 3. Importacion
        $r->addRoute('GET', '/importacion', Route::auth(['DptoOperativoController', 'importacionIndex']));

        //----- 4. Almacen
        $r->addRoute('GET', '/almacen', Route::auth(['DptoOperativoController', 'almacenIndex']));

        //----- 5. Comercializadora
        $r->addRoute('GET', '/comercializadora', Route::auth(['DptoOperativoController', 'comercializadoraIndex']));
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
        $r->addRoute('GET', '/configuracion-modulos-puesto/{puesto:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraPuestoController', 'detallePermisosPuesto']));
        $r->addRoute('PUT', '/configuracion-modulos-puesto-permiso/{id:\d+}', Route::auth(['EstructuraPuestoController', 'updatePermisosModuloPuesto']));

        // Usuarios
        $r->addRoute('GET', '/configuracion-modulos-usuario', Route::auth(['EstructuraUsuarioController', 'index']));
        $r->addRoute('GET', '/configuracion-modulos-usuario/{id:\d+}', Route::auth(['EstructuraUsuarioController', 'indexEstructuraUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/modulos/create', Route::auth(['EstructuraUsuarioController', 'createModuloUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/create', Route::auth(['EstructuraUsuarioController', 'createSubmoduloUsuario']));
        $r->addRoute('POST', '/configuracion-modulos-usuario/submodulos/delete', Route::auth(['EstructuraUsuarioController', 'deleteSubmoduloUsuario']));
        $r->addRoute('GET', '/configuracion-modulos-usuario/{usuario:\d+}/permisos-modulos/{estructura:\d+}', Route::auth(['EstructuraUsuarioController', 'detallePermisosUsuario']));
        $r->addRoute('PUT', '/configuracion-modulos-usuario-permiso/{id:\d+}', Route::auth(['EstructuraUsuarioController', 'updatePermisosModuloUsuario']));
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

    $r->addRoute('GET', '/lista-asistencia-evidencia/datatble/{id:\d+}', Route::auth(['ListaAsistenciaController', 'evidencias']));
    $r->addRoute('POST', '/lista-asistencia-evidencia/create', Route::auth(['ListaAsistenciaController', 'createEvidencia']));
    $r->addRoute('POST', '/lista-asistencia-evidencia/delete', Route::auth(['ListaAsistenciaController', 'deleteEvidencia']));

    $r->addRoute('GET', '/cursos/descargar/{id:\d+}', Route::auth(['CursosController', 'descargar']));
    $r->addRoute('GET', '/cursos/descargar/{year:\d+}/{idModulo:\d+}', Route::auth(['CursosController', 'descargarAll']));

    // ---------------- SASISOPA ----------------
    $r->addGroup('/sasisopa', function (RouteCollector $r) {

        $r->addRoute('GET', '', Route::auth(['SasisopaController', 'index']));

        $r->addRoute('GET', '/reporte/elemento1', Route::auth(['SasisopaController', 'elemento1']));
        $r->addRoute('GET', '/reporte/elemento2', Route::auth(['SasisopaController', 'elemento2']));
        $r->addRoute('GET', '/reporte/elemento3', Route::auth(['SasisopaController', 'elemento3']));
        $r->addRoute('GET', '/reporte/elemento3/pdf', Route::auth(['SasisopaController', 'calendarioRequisitosLegales']));
        $r->addRoute('GET', '/reporte/elemento5', Route::auth(['SasisopaController', 'elemento5']));
        $r->addRoute('GET', '/reporte/elemento6', Route::auth(['SasisopaController', 'elemento6']));
        $r->addRoute('GET', '/reporte/elemento7', Route::auth(['SasisopaController', 'elemento7']));
        $r->addRoute('GET', '/reporte/elemento10', Route::auth(['SasisopaController', 'elemento10']));
        $r->addRoute('GET', '/reporte/elemento12', Route::auth(['SasisopaController', 'elemento12']));
        $r->addRoute('GET', '/reporte/elemento13', Route::auth(['SasisopaController', 'elemento13']));
        $r->addRoute('GET', '/reporte/elemento14', Route::auth(['SasisopaController', 'elemento14']));
        $r->addRoute('GET', '/reporte/elemento15', Route::auth(['SasisopaController', 'elemento15']));
        $r->addRoute('GET', '/reporte/elemento16', Route::auth(['SasisopaController', 'elemento16']));
        $r->addRoute('GET', '/reporte/elemento16/grupo/{id:\d+}', Route::auth(['SasisopaController', 'grupoInterdisciplinario']));
        $r->addRoute('GET', '/reporte/elemento16/tercer/{id:\d+}', Route::auth(['SasisopaController', 'tercerAutorizado']));
        $r->addRoute('GET', '/reporte/elemento17', Route::auth(['SasisopaController', 'elemento17']));
        $r->addRoute('GET', '/reporte/elemento18', Route::auth(['SasisopaController', 'elemento18']));

        $r->addRoute('GET', '/reporte/{fechainicio}/{fechatermino}', Route::auth(['SasisopaController', 'reporte']));

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
        $r->addRoute('GET', '/requisitos-legales/datatable-detalle/{nGobierno:[a-zA-Z0-9\-]+}/{modulo:\d+}', Route::auth(['RequisitosLegalesController', 'datatableDetalle']));
        $r->addRoute('POST', '/requisitos-legales/delete-detalle', Route::auth(['RequisitosLegalesController', 'deleteDetalle']));

        $r->addRoute('GET', '/requisitos-legales/permisos/{nGobierno:[a-zA-Z0-9\-]+}/{sgm:\d+}', Route::auth(['RequisitosLegalesController', 'getPermisos']));
        $r->addRoute('GET', '/requisitos-legales/permisos/{nGobierno:[a-zA-Z0-9\-]+}/{sgm:\d+}/{idActual:\d+}', Route::auth(['RequisitosLegalesController', 'getPermisos']));
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
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa', Route::auth(['CapacitacionExternaController', 'pdfCapacitacionExternaCompleto']));

        // Elemento 7
        $r->addRoute('GET', '/comunicacion-participacion-consulta', Route::auth(['ComunicacionParticipacionConsultaController', 'index']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/datatable-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'datatableRegistroComunicacion']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/create-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'createRegistroComunicacion']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/delete-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'deleteRegistroComunicacion']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/update-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'updateRegistroComunicacion']));

        $r->addRoute('POST', '/comunicacion-participacion-consulta/create-evidencia', Route::auth(['ComunicacionParticipacionConsultaController', 'createEvidencia']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/get-evidencias/{id}', Route::auth(['ComunicacionParticipacionConsultaController', 'getEvidencias']));
        $r->addRoute('POST', '/comunicacion-participacion-consulta/delete-evidencia', Route::auth(['ComunicacionParticipacionConsultaController', 'deleteEvidencia']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/get-detalle-comunicacion/{id}', Route::auth(['ComunicacionParticipacionConsultaController', 'getDetalleComunicacion']));
        $r->addRoute('GET', '/comunicacion-participacion-consulta/pdf-registro-comunicacion', Route::auth(['ComunicacionParticipacionConsultaController', 'pdfRegistroComunicacion']));

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
        //------ Programa Anual Mantenimiento
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

        //--- Configuracion Bitacoras
        $r->addRoute('GET', '/control-actividades-procesos/configuracion-bitacora', Route::auth(['ConfiguracionBitacoraController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/datatable-configuracion-bitacora', Route::auth(['ConfiguracionBitacoraController', 'datatableConfiguracionBitacora']));
        $r->addRoute('GET', '/control-actividades-procesos/get-trabajador-autorizado', Route::auth(['ConfiguracionBitacoraController', 'getTrabajadorAutorizado']));
        $r->addRoute('POST', '/control-actividades-procesos/create-trabajador-autorizado', Route::auth(['ConfiguracionBitacoraController', 'createTrabajadorAutorizado']));
        $r->addRoute('POST', '/control-actividades-procesos/delete-trabajador-autorizado', Route::auth(['ConfiguracionBitacoraController', 'deleteTrabajadorAutorizado']));

        //---- Recepción y Descarga del Producto
        $r->addRoute('GET', '/control-actividades-procesos/recepcion-descarga-producto', Route::auth(['RecepcionDescargaProductoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/recepcion-descarga-producto/datatable', Route::auth(['RecepcionDescargaProductoController', 'datatable']));
        $r->addRoute('GET', '/control-actividades-procesos/recepcion-descarga-producto/pdf', Route::auth(['RecepcionDescargaProductoController', 'pdf']));


        //Mantenimiento Preventivo
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-preventivo', Route::auth(['MantenimientoPreventivoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-preventivo/datatable', Route::auth(['MantenimientoPreventivoController', 'datatable']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-preventivo/pdf', Route::auth(['MantenimientoPreventivoController', 'pdf']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-preventivo/get', Route::auth(['MantenimientoPreventivoController', 'get']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-preventivo/evidencias/{id:\d+}', Route::auth(['MantenimientoPreventivoController', 'evidencias']));
        $r->addRoute('POST', '/control-actividades-procesos/mantenimiento-preventivo/evidencias/create', Route::auth(['MantenimientoPreventivoController', 'createEvidencia']));
        $r->addRoute('POST', '/control-actividades-procesos/mantenimiento-preventivo/evidencias/delete', Route::auth(['MantenimientoPreventivoController', 'deleteEvidencia']));

        //-------- Detector de Humo
        $r->addRoute('GET', '/control-actividades-procesos/detector-humo', Route::auth(['DetectorHumoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/detector-humo/datatable', Route::auth(['DetectorHumoController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/detector-humo/create', Route::auth(['DetectorHumoController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/detector-humo/delete', Route::auth(['DetectorHumoController', 'delete']));

        //--------- Extintores
        $r->addRoute('GET', '/control-actividades-procesos/extintores', Route::auth(['ExtintoresController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/extintores/datatable', Route::auth(['ExtintoresController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/extintores/create', Route::auth(['ExtintoresController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/extintores/update/{id:\d+}', Route::auth(['ExtintoresController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/extintores/delete', Route::auth(['ExtintoresController', 'delete']));

        //------------Mantenimiento Correctivo
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-correctivo', Route::auth(['MantenimientoCorrectivoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-correctivo/datatable', Route::auth(['MantenimientoCorrectivoController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/mantenimiento-correctivo/update', Route::auth(['MantenimientoCorrectivoController', 'update']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-correctivo/pdf', Route::auth(['MantenimientoCorrectivoController', 'pdf']));
        $r->addRoute('GET', '/control-actividades-procesos/mantenimiento-correctivo/evidencias/{id:\d+}', Route::auth(['MantenimientoCorrectivoController', 'evidencias']));
        $r->addRoute('POST', '/control-actividades-procesos/mantenimiento-correctivo/evidencias/create', Route::auth(['MantenimientoCorrectivoController', 'createEvidencia']));
        $r->addRoute('POST', '/control-actividades-procesos/mantenimiento-correctivo/evidencias/delete', Route::auth(['MantenimientoCorrectivoController', 'deleteEvidencia']));

        // ------------ Calibracion de Equipos
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos', Route::auth(['CalibracionEquiposController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-tanques', Route::auth(['TanqueAlmacenamientoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-tanques/datatable', Route::auth(['TanqueAlmacenamientoController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-tanques/create', Route::auth(['TanqueAlmacenamientoController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-tanques/update', Route::auth(['TanqueAlmacenamientoController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-tanques/delete', Route::auth(['TanqueAlmacenamientoController', 'delete']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-dispensario', Route::auth(['DispensarioController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/datatable', Route::auth(['DispensarioController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/create', Route::auth(['DispensarioController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/delete', Route::auth(['DispensarioController', 'delete']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion', Route::auth(['SondasMedicionController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/datatable', Route::auth(['SondasMedicionController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/create', Route::auth(['SondasMedicionController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/update', Route::auth(['SondasMedicionController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/delete', Route::auth(['SondasMedicionController', 'delete']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron', Route::auth(['JarraPatronController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/datatable', Route::auth(['JarraPatronController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/create', Route::auth(['JarraPatronController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/update', Route::auth(['JarraPatronController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/delete', Route::auth(['JarraPatronController', 'delete']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos', Route::auth(['BitacoraCalibracionEquiposController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/datatable', Route::auth(['BitacoraCalibracionEquiposController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/create', Route::auth(['BitacoraCalibracionEquiposController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/upload-resultado', Route::auth(['BitacoraCalibracionEquiposController', 'uploadResultado']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/detalle/{id:\d+}', Route::auth(['BitacoraCalibracionEquiposController', 'detalle']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/pdf', Route::auth(['BitacoraCalibracionEquiposController', 'pdf']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/{id:\d+}', Route::auth(['CalibracionDispensarioController', 'index']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/update', Route::auth(['CalibracionDispensarioController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/delete', Route::auth(['CalibracionDispensarioController', 'delete']));
        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/get-dispensarios', Route::auth(['CalibracionDispensarioController', 'getDispensarios']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/create', Route::auth(['CalibracionDispensarioController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/finalizar', Route::auth(['CalibracionDispensarioController', 'finalizar']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/{id:\d+}', Route::auth(['CalibracionSondaController', 'index']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/update', Route::auth(['CalibracionSondaController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos//bitacora-calibracion-equipos-sonda/finalizar', Route::auth(['CalibracionSondaController', 'finalizar']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/{id:\d+}', Route::auth(['CalibracionJarraPatronController', 'index']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/update', Route::auth(['CalibracionJarraPatronController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/finalizar', Route::auth(['CalibracionJarraPatronController', 'finalizar']));

        $r->addRoute('GET', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/{id:\d+}', Route::auth(['CalibracionTanquesController', 'index']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/update', Route::auth(['CalibracionTanquesController', 'update']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/upload-resultado', Route::auth(['CalibracionTanquesController', 'uploadResultado']));
        $r->addRoute('POST', '/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/finalizar', Route::auth(['CalibracionTanquesController', 'finalizar']));

        $r->addRoute('GET', '/control-actividades-procesos/bitacora-dispensario', Route::auth(['BitacoraDispensarioController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-dispensario/datatable', Route::auth(['BitacoraDispensarioController', 'datatable']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-dispensario/catalogos', Route::auth(['BitacoraDispensarioController', 'catalogos']));
        $r->addRoute('POST', '/control-actividades-procesos/bitacora-dispensario/create', Route::auth(['BitacoraDispensarioController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/bitacora-dispensario/delete', Route::auth(['BitacoraDispensarioController', 'delete']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-dispensario/excel', Route::auth(['BitacoraDispensarioController', 'excel']));

        $r->addRoute('GET', '/control-actividades-procesos/bitacora-residuos-peligrosos', Route::auth(['BitacoraResiduosController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-residuos-peligrosos/datatable', Route::auth(['BitacoraResiduosController', 'datatable']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-residuos-peligrosos/pdf', Route::auth(['BitacoraResiduosController', 'pdf']));

        $r->addRoute('GET', '/control-actividades-procesos/bitacora-mantenimiento', Route::auth(['BitacoraMantenimientoController', 'index']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-mantenimiento-quincenal', Route::auth(['BitacoraMantenimientoController', 'mantenimientoQuincenal']));
        $r->addRoute('GET', '/control-actividades-procesos/bitacora-mantenimiento-quincenal/datatable', Route::auth(['BitacoraMantenimientoController', 'datatable']));
        $r->addRoute('POST', '/control-actividades-procesos/bitacora-mantenimiento-quincenal/create', Route::auth(['BitacoraMantenimientoController', 'create']));
        $r->addRoute('POST', '/control-actividades-procesos/bitacora-mantenimiento-quincenal/delete', Route::auth(['BitacoraMantenimientoController', 'delete']));
        $r->addRoute('POST', '/control-actividades-procesos/bitacora-mantenimiento-quincenal/update', Route::auth(['BitacoraMantenimientoController', 'update']));

        // Elemento 11
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento', Route::auth(['IntegridadMecanicaController', 'index']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/datatable-equipo-critico', Route::auth(['IntegridadMecanicaController', 'datatableEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/delete-equipo-critico', Route::auth(['IntegridadMecanicaController', 'deleteEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/baja-equipo-critico', Route::auth(['IntegridadMecanicaController', 'bajaEquipoCritico']));
        $r->addRoute('POST', '/integridad-mecanica-aseguramiento/create-equipo-critico', Route::auth(['IntegridadMecanicaController', 'createEquipoCritico']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/pdf-equipo-critico', Route::auth(['IntegridadMecanicaController', 'pdfEquipoCritico']));
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento/bitacoras', Route::auth(['IntegridadMecanicaController', 'bitacoras']));

        // Elemento 12
        $r->addRoute('GET', '/seguridad-contratistas', Route::auth(['SeguridadContratistasController', 'index']));
        $r->addRoute('GET', '/seguridad-contratistas/datatable', Route::auth(['SeguridadContratistasController', 'datatable']));
        $r->addRoute('POST', '/seguridad-contratistas/create', Route::auth(['SeguridadContratistasController', 'create']));
        $r->addRoute('POST', '/seguridad-contratistas/update', Route::auth(['SeguridadContratistasController', 'update']));
        $r->addRoute('POST', '/seguridad-contratistas/delete', Route::auth(['SeguridadContratistasController', 'delete']));

        $r->addRoute('GET', '/seguridad-contratistas/formato12/{id:\d+}', Route::auth(['SeguridadContratistasController', 'formato12']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/update', Route::auth(['SeguridadContratistasController', 'updateFormato12']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/trabajador/create', Route::auth(['SeguridadContratistasController', 'createTrabajador']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/trabajador/delete', Route::auth(['SeguridadContratistasController', 'deleteTrabajador']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/encargado/create', Route::auth(['SeguridadContratistasController', 'createEncargado']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/encargado/delete', Route::auth(['SeguridadContratistasController', 'deleteTrabajador']));
        $r->addRoute('POST', '/seguridad-contratistas/formato12/procedimiento/update', Route::auth(['SeguridadContratistasController', 'updateProcedimiento']));
        $r->addRoute('GET', '/seguridad-contratistas/formato12/pdf/{id:\d+}', Route::auth(['SeguridadContratistasController', 'pdfFormato12']));

        $r->addRoute('GET', '/seguridad-contratistas/formato13/{id:\d+}', Route::auth(['SeguridadContratistasController', 'formato13']));

        $r->addRoute('GET', '/seguridad-contratistas/formato14/{id:\d+}', Route::auth(['SeguridadContratistasController', 'formato14']));
        $r->addRoute('POST', '/seguridad-contratistas/formato14/update', Route::auth(['SeguridadContratistasController', 'updateFormato14']));

        $r->addRoute('GET', '/seguridad-contratistas/formato15/{id:\d+}', Route::auth(['SeguridadContratistasController', 'formato15']));
        $r->addRoute('POST', '/seguridad-contratistas/formato15/update', Route::auth(['SeguridadContratistasController', 'updateFormato15']));
        $r->addRoute('GET', '/seguridad-contratistas/formato15/pdf/{id:\d+}', Route::auth(['SeguridadContratistasController', 'pdfFormato15']));

        $r->addRoute('GET', '/seguridad-contratistas/carta-responsiva/id/{id:\d+}', Route::auth(['SeguridadContratistasController', 'cartaResponsiva']));
        $r->addRoute('POST', '/seguridad-contratistas/carta-responsiva/update', Route::auth(['SeguridadContratistasController', 'updateCartaResponsiva']));
        $r->addRoute('GET', '/seguridad-contratistas/carta-responsiva/pdf/{id:\d+}', Route::auth(['SeguridadContratistasController', 'pdfCartaResponsiva']));

        // Elemento 13
        $r->addRoute('GET', '/preparacion-emergencias', Route::auth(['PreparacionEmergenciasController', 'index']));

        $r->addRoute('GET', '/preparacion-emergencias/protocolo/get', Route::auth(['PreparacionEmergenciasController', 'protocoloGet']));
        $r->addRoute('POST', '/preparacion-emergencias/protocolo/create', Route::auth(['PreparacionEmergenciasController', 'protocoloCreate']));
        $r->addRoute('POST', '/preparacion-emergencias/protocolo/update', Route::auth(['PreparacionEmergenciasController', 'protocoloUpdate']));
        $r->addRoute('POST', '/preparacion-emergencias/protocolo/delete', Route::auth(['PreparacionEmergenciasController', 'protocoloDelete']));

        $r->addRoute('GET', '/preparacion-emergencias/protocolo/anexos/get/{id:\d+}', Route::auth(['PreparacionEmergenciasController', 'anexosGet']));
        $r->addRoute('POST', '/preparacion-emergencias/protocolo/anexos/create', Route::auth(['PreparacionEmergenciasController', 'anexoCreate']));
        $r->addRoute('POST', '/preparacion-emergencias/protocolo/anexos/delete', Route::auth(['PreparacionEmergenciasController', 'anexoDelete']));


        $r->addRoute('GET', '/preparacion-emergencias/telefonos/get', Route::auth(['PreparacionEmergenciasController', 'telefonosGet']));
        $r->addRoute('POST', '/preparacion-emergencias/telefonos/create', Route::auth(['PreparacionEmergenciasController', 'telefonosCreate']));
        $r->addRoute('POST', '/preparacion-emergencias/telefonos/update', Route::auth(['PreparacionEmergenciasController', 'telefonosUpdate']));
        $r->addRoute('POST', '/preparacion-emergencias/telefonos/delete', Route::auth(['PreparacionEmergenciasController', 'telefonosDelete']));

        $r->addRoute('GET', '/preparacion-emergencias/simulacro/datatable', Route::auth(['PreparacionEmergenciasController', 'simulacroDatatable']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/create', Route::auth(['PreparacionEmergenciasController', 'simulacroCreate']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/update', Route::auth(['PreparacionEmergenciasController', 'simulacroUpdate']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/delete', Route::auth(['PreparacionEmergenciasController', 'simulacroDelete']));
        $r->addRoute('GET', '/preparacion-emergencias/simulacro/pdf', Route::auth(['PreparacionEmergenciasController', 'simulacroPdf']));

        $r->addRoute('GET', '/preparacion-emergencias/simulacro/personal/get/{id:\d+}', Route::auth(['PreparacionEmergenciasController', 'personalGet']));
        $r->addRoute('GET', '/preparacion-emergencias/simulacro/personal/usuarios/{id:\d+}', Route::auth(['PreparacionEmergenciasController', 'personalUsuarios']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/personal/create', Route::auth(['PreparacionEmergenciasController', 'personalCreate']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/personal/delete', Route::auth(['PreparacionEmergenciasController', 'personalDelete']));

        $r->addRoute('GET', '/preparacion-emergencias/simulacro/resumen/get/{id:\d+}', Route::auth(['PreparacionEmergenciasController', 'resumenGet']));
        $r->addRoute('POST', '/preparacion-emergencias/simulacro/resumen/create', Route::auth(['PreparacionEmergenciasController', 'resumenCreate']));

        $r->addRoute('POST', '/preparacion-emergencias/simulacro/evaluacion/create', Route::auth(['PreparacionEmergenciasController', 'evaluacionCreate']));


        // Elemento 14
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion', Route::auth(['MonitoreoVerificacionEvaluacionController', 'index']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/buscar-indicadores', Route::auth(['MonitoreoVerificacionEvaluacionController', 'buscarIndicadores']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/{id:\d+}', Route::auth(['MonitoreoVerificacionEvaluacionController', 'revisionResultadoPdf']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/implementacion-sa', Route::auth(['ImplementacionSAController', 'index']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/implementacion-sa/datatable', Route::auth(['ImplementacionSAController', 'datatable']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/implementacion-sa/create', Route::auth(['ImplementacionSAController', 'create']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/implementacion-sa/update', Route::auth(['ImplementacionSAController', 'update']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/implementacion-sa/get/{id:\d+}', Route::auth(['ImplementacionSAController', 'get']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/ventas-mes', Route::auth(['MonitoreoVerificacionEvaluacionController', 'indexVentasMes']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/ventas-mes/get', Route::auth(['MonitoreoVerificacionEvaluacionController', 'getVentasMes']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos', Route::auth(['CalibracionVerificacionMantenimientoController', 'index']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/get-equipos-calibracion', Route::auth(['CalibracionVerificacionMantenimientoController', 'getEquiposCalibracion']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-equipos-calibracion', Route::auth(['CalibracionVerificacionMantenimientoController', 'pdfEquiposCalibracion']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/get-calendario-calibracion', Route::auth(['CalibracionVerificacionMantenimientoController', 'getCalendarioCalibracion']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/pdf-calendario-calibracion', Route::auth(['CalibracionVerificacionMantenimientoController', 'pdfCalendarioCalibracion']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales', Route::auth(['EvaluacionRequisitosLegalesController', 'index']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf', Route::auth(['EvaluacionRequisitosLegalesController', 'pdf']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/datatable', Route::auth(['EvaluacionRequisitosLegalesController', 'datatable']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/create', Route::auth(['EvaluacionRequisitosLegalesController', 'create']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/delete', Route::auth(['EvaluacionRequisitosLegalesController', 'delete']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos', Route::auth(['AtencionHallazgoController', 'index']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/datatable', Route::auth(['AtencionHallazgoController', 'datatable']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/create', Route::auth(['AtencionHallazgoController', 'create']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/delete', Route::auth(['AtencionHallazgoController', 'delete']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/pdf/{id:\d+}', Route::auth(['AtencionHallazgoController', 'pdf']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/descargar-programa-implementacion-s-a', Route::auth(['AtencionHallazgoController', 'descargarProgramaImplementacion']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/nuevo/{id:\d+}', Route::auth(['AtencionHallazgoNuevoController', 'index']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/update-encabezados', Route::auth(['AtencionHallazgoNuevoController', 'updateEncabezados']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/detalle/{id:\d+}', Route::auth(['AtencionHallazgoNuevoController', 'detalle']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/delete-hallazgo', Route::auth(['AtencionHallazgoNuevoController', 'deleteHallazgo']));
        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/sasisopa', Route::auth(['AtencionHallazgoNuevoController', 'sasisopaList']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/create-detalle', Route::auth(['AtencionHallazgoNuevoController', 'createDetalle']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/update-detalle', Route::auth(['AtencionHallazgoNuevoController', 'updateDetalle']));

        $r->addRoute('GET', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencias/{id:\d+}', Route::auth(['AtencionHallazgoNuevoController', 'evidencias']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencia/create', Route::auth(['AtencionHallazgoNuevoController', 'createEvidencia']));
        $r->addRoute('POST', '/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencia/delete', Route::auth(['AtencionHallazgoNuevoController', 'deleteEvidencia']));

        // Elemento 15
        $r->addRoute('GET', '/auditorias', Route::auth(['AuditoriasController', 'index']));
        $r->addRoute('GET', '/auditorias/programa', Route::auth(['AuditoriasController', 'programa']));
        $r->addRoute('GET', '/auditorias/programa/formato', Route::auth(['AuditoriasController', 'formatoAuditorias']));
        $r->addRoute('GET', '/auditorias/programa/pdf/{yearInicio:\d+}/{yearFin:\d+}', Route::auth(['AuditoriasController', 'formatpPdfAuditorias']));

        $r->addRoute('GET', '/auditorias/interna', Route::auth(['AuditoriasController', 'interna']));
        $r->addRoute('GET', '/auditorias/interna/datatable', Route::auth(['AuditoriasController', 'datatable']));
        $r->addRoute('POST', '/auditorias/interna/create', Route::auth(['AuditoriasController', 'createInterna']));
        $r->addRoute('POST', '/auditorias/interna/delete', Route::auth(['AuditoriasController', 'deleteInterna']));
        $r->addRoute('POST', '/auditorias/interna/formato024', Route::auth(['AuditoriasController', 'uploadFormato024']));
        $r->addRoute('POST', '/auditorias/interna/formato025', Route::auth(['AuditoriasController', 'uploadFormato025']));
        $r->addRoute('GET', '/auditorias/interna/anexos', Route::auth(['AuditoriasController', 'anexos']));
        $r->addRoute('POST', '/auditorias/interna/anexos/create', Route::auth(['AuditoriasController', 'createAnexo']));

        $r->addRoute('GET', '/auditorias/externa', Route::auth(['AuditoriasController', 'externa']));
        $r->addRoute('GET', '/auditorias/externa/datatable', Route::auth(['AuditoriasController', 'datatableExterna']));
        $r->addRoute('POST', '/auditorias/externa/create', Route::auth(['AuditoriasController', 'createExterna']));
        $r->addRoute('POST', '/auditorias/externa/delete', Route::auth(['AuditoriasController', 'deleteExterna']));
        $r->addRoute('POST', '/auditorias/externa/formato024', Route::auth(['AuditoriasController', 'uploadExternaFormato024']));
        $r->addRoute('POST', '/auditorias/externa/formato025', Route::auth(['AuditoriasController', 'uploadExternaFormato025']));
        $r->addRoute('GET', '/auditorias/externa/asea', Route::auth(['AuditoriasController', 'asea']));
        $r->addRoute('POST', '/auditorias/externa/asea/create', Route::auth(['AuditoriasController', 'createAsea']));

        // Elemento 16
        $r->addRoute('GET', '/investigacion-incidentes-accidentes', Route::auth(['IncidentesAccidentesController', 'index']));
        $r->addRoute('GET', '/investigacion-incidentes-accidentes/datatable', Route::auth(['IncidentesAccidentesController', 'datatableInvestigaciones']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/create', Route::auth(['IncidentesAccidentesController', 'createInvestigacion']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/delete', Route::auth(['IncidentesAccidentesController', 'deleteInvestigacion']));

        $r->addRoute('GET', '/investigacion-incidentes-accidentes/grupo', Route::auth(['IncidentesAccidentesController', 'grupoInterdiciplinario']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/grupo/create', Route::auth(['IncidentesAccidentesController', 'createGrupoInterdiciplinario']));

        $r->addRoute('POST', '/investigacion-incidentes-accidentes/formato026', Route::auth(['IncidentesAccidentesController', 'uploadFormato026']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/formatoTercer', Route::auth(['IncidentesAccidentesController', 'uploadFormatoTercer']));
        $r->addRoute('GET', '/investigacion-incidentes-accidentes/pdf', Route::auth(['IncidentesAccidentesController', 'pdfInvestigacion']));


        $r->addRoute('GET', '/investigacion-incidentes-accidentes/no/datatableNoAccidentes', Route::auth(['IncidentesAccidentesController', 'datatableNoAccidentes']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/no/create', Route::auth(['IncidentesAccidentesController', 'createNoAccidente']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/no/update', Route::auth(['IncidentesAccidentesController', 'updateNoAccidente']));
        $r->addRoute('POST', '/investigacion-incidentes-accidentes/no/delete', Route::auth(['IncidentesAccidentesController', 'deleteNoAccidentes']));
        $r->addRoute('GET', '/investigacion-incidentes-accidentes/no/pdf', Route::auth(['IncidentesAccidentesController', 'pdfNoAccidentes']));

        // Elemento 17
        $r->addRoute('GET', '/revision-resultados', Route::auth(['RevisionResultadosController', 'index']));
        $r->addRoute('GET', '/revision-resultados/datatable', Route::auth(['RevisionResultadosController', 'datatable']));
        $r->addRoute('POST', '/revision-resultados/create', Route::auth(['RevisionResultadosController', 'create']));
        $r->addRoute('POST', '/revision-resultados/update', Route::auth(['RevisionResultadosController', 'update']));
        $r->addRoute('POST', '/revision-resultados/delete', Route::auth(['RevisionResultadosController', 'delete']));

        // Elemento 18
        $r->addRoute('GET', '/informes-desempeno', Route::auth(['InformeDesempenoController', 'index']));
        $r->addRoute('GET', '/informes-desempeno/evaluacion/datatable', Route::auth(['InformeDesempenoController', 'datatableEvaluacion']));
        $r->addRoute('POST', '/informes-desempeno/evaluacion/create', Route::auth(['InformeDesempenoController', 'createEvaluacion']));
        $r->addRoute('POST', '/informes-desempeno/evaluacion/update', Route::auth(['InformeDesempenoController', 'updateEvaluacion']));
        $r->addRoute('POST', '/informes-desempeno/evaluacion/delete', Route::auth(['InformeDesempenoController', 'deleteEvaluacion']));

        $r->addRoute('GET', '/informes-desempeno/implementacion/datatable', Route::auth(['InformeDesempenoController', 'datatableImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/create', Route::auth(['InformeDesempenoController', 'createImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/delete', Route::auth(['InformeDesempenoController', 'deleteImplementacion']));
        $r->addRoute('GET', '/informes-desempeno/implementacion/pdf/{id:\d+}', Route::auth(['InformeDesempenoController', 'pdfImplementacion']));

        $r->addRoute('GET', '/informes-desempeno/implementacion/editar/{id:\d+}', Route::auth(['InformeDesempenoController', 'indexEditar']));
        $r->addRoute('GET', '/informes-desempeno/implementacion/editar/datatable/{id:\d+}', Route::auth(['InformeDesempenoController', 'editarImplementacionDatatable']));

        $r->addRoute('POST', '/informes-desempeno/implementacion/editar/fecha-reporte', Route::auth(['InformeDesempenoController', 'updateFechaReporte']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/editar/fecha-implementacion', Route::auth(['InformeDesempenoController', 'updateFechaImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/editar/descripcion', Route::auth(['InformeDesempenoController', 'updateDescripcionImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/editar/observacion', Route::auth(['InformeDesempenoController', 'updateObservacionImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/editar/informacion', Route::auth(['InformeDesempenoController', 'updateInformacionImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/create/puesto', Route::auth(['InformeDesempenoController', 'createPuestoImplementacion']));
        $r->addRoute('POST', '/informes-desempeno/implementacion/delete/puesto', Route::auth(['InformeDesempenoController', 'deletePuestoImplementacion']));

        // Otros
        $r->addRoute('GET', '/calendario', Route::auth(['CalendarioController', 'index']));
        $r->addRoute('GET', '/calendario/eventos', Route::auth(['CalendarioController', 'eventos']));
        $r->addRoute('GET', '/calendario/dia', Route::auth(['CalendarioController', 'dia']));
        $r->addRoute('GET', '/calendario/detalle', Route::auth(['CalendarioController', 'detalle']));
        $r->addRoute('POST', '/calendario/actividad/abrir', Route::auth(['CalendarioController', 'abrirActividad']));
        $r->addRoute('POST', '/calendario/curso/reagendar', Route::auth(['CalendarioController', 'reagendar']));
        $r->addRoute('GET', '/calendario/actividades', Route::auth(['CalendarioController', 'actividadesDisponibles']));
        $r->addRoute('POST', '/calendario/actividad/create', Route::auth(['CalendarioController', 'createActividad']));

        $r->addRoute('GET', '/programa-implementacion', Route::auth(['SasisopaController', 'programaImplementacion']));

        //--------- Comunicados
        $r->addRoute('GET', '/comunicados', Route::auth(['ComunicadosController', 'index']));
        $r->addRoute('GET', '/comunicados/datatable', Route::auth(['ComunicadosController', 'datatable']));
        $r->addRoute('POST', '/comunicados/create', Route::auth(['ComunicadosController', 'create']));
        $r->addRoute('POST', '/comunicados/delete', Route::auth(['ComunicadosController', 'delete']));
        //------------------------------------------------

        //----------- CONSULTA TU SASISOPA
        $r->addRoute('GET', '/consulta', Route::auth(['SasisopaController', 'consultaSasisopa']));
        $r->addRoute('GET', '/consulta/datatable', Route::auth(['SasisopaController', 'datatableConsulta']));
        //------------------------------------------------

        $r->addRoute('GET', '/reporte-diario', Route::auth(['ReporteDiarioController', 'index']));
        $r->addRoute('GET', '/reporte-diario/meses', Route::auth(['ReporteDiarioController', 'meses']));
        $r->addRoute('GET', '/reporte-diario/{mes:\d+}/{year:\d+}', Route::auth(['ReporteDiarioController', 'reporteMes']));
        $r->addRoute('GET', '/reporte-diario/pdf', Route::auth(['ReporteDiarioController', 'reportePdf']));
        $r->addRoute('GET', '/reporte-diario/mes/datatable', Route::auth(['ReporteDiarioController', 'datatableMes']));

        $r->addRoute('GET', '/reporte-diario/facturas/{year:\d+}', Route::auth(['ReporteDiarioController', 'facturas']));
        $r->addRoute('GET', '/reporte-diario/facturas/get/{year:\d+}', Route::auth(['ReporteDiarioController', 'getFacturas']));
        $r->addRoute('POST', '/reporte-diario/facturas/guardar', Route::auth(['ReporteDiarioController', 'guardarFacturas']));

        $r->addRoute('GET', '/reporte-diario/mensajes/{id:\d+}/{fecha:\d+}', Route::auth(['ReporteDiarioController', 'getMensajes']));
        $r->addRoute('POST', '/reporte-diario/mensajes/create', Route::auth(['ReporteDiarioController', 'guardarMensaje']));

        $r->addRoute('GET', '/reporte-diario/nuevo/{mes:\d+}/{year:\d+}', Route::auth(['ReporteDiarioController', 'reporteMesNuevo']));
        $r->addRoute('GET', '/reporte-diario/editar/{idmes:\d+}/{idfecha:\d+}', Route::auth(['ReporteDiarioController', 'reporteMesEditar']));

        $r->addRoute('GET', '/reporte-diario/nuevo/base-reporte-diario', Route::auth(['ReporteDiarioController', 'baseReporteDiario']));
        $r->addRoute('GET', '/reporte-diario/editar/base/{idmes:\d+}/{fecha}', Route::auth(['ReporteDiarioController', 'baseReporteDiarioEditar']));

        $r->addRoute('POST', '/reporte-diario/nuevo/create', Route::auth(['ReporteDiarioController', 'createReporteDiario']));
        $r->addRoute('POST', '/reporte-diario/editar/update', Route::auth(['ReporteDiarioController', 'updateReporteDiario']));

        //------------------- CURSOS
        $r->addRoute('GET', '/cursos', Route::auth(['CursosController', 'cursosIndex']));
        $r->addRoute('GET', '/cursos/modulos/get', Route::auth(['CursosController', 'getModulos']));
        $r->addRoute('GET', '/cursos/pendientes/get', Route::auth(['CursosController', 'getCursosPendientes']));
        $r->addRoute('GET', '/cursos/iniciar/{id:\d+}', Route::auth(['CursosController', 'cursosIniciar']));
        $r->addRoute('GET', '/cursos/evaluacion/{id:\d+}', Route::auth(['CursosController', 'cursosEvaluacion']));
        $r->addRoute('GET', '/cursos/evaluacion/get/{id:\d+}', Route::auth(['CursosController', 'getEvaluacion']));
        $r->addRoute('POST', '/cursos/evaluacion/respuesta', Route::auth(['CursosController', 'guardarRespuesta']));
        $r->addRoute('POST', '/cursos/evaluacion/finalizar', Route::auth(['CursosController', 'finalizarEvaluacion']));

        $r->addRoute('GET', '/cursos/modulos/{id:\d+}', Route::auth(['CursosController', 'cursosModulos']));
        $r->addRoute('GET', '/cursos/modulos/temas/{idModulo:\d+}', Route::auth(['CursosController', 'detalleTema']));

        //----------------- CAMBIO DE PRECIO --------------
        $r->addRoute('GET', '/cambio-precio', Route::auth(['CambioPrecioController', 'index']));
        $r->addRoute('GET', '/cambio-precio/datetable', Route::auth(['CambioPrecioController', 'datetable']));
        $r->addRoute('POST', '/cambio-precio/create', Route::auth(['CambioPrecioController', 'create']));
        $r->addRoute('POST', '/cambio-precio/delete', Route::auth(['CambioPrecioController', 'delete']));
    });

    // ---------------- SGM ----------------
    $r->addGroup('/sgm', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['SgmController', 'index']));

        $r->addRoute('GET', '/calendario', Route::auth(['CalendarioController', 'sgmIndex']));
        $r->addRoute('GET', '/cursos', Route::auth(['CursosController', 'cursosSgmIndex']));


        //-------------- Resision ----------
        $r->addRoute('GET', '/revision/datatable', Route::auth(['SgmRevisionController', 'datatable']));
        $r->addRoute('POST', '/revision/create', Route::auth(['SgmRevisionController', 'createRevision']));
        $r->addRoute('POST', '/revision/delete', Route::auth(['SgmRevisionController', 'deleteRevision']));
        $r->addRoute('GET', '/revision/pdf/{id:\d+}', Route::auth(['SgmRevisionController', 'pdfRevision']));
        $r->addRoute('GET', '/revision/editar/{id:\d+}', Route::auth(['SgmRevisionController', 'revisionIndex']));
        $r->addRoute('GET', '/revision/detalle/{id:\d+}', Route::auth(['SgmRevisionController', 'detalleRevision']));
        $r->addRoute('POST', '/revision/update', Route::auth(['SgmRevisionController', 'updateRevision']));
        $r->addRoute('POST', '/revision/update-detalle', Route::auth(['SgmRevisionController', 'updateRevisionDetalle']));
        $r->addRoute('POST', '/revision/finalizar', Route::auth(['SgmRevisionController', 'finalizarRevision']));
        //-------------- Resision ----------

        //---------- 1. Estructura del sistema de Medicion --------------------------------------
        $r->addRoute('GET', '/estructura-sistema-medicion', Route::auth(['SgmEstructuraController', 'index']));
        //---------- 1. Estructura del sistema de Medicion --------------------------------------

        //---------- 2. CONTROL DEL DOCUMENTAL DEL SISTEMA DE GESTION DE MEDICIÓN --------------------
        $r->addRoute('GET', '/control-documental-sistema-gestion-medicion', Route::auth(['SgmControlDocumentalController', 'index']));
        $r->addRoute('GET', '/control-documental-sistema-gestion-medicion/documentos', Route::auth(['SgmControlDocumentalController', 'documentos']));
        $r->addRoute('GET', '/control-documental-sistema-gestion-medicion/pdf', Route::auth(['SgmControlDocumentalController', 'pdf']));
        //---------- 2. CONTROL DEL DOCUMENTAL DEL SISTEMA DE GESTION DE MEDICIÓN --------------------

        //---------- 3. RESPONSABILIDADES DE LA DIRECCION ---------------------------------------------
        $r->addRoute('GET', '/responsabilidades-direccion', Route::auth(['SgmResponsabilidadDireccionController', 'index']));
        $r->addRoute('GET', '/responsabilidades-direccion/datatable-politica', Route::auth(['SgmResponsabilidadDireccionController', 'politicas']));
        $r->addRoute('POST', '/responsabilidades-direccion/delete-politica', Route::auth(['SgmResponsabilidadDireccionController', 'deletePolitica']));
        $r->addRoute('GET', '/responsabilidades-direccion/politica-sgm', Route::auth(['SgmResponsabilidadDireccionController', 'politicaIndex']));
        $r->addRoute('GET', '/responsabilidades-direccion/politica-sgm/detalle', Route::auth(['SgmResponsabilidadDireccionController', 'detallePolitica']));
        $r->addRoute('POST', '/responsabilidades-direccion/politica-sgm/create', Route::auth(['SgmResponsabilidadDireccionController', 'guardarPolitica']));
        //---------- 3. RESPONSABILIDADES DE LA DIRECCION ---------------------------------------------

        //---------- 4. ESTABLECIMIENTO DE OBJETIVOS ENFOCADOS AL CLIENTE -----------------------------
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente', Route::auth(['SgmEstablecimientoController', 'index']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/datatable-objetivos', Route::auth(['SgmEstablecimientoController', 'tableObjetivos']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/objetivo-cliente', Route::auth(['SgmEstablecimientoController', 'objetivoIndex']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/detalle', Route::auth(['SgmEstablecimientoController', 'detalleObjetivo']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/create', Route::auth(['SgmEstablecimientoController', 'createObjetivo']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/delete', Route::auth(['SgmEstablecimientoController', 'deleteObjetivo']));

        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/datatable', Route::auth(['SgmEstablecimientoController', 'datatableSeguimientoObjetivo']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/create', Route::auth(['SgmEstablecimientoController', 'createSeguimientoObjetivo']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/delete', Route::auth(['SgmEstablecimientoController', 'deleteSeguimientoObjetivo']));

        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/{id:\d+}', Route::auth(['SgmEstablecimientoController', 'SeguimientoObjetivoIndex']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/{id:\d+}/detalle', Route::auth(['SgmEstablecimientoController', 'detalle']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/asistente/create', Route::auth(['SgmEstablecimientoController', 'agregarAsistentes']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/asistente/delete', Route::auth(['SgmEstablecimientoController', 'eliminarAsistente']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/update', Route::auth(['SgmEstablecimientoController', 'actualizarCampo']));
        $r->addRoute('POST', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/finalizar', Route::auth(['SgmEstablecimientoController', 'finalizar']));
        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/pdf/{id:\d+}', Route::auth(['SgmEstablecimientoController', 'pdf']));
        //---------- 4. ESTABLECIMIENTO DE OBJETIVOS ENFOCADOS AL CLIENTE -----------------------------

        //---------- 5. NORMATIVIDAD APLICABLE A MEDICIONES ----------------------------------------------
        $r->addRoute('GET', '/normatividad-aplicable-mediciones', Route::auth(['SgmNormatividadController', 'index']));
        $r->addRoute('GET', '/normatividad-aplicable-mediciones/datatable-inventario', Route::auth(['SgmNormatividadController', 'datatableInventario']));
        $r->addRoute('POST', '/normatividad-aplicable-mediciones/create-inventario', Route::auth(['SgmNormatividadController', 'createInventario']));
        $r->addRoute('POST', '/normatividad-aplicable-mediciones/delete-inventario', Route::auth(['SgmNormatividadController', 'deleteInventario']));
        $r->addRoute('GET', '/normatividad-aplicable-mediciones/inventario-normatividad/pdf', Route::auth(['SgmNormatividadController', 'pdfInventario']));

        $r->addRoute('GET', '/normatividad-aplicable-mediciones/pdf-requisito-legal', Route::auth(['SgmNormatividadController', 'pdfRequisitoLegal']));
        $r->addRoute('GET', '/normatividad-aplicable-mediciones/requisito-legal-sgm', Route::auth(['SgmNormatividadController', 'requisitoLegal']));
        //---------- 5. NORMATIVIDAD APLICABLE A MEDICIONES ----------------------------------------------

        //--------- 6. Gestion de los Recursos ------------------------------------------------------------
        $r->addRoute('GET', '/gestion-recursos', Route::auth(['SgmGestionRecursosController', 'index']));
        $r->addRoute('GET', '/gestion-recursos/responsable/table', Route::auth(['SgmGestionRecursosController', 'table']));
        $r->addRoute('POST', '/gestion-recursos/responsable/create', Route::auth(['SgmGestionRecursosController', 'create']));
        $r->addRoute('POST', '/gestion-recursos/responsable/delete', Route::auth(['SgmGestionRecursosController', 'delete']));
        $r->addRoute('GET', '/gestion-recursos/responsable/pdf/{id:\d+}', Route::auth(['SgmGestionRecursosController', 'pdf']));

        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-interna', Route::auth(['SgmCapacitacionInternaController', 'index']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-interna/datatable/{year:\d+}', Route::auth(['SgmCapacitacionInternaController', 'datatable']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-interna/reconocimiento/{id:\d+}', Route::auth(['SgmCapacitacionInternaController', 'reconocimiento']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-interna/pdf/{year:\d+}', Route::auth(['SgmCapacitacionInternaController', 'pdf']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-interna/reconocimiento/year/{year:\d+}', Route::auth(['SgmCapacitacionInternaController', 'reconocimientoYear']));

        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-externa', Route::auth(['SgmCapacitacionExternaController', 'index']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/create', Route::auth(['SgmCapacitacionExternaController', 'create']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/update', Route::auth(['SgmCapacitacionExternaController', 'update']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/delete', Route::auth(['SgmCapacitacionExternaController', 'delete']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-externa/datatable/{year:\d+}', Route::auth(['SgmCapacitacionExternaController', 'datatable']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-externa/detalle/{id:\d+}', Route::auth(['SgmCapacitacionExternaController', 'detalle']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/personal/create', Route::auth(['SgmCapacitacionExternaController', 'guardarPersonal']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/personal/delete', Route::auth(['SgmCapacitacionExternaController', 'deletePersonal']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/evidencia/create', Route::auth(['SgmCapacitacionExternaController', 'createEvidencia']));
        $r->addRoute('POST', '/gestion-recursos/programa-capacitacion-externa/evidencia/delete', Route::auth(['SgmCapacitacionExternaController', 'deleteEvidencia']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-externa/pdf/{year:\d+}', Route::auth(['SgmCapacitacionExternaController', 'pdf']));

        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-induccion', Route::auth(['SgmCapacitacionInduccionController', 'index']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-induccion/datatable', Route::auth(['SgmCapacitacionInduccionController', 'datatable']));
        $r->addRoute('GET', '/gestion-recursos/programa-capacitacion-induccion/pdf', Route::auth(['SgmCapacitacionInduccionController', 'pdf']));

        $r->addRoute('GET', '/gestion-recursos/inventario-equipo', Route::auth(['SgmInventarioEquipoController', 'index']));
        $r->addRoute('GET', '/gestion-recursos/inventario-equipo/datatable', Route::auth(['SgmInventarioEquipoController', 'datatable']));
        $r->addRoute('POST', '/gestion-recursos/inventario-equipo/create', Route::auth(['SgmInventarioEquipoController', 'create']));
        $r->addRoute('POST', '/gestion-recursos/inventario-equipo/update', Route::auth(['SgmInventarioEquipoController', 'update']));
        $r->addRoute('POST', '/gestion-recursos/inventario-equipo/delete', Route::auth(['SgmInventarioEquipoController', 'delete']));
        $r->addRoute('GET', '/gestion-recursos/inventario-equipo/pdf', Route::auth(['SgmInventarioEquipoController', 'pdf']));
        $r->addRoute('POST', '/gestion-recursos/inventario-equipo/manual/create', Route::auth(['SgmInventarioEquipoController', 'createManual']));
        $r->addRoute('POST', '/gestion-recursos/inventario-equipo/manual/delete', Route::auth(['SgmInventarioEquipoController', 'deleteManual']));
        $r->addRoute('GET', '/gestion-recursos/inventario-equipo/detalle/{id:\d+}', Route::auth(['SgmInventarioEquipoController', 'detalleInventario']));
        $r->addRoute('GET', '/gestion-recursos/inventario-equipo/manuales/{id:\d+}', Route::auth(['SgmInventarioEquipoController', 'manuales']));

        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores', Route::auth(['SgmEvaluacionProveedoresController', 'index']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/data', Route::auth(['SgmEvaluacionProveedoresController', 'data']));
        $r->addRoute('POST', '/gestion-recursos/orden-servicio-evaluacion-proveedores/create', Route::auth(['SgmEvaluacionProveedoresController', 'create']));
        $r->addRoute('POST', '/gestion-recursos/orden-servicio-evaluacion-proveedores/update', Route::auth(['SgmEvaluacionProveedoresController', 'update']));
        $r->addRoute('POST', '/gestion-recursos/orden-servicio-evaluacion-proveedores/delete', Route::auth(['SgmEvaluacionProveedoresController', 'delete']));

        $r->addRoute('POST', '/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/update', Route::auth(['SgmEvaluacionProveedoresController', 'updateEvaluacion']));

        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/detalle/{id:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'detalle']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/detalle-completo/{id:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'detalleCompleto']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/pdf/{id:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'pdfOrdenServicio']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/detalle/{idOrden:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'detalleEvaluacion']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/pdf/{idOrden:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'pdfEvaluacion']));
        $r->addRoute('GET', '/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/detalle-completo/{idOrden:\d+}', Route::auth(['SgmEvaluacionProveedoresController', 'detalleCompletoEvaluacion']));
        //--------- 6. Gestion de los Recursos ------------------------------------------------------------

        //---------- 7. Procesos de medición -------------------------------------------------------------
        $r->addRoute('GET', '/procesos-medicion', Route::auth(['SgmProcesosMedicionController', 'index']));

        $r->addRoute('GET', '/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida', Route::auth(['SgmProcesosMedicionController', 'programacionAnualCalibracion']));
        $r->addRoute(
            'GET',
            '/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/table-programa-calibracion-patrones',
            Route::auth(['SgmProgramaCalibracionController', 'tableProgramaCalibracionPatrones'])
        );
        $r->addRoute(
            'GET',
            '/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/modal-programa-calibracion-patrones',
            Route::auth(['SgmProgramaCalibracionController', 'modalProgramaCalibracion'])
        );
        $r->addRoute(
            'POST',
            '/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/create-programa-calibracion-patrones',
            Route::auth(['SgmProgramaCalibracionController', 'createProgramaCalibracion'])
        );
        $r->addRoute(
            'GET',
            '/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/pdf/{year:\d+}/{formato:\d+}',
            Route::auth(['SgmProgramaCalibracionController', 'pdfProgramaCalibracion'])
        );


        $r->addRoute('GET', '/procesos-medicion/bitacora-calibracion-equipos', Route::auth(['SgmProcesosMedicionController', 'bitacoraCalibracionEquipos']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-calibracion-equipos/datatable', Route::auth(['SgmBitacoraCalibracionController', 'datatable']));

        $r->addRoute('POST', '/procesos-medicion/bitacora-calibracion-equipos/update', Route::auth(['SgmBitacoraCalibracionController', 'actualizarCampo']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-calibracion-equipos/update-resultado', Route::auth(['SgmBitacoraCalibracionController', 'actualizarResultado']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-calibracion-equipos/finalizar', Route::auth(['SgmBitacoraCalibracionController', 'finalizar']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-calibracion-equipos/{id:\d+}', Route::auth(['SgmBitacoraCalibracionController', 'editarBitacoraCalibracionEquipos']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-calibracion-equipos/detalle/{id:\d+}', Route::auth(['SgmBitacoraCalibracionController', 'obtenerBitacora']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-calibracion-equipos/pdf/{id:\d+}', Route::auth(['SgmBitacoraCalibracionController', 'pdfBitacora']));


        $r->addRoute('GET', '/procesos-medicion/programa-anual-verificacion-equipos', Route::auth(['SgmProcesosMedicionController', 'programacionAnualVerificacion']));
        $r->addRoute('POST', '/procesos-medicion/programa-anual-verificacion-equipos/delete', Route::auth(['SgmProgramaVerificacionController', 'deleteProgramacionAnualVerificacion']));
        $r->addRoute('GET', '/procesos-medicion/programa-anual-verificacion-equipos/pdf/{year:\d+}/{formato:\d+}', Route::auth(['SgmProgramaVerificacionController', 'pdfProgramacionAnualVerificacion']));


        $r->addRoute('GET', '/procesos-medicion/bitacora-verificacion-equipo-medicion', Route::auth(['SgmProcesosMedicionController', 'bitacoraVerificacionEquipos']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-verificacion-equipo-medicion/datatable', Route::auth(['SgmBitacoraVerificacionController', 'datatable']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-verificacion-equipo-medicion/actualizar-campo', Route::auth(['SgmBitacoraVerificacionController', 'actualizarCampo']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-verificacion-equipo-medicion/actualizar-resultado', Route::auth(['SgmBitacoraVerificacionController', 'actualizarResultado']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-verificacion-equipo-medicion/finalizar', Route::auth(['SgmBitacoraVerificacionController', 'finalizar']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-verificacion-equipo-medicion/create-manguera', Route::auth(['SgmBitacoraVerificacionController', 'agregarManguera']));
        $r->addRoute('POST', '/procesos-medicion/bitacora-verificacion-equipo-medicion/delete-manguera', Route::auth(['SgmBitacoraVerificacionController', 'eliminarManguera']));

        $r->addRoute('GET', '/procesos-medicion/bitacora-verificacion-equipo-medicion/{id:\d+}', Route::auth(['SgmBitacoraVerificacionController', 'editarBitacoraVerificacionEquipos']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-verificacion-equipo-medicion/detalle/{id:\d+}', Route::auth(['SgmBitacoraVerificacionController', 'obtenerBitacora']));
        $r->addRoute('GET', '/procesos-medicion/bitacora-verificacion-equipo-medicion/pdf/{id:\d+}', Route::auth(['SgmBitacoraVerificacionController', 'pdfBitacora']));

        //---------- 7. Procesos de medición -------------------------------------------------------------

        //--------- 8. GESTIÓN DE RIESGOS QUE IMPACTAN EN LA MEDICIÓN ------------------------------------
        $r->addRoute('GET', '/gestion-riesgos-impactan-medicion', Route::auth(['SgmController', 'gestionRiesgosImpactanMedicion']));
        //--------- 8. GESTIÓN DE RIESGOS QUE IMPACTAN EN LA MEDICIÓN ------------------------------------

        //--------- 9. ESTABLECIMIENTO Y SEGUIMIENTO CONFIRMACIÓN METROLÓGICA ----------------------------
        $r->addRoute('GET', '/establecimiento-seguimiento-confirmacion-metrologica', Route::auth(['SgmController', 'establecimientoSeguimientoConfirmacionMetrologica']));
        //--------- 9. ESTABLECIMIENTO Y SEGUIMIENTO CONFIRMACIÓN METROLÓGICA ----------------------------

        //--------- 10. Auditorias, Internas, externas y Atención de hallazgos
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos', Route::auth(['SgmAuditoriaInternaExternaController', 'index']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/table', Route::auth(['SgmAuditoriaInternaExternaController', 'table']));

        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/editar', Route::auth(['SgmPlanAuditoriaController', 'editar']));

        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/{id:\d+}', Route::auth(['SgmPlanAuditoriaController', 'index']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/{id:\d+}/data', Route::auth(['SgmPlanAuditoriaController', 'data']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/responsable/create', Route::auth(['SgmPlanAuditoriaController', 'createResponsable']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/responsable/delete', Route::auth(['SgmPlanAuditoriaController', 'deleteResponsable']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auditor/create', Route::auth(['SgmPlanAuditoriaController', 'createAuditor']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auditor/delete', Route::auth(['SgmPlanAuditoriaController', 'deleteAuditor']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auxiliar/create', Route::auth(['SgmPlanAuditoriaController', 'createAuxiliar']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auxiliar/delete', Route::auth(['SgmPlanAuditoriaController', 'deleteAuxiliar']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/agenda/create', Route::auth(['SgmPlanAuditoriaController', 'createAgenda']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/agenda/delete', Route::auth(['SgmPlanAuditoriaController', 'deleteAgenda']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/pdf/{id:\d+}', Route::auth(['SgmPlanAuditoriaController', 'pdf']));


        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/editar', Route::auth(['SgmReporteHallazgoAuditoriaController', 'editar']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/{id:\d+}', Route::auth(['SgmReporteHallazgoAuditoriaController', 'index']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/{id:\d+}/data', Route::auth(['SgmReporteHallazgoAuditoriaController', 'data']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/responsable/create', Route::auth(['SgmReporteHallazgoAuditoriaController', 'createResponsable']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/responsable/delete', Route::auth(['SgmReporteHallazgoAuditoriaController', 'deleteResponsable']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/entrevistador/create', Route::auth(['SgmReporteHallazgoAuditoriaController', 'agregarEntrevistado']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/entrevistador/delete', Route::auth(['SgmReporteHallazgoAuditoriaController', 'eliminarEntrevistado']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/equipoauditor/create', Route::auth(['SgmReporteHallazgoAuditoriaController', 'agregarEquipoAuditor']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/equipoauditor/delete', Route::auth(['SgmReporteHallazgoAuditoriaController', 'eliminarEquipoAuditor']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/resultado/update', Route::auth(['SgmReporteHallazgoAuditoriaController', 'actualizarResultado']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/conforme/create', Route::auth(['SgmReporteHallazgoAuditoriaController', 'agregarConforme']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/conforme/delete', Route::auth(['SgmReporteHallazgoAuditoriaController', 'eliminarConforme']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/mejora/create', Route::auth(['SgmReporteHallazgoAuditoriaController', 'agregarMejora']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/mejora/delete', Route::auth(['SgmReporteHallazgoAuditoriaController', 'eliminarMejora']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/pdf/{id:\d+}', Route::auth(['SgmReporteHallazgoAuditoriaController', 'pdf']));

        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/editar', Route::auth(['SgmPlanAtencionHallazgoController', 'editar']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/{id:\d+}', Route::auth(['SgmPlanAtencionHallazgoController', 'index']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/{id:\d+}/data', Route::auth(['SgmPlanAtencionHallazgoController', 'data']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/responsable/create', Route::auth(['SgmPlanAtencionHallazgoController', 'createResponsable']));
        $r->addRoute('POST', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/responsable/delete', Route::auth(['SgmPlanAtencionHallazgoController', 'deleteResponsable']));
        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/pdf/{id:\d+}', Route::auth(['SgmPlanAtencionHallazgoController', 'pdf']));

        //--------- 10. Auditorias, Internas, externas y Atención de hallazgos

        //--------- 11. EVALUACIÓN DEL CUMPLIMIENTO DE OBJETIVOS Y REVISIÓN POR LA DIRECCIÓN ----------------
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion', Route::auth(['SgmEvaluacionCumplimientoController', 'index']));
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion/datatable', Route::auth(['SgmEvaluacionCumplimientoController', 'datatable']));
        $r->addRoute('POST', '/evaluacion-cumplimiento-objetivos-revision-direccion/update', Route::auth(['SgmEvaluacionCumplimientoController', 'update']));
        $r->addRoute('POST', '/evaluacion-cumplimiento-objetivos-revision-direccion/asistentes', Route::auth(['SgmEvaluacionCumplimientoController', 'agregarAsistentes']));
        $r->addRoute('POST', '/evaluacion-cumplimiento-objetivos-revision-direccion/finalizar', Route::auth(['SgmEvaluacionCumplimientoController', 'finalizar']));
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion/editar/{id:\d+}', Route::auth(['SgmEvaluacionCumplimientoController', 'editarIndex']));
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion/pdf/{id:\d+}', Route::auth(['SgmEvaluacionCumplimientoController', 'pdf']));
        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion/editar/detalle/{id:\d+}', Route::auth(['SgmEvaluacionCumplimientoController', 'detalle']));
        $r->addRoute('POST', '/evaluacion-cumplimiento-objetivos-revision-direccion/asistentes/delete', Route::auth(['SgmEvaluacionCumplimientoController', 'eliminarAsistente']));
        //--------- 11. EVALUACIÓN DEL CUMPLIMIENTO DE OBJETIVOS Y REVISIÓN POR LA DIRECCIÓN ----------------

    });

    // ---------------- TELEGRAM / NOTIFICACIONES GLOBAL ----------------
    $r->addRoute('POST', '/telegram/webhook', ['TelegramWebhookController', 'handle']);
    $r->addRoute('GET', '/telegram/poll', Route::auth(['TelegramWebhookController', 'poll']));
    $r->addRoute('POST', '/token-telegram/status', Route::auth(['TokenTelegramController', 'status']));
    $r->addRoute('POST', '/token-telegram/generate', Route::auth(['TokenTelegramController', 'generate']));
    $r->addRoute('POST', '/token-telegram/revoke', Route::auth(['TokenTelegramController', 'revoke']));
    $r->addRoute('POST', '/token-telegram/test-notification', Route::auth(['TokenTelegramController', 'testNotification']));

    // ---------------- RUTA FINAL ----------------
    //$r->addRoute('GET', '/{url:.+}', Route::auth(['ModuloController', 'RutasModulos']));
};
