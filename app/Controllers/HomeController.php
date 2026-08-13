<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Services\ModuleStationService;

class HomeController extends BaseController
{

    public function index()
    {
        // Resetear contextos de estaciones al salir de módulos
        ModuleStationService::resetAllContexts();

        // Obtener módulos con permisos
        $modulos = Session::get('permisos');

        $data = [
            'title'   => 'Home',
            'modulo' => '',
            'modulousuario' => $modulos,
            'scripts' => []
        ];

        View::render('home/index', $data, 'main');
    }
}
