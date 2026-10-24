<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Auth;
use App\Services\ModuleStationService;
use App\Services\SolicitudGafetesService;
use App\Services\SolicitudTarjetasService;

class HomeController extends BaseController
{

    public function index()
    {
        // Resetear contextos de estaciones al salir de módulos
        ModuleStationService::resetAllContexts();

        // Obtener módulos con permisos
        $modulos = Session::get('permisos');
        $puesto = (int) (Auth::user()?->id_puesto ?? 0);
        $conteoSolicitudes = [];
        if (in_array($puesto, [1, 13], true)) {
            $conteoSolicitudes = [
                '/solicitud-gafetes' => SolicitudGafetesService::contarSolicitudesPorEstatus([1]),
                '/solicitud-tarjetas' => SolicitudTarjetasService::contarSolicitudesPorEstatus([1]),
            ];
        } elseif (in_array($puesto, [6, 7], true)) {
            $conteoSolicitudes = [
                '/solicitud-gafetes' => SolicitudGafetesService::contarSolicitudesPorEstatus([2, 3]),
                '/solicitud-tarjetas' => SolicitudTarjetasService::contarSolicitudesPorEstatus([2, 3]),
            ];
        }

        $data = [
            'title'   => 'Home',
            'modulo' => '',
            'modulousuario' => $modulos,
            'conteoSolicitudesHome' => $conteoSolicitudes,
            'scripts' => []
        ];

        View::render('home/index', $data, 'main');
    }
}
