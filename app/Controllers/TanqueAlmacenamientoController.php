<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuleStationService;
use App\Services\ModuloService;
use App\Models\Sasisopa\TanqueAlmacenamiento;

class TanqueAlmacenamientoController extends BaseController
{

 protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index()
    {

        $title = 'Configuración de Tanques de almacenamiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Calibración de Equipos','/sasisopa/control-actividades-procesos/calibracion-equipos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $idEstacion = $this->estacionModulo();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/controlactividadproceso/tanquealmacenamiento.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/tanquealmacenamiento.action.init.js?v=' . time(),

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/tanque-almacenamiento',$data,'sasisopa');
       
    }

     public function datatable(){

      $data = TanqueAlmacenamiento::where(
        'id_estacion',
        $this->estacionModulo()
        )
        ->orderByDesc('no_tanque')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'no_tanque' => $item->no_tanque,
                'capacidad' => $item->capacidad,
                'producto' => $item->producto,
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

    $no_tanque = sanitize_input($data['no_tanque'] ?? null, 'int');
    $capacidad = sanitize_input($data['capacidad'] ?? null, 'string');
    $producto = sanitize_input($data['producto'] ?? null, 'string');


     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($no_tanque) || empty($capacidad) || empty($producto)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        TanqueAlmacenamiento::create([
            'id_estacion' => $this->estacionModulo(),
            'no_tanque' => $no_tanque,
            'capacidad' => $capacidad,
            'producto' => $producto,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Tanque de Almacenamiento creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Tanque de Almacenamiento'
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

    $registro = TanqueAlmacenamiento::where(
        'id_estacion',
        $this->estacionModulo()
    )->find($data['id_tanque']);

    if (!$registro) {

        echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
        ]);

        return;
    }

    try{

        $registro->update([

            'no_tanque' => sanitize_input(
                $data['no_tanque'] ?? null,
                'int'
            ),

            'capacidad' => sanitize_input(
                $data['capacidad'] ?? null,
                'string'
            ),

            'producto' => sanitize_input(
                $data['producto'] ?? null,
                'string'
            )
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Tanque de Almacenamiento actualizado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar Tanque de Almacenamiento'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = TanqueAlmacenamiento::where(
            'id_estacion',
            $this->estacionModulo()
        )->find($data['id']);

        if(!$registro){

            echo json_encode([
                'success' => false,
                'message' => 'El Registro no se puede eliminar'
            ]);

            return;
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Tanque de Almacenamiento eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Extintor'
        ]);
    }

    }


}