<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Services\ModuloService;

class HomeController extends BaseController
{
    public function index()
    {
    
        // 🔹 Usuario logueado
        $usuario = Auth::user();

        // 🔹 Obtener módulos con permisos
        $modulos = ModuloService::getPermisos($usuario->id);

        $data = [
            'title'   => 'Home',
            'modulos' => $modulos,
            'scripts' => []
        ];

        View::render('home/index', $data, 'main');
    }
}
