<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Sgm\Elemento;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;


class SgmController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index()
    {

        $title = 'SGM';

        // 1. Obtener contexto de estación para ESTE módulo
        $moduleCtx = ModuleStationService::getContext('sgm');
        $idEstacion = $moduleCtx['id_estacion'];


        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $sgm = Elemento::all();

        $data = [
            'title' => $title,
            'elementos' => $sgm,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sgm',  // ← ACTIVA EL SELECTOR
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                 '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('sgm/index', $data, 'sgm');
    }






    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------


    public function gestionRiesgosImpactanMedicion()
    {

        $title = '8. Gestión de Riesgos que impactan en la medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/gestion-riesgos-impactan-medicion', $data, 'sgm');
    }

    public function establecimientoSeguimientoConfirmacionMetrologica()
    {

        $title = '9. Establecimiento y Seguimiento Confirmación Metrológica';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/establecimiento-seguimiento-confirmacion-metrologica', $data, 'sgm');
    }
}
