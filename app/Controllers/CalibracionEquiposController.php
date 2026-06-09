<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\ExtintorEstacion;

class CalibracionEquiposController extends BaseController
{

 protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'Calibración de Equipos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );

        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
            ],
            'scripts' => [
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/calibracion-equipos',$data,'sasisopa');
       
    }


}