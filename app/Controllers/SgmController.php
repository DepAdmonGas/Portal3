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
            'elementos' => $sgm,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => false
        ];
        
        View::render('sgm/index', $data,'sgm');

    }


    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------




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
