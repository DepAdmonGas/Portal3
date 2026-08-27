<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;

class GestoriaSgmController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index()
    {
        $title = 'Reporte SGM';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion(
            $this->modulo
        );

        $estaciones = Estacion::where(function ($query) {
            $query->where('numlista', '<=', 8)
                ->orWhere('numlista', 13);
        })
            ->orderBy('numlista', 'asc')
            ->get([
                'id',
                'numlista',
                'razonsocial'
            ]);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estaciones' => $estaciones,
            'anio_actual' => date('Y'),
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/gestoria/sgm/index.actions.init.js?v=1.0.0'
            ],
            'help' => false
        ];

        View::render(
            'gestoria/sgm',
            $data,
            'main'
        );
    }
}
