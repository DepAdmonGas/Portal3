<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Session;

class HomeController extends BaseController
{
    public function index()
    {   
        // filtro de usuarios
        $filtro_usuario = Session::get('usuario');
        // Obtener módulos con permisos
        $modulos = Session::get('permisos');

        $data = [
            'title'   => 'Home',
            'modulos' => $modulos,
            'filtro_usuario' => $filtro_usuario,
            'scripts' => []
        ];

        View::render('home/index', $data, 'main');
    }
}
