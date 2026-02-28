<?php
namespace App\Controllers;
use App\Core\View;

class SgmController{

    public function index(){

         $data = [
            'title' => 'SGM',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/index', $data,'sgm');

    }

    public function estructuraSistemaMedicion(){

         $data = [
            'title' => '1. Estructura del sistema de Medicion',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/estructura-sistema-medicion', $data,'sgm');

    }

    public function controlDocumentalSistemaGestionMedicion(){

         $data = [
            'title' => '2. Control del documental del Sistema de Gestion de medición',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/control-documental-sistema-gestion-medicion', $data,'sgm');

    }

    public function responsabilidadesDireccion(){

         $data = [
            'title' => '3. Responsabilidades de la direccion',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/responsabilidades-direccion', $data,'sgm');

    }

    public function establecimientoObjetivosEnfocadosCliente(){

         $data = [
            'title' => '4. Establecimiento de objetivos enfocados al cliente',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/establecimiento-objetivos-enfocados-cliente', $data,'sgm');

    }

    public function normatividadAplicableMediciones(){

         $data = [
            'title' => '5. Normatividad aplicable a mediciones',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/normatividad-aplicable-mediciones', $data,'sgm');

    }

    public function gestionRecursos(){

         $data = [
            'title' => '6. Gestion de los Recursos',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/gestion-recursos', $data,'sgm');

    }

    public function procesosMedicion(){

         $data = [
            'title' => '7. Procesos de medición',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/procesos-medicion', $data,'sgm');

    }

    public function gestionRiesgosImpactanMedicion(){

         $data = [
            'title' => '8. Gestión de Riesgos que impactan en la medición',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/gestion-riesgos-impactan-medicion', $data,'sgm');

    }

    public function establecimientoSeguimientoConfirmacionMetrologica(){

         $data = [
            'title' => '9. Establecimiento y Seguimiento Confirmación Metrológica',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/establecimiento-seguimiento-confirmacion-metrologica', $data,'sgm');

    }

    public function auditoriasInternasExternasAtencionHallazgos(){

         $data = [
            'title' => '10. Auditorias, Internas, externas y Atención de hallazgos',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/auditorias-internas-externas-atencion-hallazgos', $data,'sgm');

    }

    public function evaluacionCumplimientoObjetivosRevisionDireccion(){

         $data = [
            'title' => '11. Evaluación del cumplimiento de Objetivos y revisión por la Dirección',
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sgm/evaluacion-cumplimiento-objetivos-revision-direccion', $data,'sgm');

    }

    

}
