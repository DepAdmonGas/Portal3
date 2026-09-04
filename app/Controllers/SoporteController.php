<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

class SoporteController extends BaseController
{
    protected string $modulo = 'sistemas';

    public function index()
    {
        $title = 'Soporte';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sistemas/soporte/index.datatable.init.js?v=1.0.0',
                '/js/sistemas/soporte/index.actions.init.js?v=1.0.0'

            ],
            'help' => false
        ];

        View::render('sistemas/soporte', $data, 'main');
    }
}
