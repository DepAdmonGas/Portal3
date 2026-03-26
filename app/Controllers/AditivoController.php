<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Operativo\BitacoraAditivo;
use App\Services\ModuloService;
use App\Core\Breadcrumb;

class AditivoController extends BaseController{

    protected string $title = 'Bitácora de aditivo';
     protected string $modulo = 'bitacora-aditivo';
     public function index(){

        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($this->title, '');

        $data = [
            'title' => $this->title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/bitacora/aditivo.datatable.init.js?v=1.3'
            ],
            'help' => false
        ];
        
        View::render('aditivo/index', $data,'main');
    }

    public function datatableAditivo(){
         $aditivo = BitacoraAditivo::where('id_estacion', $this->estacionId())->get();

         echo json_encode([
            "data" => $aditivo
        ]);
        
        exit;
    }

    public function deleteAditivo(){
    // Leer JSON enviado por Axios
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso para eliminar'
        ]);
        exit;
    }


    if (!$id) {
    echo json_encode(['success' => false,'message' => 'ID requerido']);
    exit;
    }

    // Buscar el folio
    $bitacora = BitacoraAditivo::find($id);

    if (!$bitacora) {
    echo json_encode(['success' => false,'message' => 'Folio no encontrado']);
    exit;
    }

    if ($bitacora->estado == 0) {
    echo json_encode(['success' => false,'message' => 'No se puede eliminar un folio ya inactivo']);
    exit;
    }

    // Soft delete: cambiar estatus a 0
    $bitacora->estado = 0;
    $bitacora->save();

    // Devolver respuesta JSON
    echo json_encode(['success' => true,'message' => 'Folio eliminado correctamente']);
    exit;
    }

    public function reporte(){
        $data = [
            'title' => 'Reporte aditivo',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js'
            ]
        ];
        
        View::render('aditivo/reporte', $data,'main');
    }

    public function inventario(){
        $data = [
            'title' => 'Inventario aditivo',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js'
            ]
        ];
        
        View::render('aditivo/inventario', $data,'main');
    }

}