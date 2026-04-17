<?php
use FastRoute\RouteCollector;
use App\Core\Route;

return function(RouteCollector $r) {

    // ---------------- AUTH ----------------
    $r->addRoute('GET', '/', Route::guest(['LoginController', 'index']));
    $r->addRoute('GET', '/login', Route::guest(['LoginController', 'index']));
    $r->addRoute('POST', '/login', ['LoginController', 'login']);
    $r->addRoute('GET', '/logout', Route::auth(['AuthController', 'logout']));

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


    // ---------------- DEPARTAMENTO OPERATIVO ----------------
    $r->addGroup('/departamento-operativo', function (RouteCollector $r) {
    $r->addRoute('GET', '', Route::auth(['DptoOperativoController', 'index']));

    //----- 1. Corporativo
    $r->addRoute('GET','/corporativo',Route::auth(['DptoOperativoController', 'corporativoIndex']));

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
        $r->addRoute('GET', '/datatable-seguimiento-indicadores', Route::auth(['objetivosMetasIndicadoresController', 'datatableSeguimientoIndicadores']));
        $r->addRoute('GET', '/datatable-seguimiento-objetivosmetas', Route::auth(['objetivosMetasIndicadoresController', 'datatableSeguimientoObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/create-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'createObjetivosMetas']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/get-objetivos-metas/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'getObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/update-objetivos-metas/{id:\d+}', Route::auth(['objetivosMetasIndicadoresController', 'updateObjetivosMetas']));
        $r->addRoute('POST', '/objetivos-metas-indicadores/delete-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'deleteObjetivosMetas']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/pdf-objetivos-metas', Route::auth(['objetivosMetasIndicadoresController', 'pdfObjetivosMetas']));
        
        
        $r->addRoute('GET', '/objetivos-metas-indicadores/capacitacion-personal', Route::auth(['SasisopaController', 'objetivosMetasIndicadoresCapacitacionPersonal']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/experiencia-cliente', Route::auth(['SasisopaController', 'objetivosMetasIndicadoresExperienciaCliente']));
        $r->addRoute('GET', '/objetivos-metas-indicadores/indicador-ventas', Route::auth(['SasisopaController', 'objetivosMetasIndicadoresIndicadorVentas']));

        // Elemento 5
        $r->addRoute('GET', '/funciones-responsabilidades-autoridad', Route::auth(['SasisopaController', 'funcionesResponsabilidadesAutoridad']));
        // Elemento 6
        $r->addRoute('GET', '/competencia-personal-capacitacion-entrenamiento', Route::auth(['SasisopaController', 'competenciaPersonalCapacitacionEntrenamiento']));
        // Elemento 7
        $r->addRoute('GET', '/comunicacion-participacion-consulta', Route::auth(['SasisopaController', 'comunicacionParticipacionConsulta']));
        // Elemento 8
        $r->addRoute('GET', '/control-documentos-registros', Route::auth(['SasisopaController', 'controlDocumentosRegistros']));
        // Elemento 9
        $r->addRoute('GET', '/mejores-practicas-estandares', Route::auth(['SasisopaController', 'mejoresPracticasEstandares']));
        // Elemento 10
        $r->addRoute('GET', '/control-actividades-procesos', Route::auth(['SasisopaController', 'controlActividadesProcesos']));
        // Elemento 11
        $r->addRoute('GET', '/integridad-mecanica-aseguramiento', Route::auth(['SasisopaController', 'integridadMecanicaAseguramiento']));
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
