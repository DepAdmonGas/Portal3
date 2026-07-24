<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;

class SgmProcesosMedicionController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index()
    {
        $title = '7. Procesos de medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $pendientesCalibracion = $this->pendientesCalibracion($this->estacionId());
        $pendientesVerificacion = $this->pendientesVerificacion($this->estacionId());

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'pendientesCalibracion' => $pendientesCalibracion,
            'pendientesVerificacion' => $pendientesVerificacion,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sgm/procesos-medicion/programaanualcalibracion.actions.init.js?v=1.2',

            ],
            'help' => true
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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

            ],
            'help' => true
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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

            ],
            'help' => true
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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

            ],
            'help' => true
        ];

        View::render('sgm/procesos-medicion/bitacora-calibracion-equipos', $data, 'sgm');
    }
}
