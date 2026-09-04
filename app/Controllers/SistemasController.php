<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

class SistemasController extends BaseController
{
    protected string $modulo = 'sistemas';

    public function index()
    {
        $title = 'Sistemas';

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
