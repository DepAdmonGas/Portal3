<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;

class PerfilController extends BaseController
{

    public function index()
    {
        $title = 'Perfil de usuario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => false
        ];

        View::render('perfil/index', $data, 'main');
    }
}
