<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\ExtintorEstacion;
use App\Services\ModuleStationService;

class CalibracionEquiposController extends BaseController
{

 protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index()
    {

        $title = 'Calibración de Equipos';
        $idEstacion = $this->estacionModulo();

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
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
            ],
            'scripts' => [
                '/js/core/module-station-selector.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/calibracion-equipos',$data,'sasisopa');
       
    }


}