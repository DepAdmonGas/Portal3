<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;

class SgmEstructuraController extends BaseController{

    protected string $modulo = 'sgm';

    public function index(){

    $title = '1. Estructura del sistema de Medicion';
    Breadcrumb::add('Home', '/home');
    Breadcrumb::add('SGM', '/sgm');
    Breadcrumb::add($title, '');
    $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/sgm/revision/index.action.init.js?v=' . time(),

                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),               
                '/js/sgm/revision/index.datatable.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('sgm/estructura/index', $data,'sgm');

    }

}