<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Services\ModuloService;
use App\Models\Sasisopa\DisenoConstruccion;
use App\Models\Sasisopa\OperacionMantenimiento;
use Dompdf\Dompdf;
use Dompdf\Options;

class MejoresPracticasEstandaresController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

        $title = '9. MEJORES PRÁCTICAS Y ESTÁNDARES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/mejorespracticas/disenoconstruccion.datatable.init.js?v=1.0.5',
                '/js/mejorespracticas/operacionmantenimiento.datatable.init.js?v=1.0.2',
                '/js/mejorespracticas/index.action.init.js?v=1.0.1'
            ],
            'help' => true
        ];
        
        View::render('mejorespracticas/index', $data,'sasisopa');

    }

    public function datatableDisenoConstruccion()
    {

        $data = DisenoConstruccion::where(function ($query) {
            $query->where('estado', $this->estacionId())
                  ->orWhere('estado', 0);
        })
        ->select(['id', 'valor1', 'valor2'])
        ->get();

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'eliminar' =>ModuloService::validaPermiso($this->modulo,'eliminar')
            ]
        ]);
    }

    public function createDisenoConstruccion(){

    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($data['codigo']) || empty($data['area'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        DisenoConstruccion::create([
            'valor1' => $data['codigo'],
            'valor2' => $data['area'],
            'estado' => $this->estacionId()
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Diseño y Construcción creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Diseño y Construcción'
        ]);
    }

    }

    public function deleteDisenoConstruccion(){

     header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = DisenoConstruccion::where(
            'estado',
            $this->estacionId()
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
            'message' => 'Diseño y Construcción eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Diseño y Construcción'
        ]);
    }

    }

    public function pdfDisenoConstruccion(){

    $idEstacion = $this->estacionId();

    $registro = Estacion::find($idEstacion);

    if (!$registro) {
        return "No se encontró la información";
    }

   $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

   $disenos = DisenoConstruccion::where(function ($query) {
            $query->where('estado', $this->estacionId())
                  ->orWhere('estado', 0);
        })
        ->select(['id', 'valor1', 'valor2'])
        ->orderByDesc('id')
        ->get();
   
     $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Diseño y construcción</title>
    <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
    </head>
    <body>

     <div class="text-center"><img src="' . $logo . '" style="width:250px;"></div>

        <div class="text-center mt-3">' . $registro->permisocre . '</div>
        <div class="text-center mt-1">' . $registro->razonsocial . '</div>
        <div class="text-center mt-1">' . $registro->direccioncompleta . '</div>

        <h1 class="text-center mt-4">Diseño y construcción</h1>

        <table class="table">
            <thead>
                <tr>
                    <th width="50%">
                        Código, estándar, normatividad
                        o práctica de ingeniería
                    </th>
                    <th width="50%">
                        Área, maquinaria, equipo o
                        instalación a la que aplica
                    </th>
                </tr>
            </thead>
            <tbody>
    ';

    if ($disenos->count() > 0) {

        foreach ($disenos as $item) {

            $html .= '
            <tr>
                <td class="text-center">' . $item->valor1 . '</td>
                <td class="text-center">' . $item->valor2 . '</td>
            </tr>
            ';
        }

    } else {

        $html .= '
        <tr>
            <td colspan="2" class="text-center">No hay registros disponibles</td>
        </tr>
        ';
    }
    $html .= '</body>
    </html>
    ';

    // ======================
    // PDF
    // ======================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); 
    $dompdf->render();

    return $dompdf->stream(
        "Diseño-construcción.pdf",
        ["Attachment" => true]
    );

    }

    //--------------------------------------------------

    public function datatableOperacionMantenimiento(){

     $data = OperacionMantenimiento::where(function ($query) {
            $query->where('estado', $this->estacionId())
                  ->orWhere('estado', 0);
        })
        ->select(['id','fecha', 'norma', 'nombre', 'link'])
        ->get();

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'eliminar' =>ModuloService::validaPermiso($this->modulo,'eliminar')
            ]
        ]);

    }
    public function createOperacionMantenimiento(){

     header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($data['fecha']) || empty($data['norma']) || empty($data['nombre']) || empty($data['link']) ) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

    try{

        OperacionMantenimiento::create([
        'fecha' => $data['fecha'],
        'norma' => $data['norma'],
        'nombre' => $data['nombre'],
        'link' => $data['link'],
        'estado'=> $this->estacionId()
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Diseño y Construcción creado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al crear Diseño y Construcción'
        ]);
    }

    }
    public function deleteOperacionMantenimiento(){

     header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);

        $registro = OperacionMantenimiento::where(
            'estado',
            $this->estacionId()
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
            'message' => 'Operación y Mantenimiento eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Operación y Mantenimiento'
        ]);
    }

    }
    public function pdfOperacionMantenimiento(){
         $idEstacion = $this->estacionId();

    $registro = Estacion::find($idEstacion);

    if (!$registro) {
        return "No se encontró la información";
    }

   $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $data = OperacionMantenimiento::where(function ($query) {
            $query->where('estado', $this->estacionId())
                  ->orWhere('estado', 0);
        })
        ->select(['id','fecha', 'norma', 'nombre', 'link'])
        ->get();
   
     $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Operación y Mantenimiento</title>
    <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
    </head>
    <body class="fs-6">

     <div class="text-center"><img src="' . $logo . '" style="width:250px;"></div>

        <div class="text-center mt-3">' . $registro->permisocre . '</div>
        <div class="text-center mt-1">' . $registro->razonsocial . '</div>
        <div class="text-center mt-1">' . $registro->direccioncompleta . '</div>

        <h1 class="text-center mt-4">Operación y Mantenimiento</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Norma</th>
                    <th>Nombre</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
    ';

    if ($data->count() > 0) {

        foreach ($data as $item) {

            $html .= '
            <tr>
                <td class="text-center">' . $item->id . '</td>
                <td class="text-center">' . formatearFecha($item->fecha) . '</td>
                <td class="text-center">' . $item->norma . '</td>
                <td class="text-center">' . $item->nombre . '</td>
                <td class="text-center align-middle">
                <div><a style="width: 100%;height:20px;" href="' . $item->link . '" target="_blank" >Link</a></div>
                </td>
            </tr>
            ';
        }

    } else {

        $html .= '
        <tr>
            <td colspan="5" class="text-center">No hay registros disponibles</td>
        </tr>
        ';
    }
    $html .= '</body>
    </html>
    ';

    // ======================
    // PDF
    // ======================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); 
    $dompdf->render();

    return $dompdf->stream(
        "Operación-Mantenimiento.pdf",
        ["Attachment" => true]
    );

    }

}