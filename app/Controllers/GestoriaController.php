<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;

class GestoriaController extends BaseController
{
    protected string $modulo = 'gestoria';
    public function index()
    {
        $title = 'Gestoria';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/gestoria/index.datatable.init.js?v=1.1.0',
                '/js/gestoria/index.actions.init.js?v=1.1.0'
            ],
            'help' => false
        ];

        View::render('gestoria/index', $data, 'main');
    }
}
