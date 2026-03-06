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

        // SASISOPA
                $r->addGroup('/sasisopa', function (RouteCollector $r) {
                        // index principal
                        $r->addRoute('GET', '', Route::auth(['SasisopaController', 'index']));
                        // Elemento 1
                        $r->addRoute('GET', '/politica', Route::auth(['SasisopaController', 'politica']));
                        // Elemento 2
                        $r->addRoute('GET', '/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', Route::auth(['SasisopaController', 'identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales']));
                        // Elemento 3
                        $r->addRoute('GET', '/requisitos-legales', Route::auth(['SasisopaController', 'requisitosLegales']));
                        // Requisitos legales configuración
                        $r->addRoute('GET', '/requisitos-legales/configuracion', Route::auth(['SasisopaController', 'requisitosLegalesConfiguracion']));

                        // Elemento 4
                        $r->addRoute('GET', '/objetivos-metas-indicadores', Route::auth(['SasisopaController', 'objetivosMetasIndicadores']));
                        // Capacitación del personal
                        $r->addRoute('GET', '/objetivos-metas-indicadores/capacitacion-personal', Route::auth(['SasisopaController', 'objetivosMetasIndicadoresCapacitacionPersonal']));
                        // Experiencia del cliente
                        $r->addRoute('GET', '/objetivos-metas-indicadores/experiencia-cliente', Route::auth(['SasisopaController', 'objetivosMetasIndicadoresExperienciaCliente']));
                        // Indicadores de Ventas
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
                        $r->addRoute('GET','/auditorias',Route::auth(handler: ['SasisopaController','auditorias']));
                        // Elemento 16
                        $r->addRoute('GET','/investigacion-incidentes-accidentes',Route::auth(handler: ['SasisopaController','investigacionIncidentesAccidentes']));
                        // Elemento 17
                        $r->addRoute('GET','/revision-resultados',Route::auth(handler: ['SasisopaController','revisionResultados']));
                        // Elemento 18
                        $r->addRoute('GET','/informes-desempeno',Route::auth(handler: ['SasisopaController','informesDesempeno']));
                        
                        // Calendario
                        $r->addRoute('GET','/calendario',Route::auth(handler: ['SasisopaController','calendario']));
                        // Cursos
                        $r->addRoute('GET','/cursos',Route::auth(handler: ['SasisopaController','cursos']));


                });

                // SGM
                $r->addGroup('/sgm', function (RouteCollector $r) {
                        // Index principal
                        $r->addRoute('GET', '', Route::auth(['SgmController', 'index']));
                        // Elemento 1
                        $r->addRoute('GET', '/estructura-sistema-medicion', Route::auth(['SgmController', 'estructuraSistemaMedicion']));
                        // Elemento 2
                        $r->addRoute('GET', '/control-documental-sistema-gestion-medicion', Route::auth(['SgmController', 'controlDocumentalSistemaGestionMedicion']));
                        // Elemento 3
                        $r->addRoute('GET', '/responsabilidades-direccion', Route::auth(['SgmController', 'responsabilidadesDireccion']));
                        // Elemento 4
                        $r->addRoute('GET', '/establecimiento-objetivos-enfocados-cliente', Route::auth(['SgmController', 'establecimientoObjetivosEnfocadosCliente']));
                        // Elemento 5
                        $r->addRoute('GET', '/normatividad-aplicable-mediciones', Route::auth(['SgmController', 'normatividadAplicableMediciones']));
                        // Elemento 6
                        $r->addRoute('GET', '/gestion-recursos', Route::auth(['SgmController', 'gestionRecursos']));
                        // Elemento 7
                        $r->addRoute('GET', '/procesos-medicion', Route::auth(['SgmController', 'procesosMedicion']));
                        // Elemento 8
                        $r->addRoute('GET', '/gestion-riesgos-impactan-medicion', Route::auth(['SgmController', 'gestionRiesgosImpactanMedicion']));
                        // Elemento 9
                        $r->addRoute('GET', '/establecimiento-seguimiento-confirmacion-metrologica', Route::auth(['SgmController', 'establecimientoSeguimientoConfirmacionMetrologica']));
                        // Elemento 10
                        $r->addRoute('GET', '/auditorias-internas-externas-atencion-hallazgos', Route::auth(['SgmController', 'auditoriasInternasExternasAtencionHallazgos']));
                        // Elemento 11
                        $r->addRoute('GET', '/evaluacion-cumplimiento-objetivos-revision-direccion', Route::auth(['SgmController', 'evaluacionCumplimientoObjetivosRevisionDireccion']));
                });


        $r->addRoute('GET', '/{url:.+}', Route::auth(['ModuloController', 'RutasModulos']));

};
?>