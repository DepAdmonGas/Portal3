<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Sgm\Elemento;
use App\Core\Breadcrumb;

class SgmController{

    public function index(){

        $title = 'SGM';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $sgm = Elemento::all();

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'elementos' => $sgm,
            'help' => false
        ];
        
        View::render('sgm/index', $data,'sgm');

    }

    public function estructuraSistemaMedicion(){

        $title = '1. Estructura del sistema de Medicion';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/estructura-sistema-medicion', $data,'sgm');

    }

    public function controlDocumentalSistemaGestionMedicion(){

        $title = '2. Control del documental del Sistema de Gestion de medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/control-documental-sistema-gestion-medicion', $data,'sgm');

    }

    public function responsabilidadesDireccion(){

        $title = '3. Responsabilidades de la direccion';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/responsabilidades-direccion', $data,'sgm');

    }

    public function establecimientoObjetivosEnfocadosCliente(){

        $title = '4. Establecimiento de objetivos enfocados al cliente';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/establecimiento-objetivos-enfocados-cliente', $data,'sgm');

    }

    public function normatividadAplicableMediciones(){

        $title = '5. Normatividad aplicable a mediciones';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/normatividad-aplicable-mediciones', $data,'sgm');

    }

    public function gestionRecursos(){

        $title = '6. Gestion de los Recursos';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/gestion-recursos', $data,'sgm');

    }

    public function procesosMedicion(){

        $title = '7. Procesos de medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/procesos-medicion', $data,'sgm');

    }

    public function gestionRiesgosImpactanMedicion(){

        $title = '8. Gestión de Riesgos que impactan en la medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/gestion-riesgos-impactan-medicion', $data,'sgm');

    }

    public function establecimientoSeguimientoConfirmacionMetrologica(){

        $title = '9. Establecimiento y Seguimiento Confirmación Metrológica';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/establecimiento-seguimiento-confirmacion-metrologica', $data,'sgm');

    }

    public function auditoriasInternasExternasAtencionHallazgos(){

        $title = '10. Auditorias, Internas, externas y Atención de hallazgos';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/auditorias-internas-externas-atencion-hallazgos', $data,'sgm');

    }

    public function evaluacionCumplimientoObjetivosRevisionDireccion(){

        $title = '11. Evaluación del cumplimiento de Objetivos y revisión por la Dirección';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sgm/evaluacion-cumplimiento-objetivos-revision-direccion', $data,'sgm');

    }

}
