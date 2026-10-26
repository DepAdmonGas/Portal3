<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Auth;
use App\Services\ModuleStationService;
use App\Services\SolicitudGafetesService;
use App\Services\SolicitudTarjetasService;
use App\Services\SoporteEstacionesService;
use App\Services\SoporteActividadesService;
use App\Services\SoporteService;

class HomeController extends BaseController
{

    public function index()
    {
        // Resetear contextos de estaciones al salir de módulos
        ModuleStationService::resetAllContexts();

        // Obtener módulos con permisos
        $modulos = Session::get('permisos');
        if (isset($modulos[SoporteEstacionesService::MODULE_KEY])) {
            $modulos[SoporteEstacionesService::MODULE_KEY]['ruta'] = SoporteEstacionesService::RUTA;
        }
        if (isset($modulos[SoporteActividadesService::MODULE_KEY])) {
            $modulos[SoporteActividadesService::MODULE_KEY]['ruta'] = SoporteActividadesService::RUTA;
        }
        if (isset($modulos[SoporteService::MODULE_KEY])) {
            $modulos[SoporteService::MODULE_KEY]['ruta'] = SoporteService::RUTA;
        }
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
        if (isset($modulos[SoporteEstacionesService::MODULE_KEY]) && !empty($modulos[SoporteEstacionesService::MODULE_KEY]['leer'])) {
            $conteoSolicitudes[SoporteEstacionesService::RUTA] = SoporteEstacionesService::getContadorHome();
        }
        if ($puesto === SoporteActividadesService::PUESTO_SISTEMAS
            && isset($modulos[SoporteActividadesService::MODULE_KEY])
            && !empty($modulos[SoporteActividadesService::MODULE_KEY]['leer'])) {
            $conteoSolicitudes[SoporteActividadesService::RUTA] = SoporteActividadesService::getPendientes(null, (int) date('Y'));
        }
        if (isset($modulos[SoporteService::MODULE_KEY]) && !empty($modulos[SoporteService::MODULE_KEY]['leer'])) {
            $conteoSolicitudes[SoporteService::RUTA] = SoporteService::getContadorHome();
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
