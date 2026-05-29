<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\ExtintorEstacion;

class ExtintoresController extends BaseController
{

 protected string $modulo = 'sasisopa';


    public function index()
    {

        $title = 'Configuración de Extintores';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );

        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion(
            $this->modulo
        );

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
                '/js/controlactividadproceso/extintores.datatable.init.js?v=1.2',
                '/js/controlactividadproceso/extintores.action.init.js?v=1.1',
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/extintores',$data,'sasisopa');
       
    }

    public function datatable(){

      $data = ExtintorEstacion::where(
        'id_estacion',
        $this->estacionId()
        )
        ->where('estado', 1)
        ->orderByDesc('no_extintor')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'no_extintor' => $item->no_extintor,
                'ubicacion' => $item->ubicacion,
                'ultima_recarga' => formatDate($item->ultima_recarga),
                'tipo_extintor' => $item->tipo_extintor,
                'peso_kg' => $item->peso_kg,
                'estado' => $item->estado,
            ];
        });

    echo json_encode([
        'data' => $data,
        'permisos' => [
            'eliminar' => ModuloService::validaPermiso(
                $this->modulo,
                'eliminar'
            ),

            'editar' => ModuloService::validaPermiso(
                $this->modulo,
                'editar'
            )
        ]
    ]);

    }

    public function create(){

    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

    $no_extintor = sanitize_input($data['no_extintor'] ?? null, 'string');
    $fecha_recarga = sanitize_input($data['fecha_recarga'] ?? null, 'string');
    $tipo_extintor = sanitize_input($data['tipo_extintor'] ?? null, 'string');
    $peso_kg = sanitize_input($data['peso_kg'] ?? null, 'string');
    $ubicacion = sanitize_input($data['ubicacion'] ?? null, 'string');

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($no_extintor) || empty($fecha_recarga) || empty($tipo_extintor) || empty($peso_kg) || empty($ubicacion)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        ExtintorEstacion::create([
            'id_estacion' => $this->estacionId(),
            'no_extintor' => $no_extintor,
            'ubicacion' => $ubicacion,
            'ultima_recarga' => $fecha_recarga,
            'tipo_extintor' => $tipo_extintor,
            'peso_kg' => $peso_kg,            
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Extintor creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Extintor'
        ]);
    }

    }

    public function update(int $id){

    header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!ModuloService::validaPermiso($this->modulo, 'editar')) {

        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso para editar'
        ]);

        return;
    }


    $registro = ExtintorEstacion::where(
        'id_estacion',
        $this->estacionId()
    )->find($id);

    if (!$registro) {

        echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
        ]);

        return;
    }

    try{

        $registro->update([

            'no_extintor' => sanitize_input(
                $data['no_extintor'] ?? null,
                'string'
            ),

            'ubicacion' => sanitize_input(
                $data['ubicacion'] ?? null,
                'string'
            ),

            'ultima_recarga' => sanitize_input(
                $data['fecha_recarga'] ?? null,
                'string'
            ),

            'tipo_extintor' => sanitize_input(
                $data['tipo_extintor'] ?? null,
                'string'
            ),

            'peso_kg' => sanitize_input(
                $data['peso_kg'] ?? null,
                'string'
            ),
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Extintor actualizado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar Extintor'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = ExtintorEstacion::find($data['id']);

        if(!$registro){

            echo json_encode([
                'success' => false,
                'message' => 'El Registro no se puede eliminar'
            ]);

            return;
        }

        $registro->update([
            'estado' => 0
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Extintor eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Extintor'
        ]);
    }

    }

}