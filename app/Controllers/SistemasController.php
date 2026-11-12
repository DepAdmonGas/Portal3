<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Models\Usuario;

class SistemasController extends BaseController
{
    protected string $modulo = 'sistemas';

    public function index()
    {

        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $title = 'Soporte (Apartados)';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => false
        ];

        View::render('sistemas/index', $data, 'main');
    }
}
