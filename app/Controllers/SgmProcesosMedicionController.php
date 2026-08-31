<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;

class SgmProcesosMedicionController extends BaseController
{

    protected string $modulo = 'sgm';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function index()
    {
        $title = '7. Procesos de medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $estacionId = $this->estacionModulo();

        $pendientesCalibracion = $this->pendientesCalibracion($estacionId ?? 0);
        $pendientesVerificacion = $this->pendientesVerificacion($estacionId ?? 0);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $estacionId,
            'moduleStationKey' => 'sgm',
            'pendientesCalibracion' => $pendientesCalibracion,
            'pendientesVerificacion' => $pendientesVerificacion,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/procesos-medicion/index', $data, 'sgm');
    }

    public function pendientesCalibracion(int $idEstacion): int
    {
        return ProgramaAnualCalibracionVerificacion::query()
            ->where('id_estacion', $idEstacion)
            ->where('fecha', '<=', date('Y-m-d'))
            ->where('estado', 0)
            ->whereHas('equipo', function ($query) {
                $query->where('categoria', '<>', 'Equipo sometido a verificación');
            })
            ->count();
    }

    public function pendientesVerificacion(int $idEstacion): int
    {
        return ProgramaAnualCalibracionVerificacion::query()
            ->where('id_estacion', $idEstacion)
            ->where('fecha', '<=', date('Y-m-d'))
            ->where('estado', 0)
            ->whereHas('equipo', function ($query) {
                $query->where('categoria', 'Equipo sometido a verificación')
                    ->where('nombre', 'Sensor de nivel y temperatura');
            })
            ->count();
    }

    //----- Programa anual de calibración de patrones e instrumentos de medida

    public function programacionAnualCalibracion()
    {

        $title = 'Programa anual de calibración de patrones e instrumentos de medida';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sgm/procesos-medicion/programaanualcalibracion.actions.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/programacion-anual-calibracion', $data, 'sgm');
    }

    //----- Programa anual de calibración de patrones e instrumentos de medida

    //----- Bitácora la para la calibración de equipos
    public function bitacoraCalibracionEquipos()
    {

        $title = 'Bitácora la para la calibración de equipos';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sgm/procesos-medicion/bitacoracalibracion.actions.init.js?v=' . time(),
                '/js/sgm/procesos-medicion/bitacoracalibracion.datatable.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/bitacora-calibracion-equipos', $data, 'sgm');
    }

    //----- Bitácora la para la calibración de equipos

    //----- Programa anual de verificación de equipos
    public function programacionAnualVerificacion()
    {

        $title = 'Programa anual de verificación de equipos';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sgm/procesos-medicion/programaanualverificacion.actions.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/programacion-anual-verificacion', $data, 'sgm');
    }
    //----- Programa anual de verificación de equipos

    public function bitacoraVerificacionEquipos()
    {

        $title = 'Bitácora para la verificación de equipos de medicion';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sgm/procesos-medicion/bitacoraverificacion.actions.init.js?v=' . time(),
                '/js/sgm/procesos-medicion/bitacoraverificacion.datatable.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/bitacora-verificacion-equipos', $data, 'sgm');
    }
}
