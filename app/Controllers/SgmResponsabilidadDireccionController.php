<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;

use App\Models\Sgm\Politica;


class SgmResponsabilidadDireccionController extends BaseController{

protected string $modulo = 'sgm';

    public function index(){

        $title = '3. Responsabilidades de la direccion';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/sgm/revision/index.action.init.js?v=' . time(),

                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),               
                '/js/sgm/revision/index.datatable.init.js?v=' . time(),

                '/js/sgm/responsabilidad-direccion/index.actions.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('sgm/responsabilidad-direccion/index', $data,'sgm');

    }

    public function politicas()
    {
        header('Content-Type: application/json');

        $politicas = Politica::where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('id')
            ->get();

        $data = $politicas->map(fn ($politica) => [
            'id' => $politica->id,
            'fecha' => formatearFecha($politica->fecha?->format('Y-m-d')),
            'contenido' => $politica->contenido,
        ]);

        echo json_encode($data);
    }

    public function deletePolitica()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        Politica::where(
            'id_estacion',
            $this->estacionId()
        )
        ->findOrFail($data['id'])
        ->delete();

        echo json_encode([
            'success'=>true,
            'message' => 'Politica eliminada'
        ]);
    }

    public function politicaIndex(){

        $title = 'Politica SGM';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('3. Responsabilidades de la direccion', '/sgm/responsabilidades-direccion');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' =>[
                'libs/quill/dist/quill.snow.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',  
                '/libs/quill/dist/quill.js',  
                '/js/sgm/responsabilidad-direccion/politica.actions.init.js?v=' . time(),           
            ],
            'help' => false
        ];
        
        View::render('sgm/responsabilidad-direccion/politica', $data,'sgm');

    }

    public function detallePolitica()
    {
        header('Content-Type: application/json');

        $politica = Politica::where(
                'id_estacion',
                $this->estacionId()
            )
            ->latest('id')
            ->first();

        if (!$politica) {

            echo json_encode([
                'fecha' => date('Y-m-d'),
                'contenido' => ''
            ]);

            return;
        }

        echo json_encode([
            'fecha' => $politica->fecha?->format('Y-m-d'),
            'contenido' => $politica->contenido
        ]);
    }

    public function guardarPolitica()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        Politica::create([
            'id_estacion' => $this->estacionId(),
            'fecha'       => $data['fecha'],
            'contenido'   => $data['contenido']
        ]);

        echo json_encode([
            'success' => true
        ]);
    }

}