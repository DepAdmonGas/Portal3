<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\JarraPatron;

class JarraPatronController extends BaseController
{

 protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'Configuración de Jarra de Patrón';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Calibración de Equipos','/sasisopa/control-actividades-procesos/calibracion-equipos');
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
                '/js/controlactividadproceso/jarrapatron.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/jarrapatron.action.init.js?v=' . time(),

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/jarra-patron',$data,'sasisopa');
       
    }

    public function datatable(){

      $data = JarraPatron::where(
        'id_estacion',
        $this->estacionId()
        )
        ->where('estado', 1)
        ->orderByDesc('id')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'marca' => $item->marca,
                'no_serie' => $item->no_serie,
                'capacidad' => $item->capacidad,
                'material' => $item->material,
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

    $marca = sanitize_input($data['marca'] ?? null, 'string');
    $no_serie = sanitize_input($data['no_serie'] ?? null, 'string');
    $capacidad = sanitize_input($data['capacidad'] ?? null, 'string');
    $material = sanitize_input($data['material'] ?? null, 'string');


     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($no_serie) || empty($marca) || empty($capacidad) || empty($material)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        JarraPatron::create([
            'id_estacion' => $this->estacionId(),            
            'marca' => $marca,
            'no_serie' => $no_serie,
            'capacidad' => $capacidad,
            'material' => $material,
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Jarra de Patrón creada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Jarra de Patrón'
        ]);
    }

    }

    public function update(){

    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'),true);

    if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso para editar'
        ]);
        return;
    }

    $registro = JarraPatron::where(
        'id_estacion',
        $this->estacionId()
    )->find($data['id_jarra']);

    if (!$registro) {

        echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
        ]);

        return;
    }

    try{

        $registro->update([

            'marca' => sanitize_input(
                $data['marca'] ?? null,
                'string'
            ),

            'no_serie' => sanitize_input(
                $data['no_serie'] ?? null,
                'string'
            ),

            'capacidad' => sanitize_input(
                $data['capacidad'] ?? null,
                'string'
            ),

            'material' => sanitize_input(
                $data['material'] ?? null,
                'string'
            )
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Jarra de Patrón actualizada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar Jarra de Patrón'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = JarraPatron::find($data['id']);

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
            'message' => 'Jarra de Patrón eliminada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Jarra de Patrón'
        ]);
    }

    }


}