<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Session;

class HomeController extends BaseController
{
    public function index()
    {   

        // Obtener módulos con permisos
        $modulos = Session::get('permisos');

        $data = [
        'title'   => 'Home',
        'modulos' => $modulos,
        'scripts' => []
        ];

    View::render('home/index', $data, 'main');
    }

}