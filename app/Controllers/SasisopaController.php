<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Sasisopa\Sasisopa;
use App\Core\Breadcrumb;

class SasisopaController{

    public function index(){

        $title = 'SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $sasisopa = Sasisopa::all();

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'elementos' => $sasisopa

        ];
        
        View::render('sasisopa/index', $data,'sasisopa');

    }

    public function politica(){

        $title = '1. POLÍTICA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/politica', $data,'sasisopa');

    }

    public function identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales(){

        $title = '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', $data,'sasisopa');

    }

    public function requisitosLegales(){

        $title = '3. REQUISITOS LEGALES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/requisitos-legales', $data,'sasisopa');

    }

    public function requisitosLegalesConfiguracion(){

        $title = 'REQUISITOS LEGALES CONFIGURACIÓN';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('3. REQUISITOS LEGALES', '/sasisopa/requisitos-legales');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/requisitos-legales-configuracion', $data,'sasisopa');

    }

    

    public function objetivosMetasIndicadores(){

        $title = '4. OBJETIVOS, METAS E INDICADORES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/objetivos-metas-indicadores', $data,'sasisopa');

    }

    public function objetivosMetasIndicadoresCapacitacionPersonal(){

        $title = 'Capacitación del personal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/capacitacion-personal', $data,'sasisopa');

    }

     public function objetivosMetasIndicadoresExperienciaCliente(){

        $title = 'Experiencia del cliente';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/experiencia-cliente', $data,'sasisopa');

    }

    public function objetivosMetasIndicadoresIndicadorVentas(){

        $title = 'Indicadores de Ventas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/indicador-ventas', $data,'sasisopa');

    }

    

    

    public function funcionesResponsabilidadesAutoridad(){

        $title = '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/funciones-responsabilidades-autoridad', $data,'sasisopa');

    }

    public function competenciaPersonalCapacitacionEntrenamiento(){

        $title = '6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/competencia-personal-capacitacion-entrenamiento', $data,'sasisopa');

    }

    public function comunicacionParticipacionConsulta(){

        $title = '7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/comunicacion-participacion-consulta', $data,'sasisopa');

    }

    public function controlDocumentosRegistros(){

        $title = '8. CONTROL DE DOCUMENTOS Y REGISTROS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/control-documentos-registros', $data,'sasisopa');

    }

    public function mejoresPracticasEstandares(){

        $title = '9. MEJORES PRÁCTICAS Y ESTÁNDARES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/mejores-practicas-estandares', $data,'sasisopa');

    }

    public function controlActividadesProcesos(){

        $title = '10. CONTROL DE ACTIVIDADES Y PROCESOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/control-actividades-procesos', $data,'sasisopa');

    }

    public function integridadMecanicaAseguramiento(){

        $title = '11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/integridad-mecanica-aseguramiento', $data,'sasisopa');

    }

    public function seguridadContratistas(){

        $title = '12. SEGURIDAD DE CONTRATISTAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/seguridad-contratistas', $data,'sasisopa');

    }

    public function preparacionEmergencias(){

        $title = '13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/preparacion-emergencias', $data,'sasisopa');

    }

    public function monitoreoVerificacionEvaluacion(){

        $title = '14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

    $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/monitoreo-verificacion-evaluacion', $data,'sasisopa');

    }

    public function auditorias(){

        $title = '15. AUDITORÍAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/auditorias', $data,'sasisopa');

    }

    public function investigacionIncidentesAccidentes(){

        $title = '16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/investigacion-incidentes-accidentes', $data,'sasisopa');

    }

        public function revisionResultados(){

        $title = '17. REVISIÓN DE RESULTADOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/revision-resultados', $data,'sasisopa');

    }

    public function informesDesempeno(){

        $title = '18. INFORMES DE DESEMPEÑO';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/informes-desempeno', $data,'sasisopa');

    }

    //--------------------------------------------------------------


        public function calendario(){

        $title = 'Calendario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/calendario', $data,'sasisopa');

    }

     public function cursos(){

        $title = 'Cursos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/cursos', $data,'sasisopa');

    }

    

}
