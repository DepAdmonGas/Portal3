<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Sasisopa\Sasisopa;

class SasisopaController{

    public function index(){

        $sasisopa = Sasisopa::all();

         $data = [
            'title' => 'SASISOPA',
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

         $data = [
            'title' => '1. POLÍTICA',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/politica', $data,'sasisopa');

    }

    public function identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales(){

         $data = [
            'title' => '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', $data,'sasisopa');

    }

    public function requisitosLegales(){

         $data = [
            'title' => '3. REQUISITOS LEGALES',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/requisitos-legales', $data,'sasisopa');

    }

    public function requisitosLegalesConfiguracion(){

         $data = [
            'title' => 'REQUISITOS LEGALES CONFIGURACIÓN',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/requisitos-legales-configuracion', $data,'sasisopa');

    }

    

    public function objetivosMetasIndicadores(){

         $data = [
            'title' => '4. OBJETIVOS, METAS E INDICADORES',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/objetivos-metas-indicadores', $data,'sasisopa');

    }

    public function objetivosMetasIndicadoresCapacitacionPersonal(){

         $data = [
            'title' => 'Capacitación del personal',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/capacitacion-personal', $data,'sasisopa');

    }

     public function objetivosMetasIndicadoresExperienciaCliente(){

         $data = [
            'title' => 'Experiencia del cliente',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/experiencia-cliente', $data,'sasisopa');

    }

    public function objetivosMetasIndicadoresIndicadorVentas(){

         $data = [
            'title' => 'Indicadores de Ventas',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/indicador-ventas', $data,'sasisopa');

    }

    

    

    public function funcionesResponsabilidadesAutoridad(){

         $data = [
            'title' => '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/funciones-responsabilidades-autoridad', $data,'sasisopa');

    }

    public function competenciaPersonalCapacitacionEntrenamiento(){

         $data = [
            'title' => '6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/competencia-personal-capacitacion-entrenamiento', $data,'sasisopa');

    }

    public function comunicacionParticipacionConsulta(){

         $data = [
            'title' => '7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/comunicacion-participacion-consulta', $data,'sasisopa');

    }

    public function controlDocumentosRegistros(){

         $data = [
            'title' => '8. CONTROL DE DOCUMENTOS Y REGISTROS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/control-documentos-registros', $data,'sasisopa');

    }

    public function mejoresPracticasEstandares(){

         $data = [
            'title' => '9. MEJORES PRÁCTICAS Y ESTÁNDARES',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/mejores-practicas-estandares', $data,'sasisopa');

    }

    public function controlActividadesProcesos(){

         $data = [
            'title' => '10. CONTROL DE ACTIVIDADES Y PROCESOS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/control-actividades-procesos', $data,'sasisopa');

    }

    public function integridadMecanicaAseguramiento(){

         $data = [
            'title' => '11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/integridad-mecanica-aseguramiento', $data,'sasisopa');

    }

    public function seguridadContratistas(){

         $data = [
            'title' => '12. SEGURIDAD DE CONTRATISTAS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/seguridad-contratistas', $data,'sasisopa');

    }

    public function preparacionEmergencias(){

        $data = [
            'title' => '13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/preparacion-emergencias', $data,'sasisopa');

    }

    public function monitoreoVerificacionEvaluacion(){

    $data = [
            'title' => '14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/monitoreo-verificacion-evaluacion', $data,'sasisopa');

    }

    public function auditorias(){

         $data = [
            'title' => '15. AUDITORÍAS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/auditorias', $data,'sasisopa');

    }

    public function investigacionIncidentesAccidentes(){

         $data = [
            'title' => '16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/investigacion-incidentes-accidentes', $data,'sasisopa');

    }

        public function revisionResultados(){

         $data = [
            'title' => '17. REVISIÓN DE RESULTADOS',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/revision-resultados', $data,'sasisopa');

    }

    public function informesDesempeno(){

         $data = [
            'title' => '18. INFORMES DE DESEMPEÑO',
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

         $data = [
            'title' => 'Calendario',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/calendario', $data,'sasisopa');

    }

     public function cursos(){

         $data = [
            'title' => 'Cursos',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/cursos', $data,'sasisopa');

    }

    

}
