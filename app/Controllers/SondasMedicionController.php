<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\SondasMedicion;

class SondasMedicionController extends BaseController
{

 protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'Configuración de Sondas de Medición';

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
                '/js/controlactividadproceso/sondasmedicion.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/sondasmedicion.action.init.js?v=' . time(),

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/sondas-medicion',$data,'sasisopa');
       
    }

    public function datatable(){

      $data = SondasMedicion::where(
        'id_estacion',
        $this->estacionId()
        )
        ->where('estado', 1)
        ->orderByDesc('no_sonda')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'no_sonda' => $item->no_sonda,
                'marca' => $item->marca,
                'modelo' => $item->modelo,
                'ubicacion' => $item->ubicacion,
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

    $no_sonda = sanitize_input($data['no_sonda'] ?? null, 'int');
    $marca = sanitize_input($data['marca'] ?? null, 'string');
    $modelo = sanitize_input($data['modelo'] ?? null, 'string');
    $ubicacion = sanitize_input($data['ubicacion'] ?? null, 'string');


     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($no_sonda) || empty($marca) || empty($modelo) || empty($ubicacion)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        SondasMedicion::create([
            'id_estacion' => $this->estacionId(),
            'no_sonda' => $no_sonda,
            'marca' => $marca,
            'modelo' => $modelo,
            'ubicacion' => $ubicacion,
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Sonda de Medición creada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Sonda de Medición'
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

    $registro = SondasMedicion::where(
        'id_estacion',
        $this->estacionId()
    )->find($data['id_sonda']);

    if (!$registro) {

        echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
        ]);

        return;
    }

    try{

        $registro->update([

            'no_sonda' => sanitize_input(
                $data['no_sonda'] ?? null,
                'int'
            ),

            'marca' => sanitize_input(
                $data['marca'] ?? null,
                'string'
            ),

            'modelo' => sanitize_input(
                $data['modelo'] ?? null,
                'string'
            ),

            'ubicacion' => sanitize_input(
                $data['ubicacion'] ?? null,
                'string'
            )
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Sonda de Medición actualizada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar Sonda de Medición'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = SondasMedicion::find($data['id']);

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
            'message' => 'Sonda de Medición eliminada correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Extintor'
        ]);
    }

    }


}