<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\Dispensario;

class DispensarioController extends BaseController
{

 protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'Configuración de Dispensarios';

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
                '/js/controlactividadproceso/dispensario.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/dispensario.action.init.js?v=' . time(),

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/configuracion-dispensario',$data,'sasisopa');
       
    }

     public function datatable(){

      $data = Dispensario::where(
        'id_estacion',
        $this->estacionId()
        )
        ->where('estado', 1)
        ->orderByDesc('no_dispensario')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'no_dispensario' => $item->no_dispensario,
                'marca' => $item->marca,
                'modelo' => $item->modelo,
                'serie' => $item->serie,
                'producto1' => $item->producto1,
                'producto2' => $item->producto2,
                'producto3' => $item->producto3,
                'estado' => $item->estado
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

    
    $no_dispensario = sanitize_input($data['no_dispensario'] ?? 0, 'int');
    $marca = sanitize_input($data['marca'] ?? null, 'string');
    $modelo = sanitize_input($data['modelo'] ?? null, 'string');
    $serie = sanitize_input($data['serie'] ?? null, 'string');
    $producto1 = sanitize_input($data['producto1'] ?? 0, 'int');
    $producto2 = sanitize_input($data['producto2'] ?? 0, 'int');
    $producto3 = sanitize_input($data['producto3'] ?? 0, 'int');

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($no_dispensario) || empty($marca) || empty($modelo) || empty($serie)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        Dispensario::create([
            'id_estacion' => $this->estacionId(),
            'no_dispensario' => $no_dispensario,
            'marca' => $marca,
            'modelo' => $modelo,
            'serie' => $serie,
            'producto1' => $producto1,
            'producto2' => $producto2,
            'producto3' => $producto3,
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Dispensario creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Dispensario'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = Dispensario::find($data['id']);

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
            'message' => 'Dispensario eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Dispensario'
        ]);
    }

    }

   
}