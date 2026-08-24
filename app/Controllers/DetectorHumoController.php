<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\DetectorHumo;

class DetectorHumoController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

    $title = 'Detector de Humo';

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
                '/js/controlactividadproceso/detectorhumo.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/detectorhumo.action.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/detector-humo',$data,'sasisopa');
    }

    public function datatable(){

        $data = DetectorHumo::where('id_estacion', $this->estacionId())
        ->where('estado', 1)
        ->get();

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'eliminar' =>ModuloService::validaPermiso($this->modulo,'eliminar')
            ]
        ]);

    }

    public function create(){

      header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

    
    $detector = sanitize_input($data['detector'] ?? null, 'int');
    $ubicacion = sanitize_input($data['ubicacion'] ?? null, 'string');

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($detector) || empty($ubicacion)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        DetectorHumo::create([
            'id_estacion' => $this->estacionId(),
            'no_detector' => $detector,
            'ubicacion' => $ubicacion,
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Detector de Humo creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Detector de Humo'
        ]);
    }

    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = DetectorHumo::find($data['id']);

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
            'message' => 'Detector de Humo eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Detector de Humo'
        ]);
    }

    }
}