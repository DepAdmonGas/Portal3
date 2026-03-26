<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Operativo\BitacoraAditivo;
use App\Models\Operativo\InventarioAditivo;
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
                '/assets/js/bitacora/aditivo.datatable.init.js?v=1.0',
                '/assets/js/bitacora/actions.init.js?v=1.0'
            ],
            'help' => false
        ];
        
        View::render('aditivo/index', $data,'main');
    }

    public function datatableAditivo(){

        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

         $aditivo = BitacoraAditivo::where('id_estacion', $this->estacionId())->get();

         echo json_encode([
            "data" => $aditivo,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar
            ]
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

    public function createAditivo(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }

        $litros     = $data['litros'] ?? null;
        $producto   = $data['producto'] ?? null;
        $galones    = $data['galones'] ?? 0;
        $fecha      = $data['fecha'] ?? null;
        $factura    = $data['no_factura'] ?? null;

        if (!$litros || !$producto || !$fecha) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes'
        ]);
        exit;
        }

        try {

            // INVENTARIO
            $inventario = InventarioAditivo::where('id_estacion', $this->estacionId())->first();

            if (!$inventario) {
            throw new \Exception('Inventario no encontrado');
            }

            // FOLIO
            $folio = BitacoraAditivo::where('id_estacion', $this->estacionId())->max('folio') + 1;
            $folio = $folio ?: 1;

            // CALCULAR INVENTARIO
            if ($producto === 'G SUPER' || $producto === 'G PREMIUM') {
                $inventarioFisico = $inventario->gasolina - $galones;
            } elseif ($producto === 'G DIESEL') {
                $inventarioFisico = $inventario->diesel - $galones;
            } else {
                throw new \Exception('Producto inválido');
            }

            // INSERTAR BITÁCORA
            BitacoraAditivo::create([
            'id_estacion'        => $this->estacionId(),
            'folio'              => $folio,
            'litros'             => $litros,
            'fecha'              => $fecha,
            'no_factura'         => $factura,
            'producto'           => $producto,
            'galones'            => $galones,
            'inventario_fisico'  => $inventarioFisico,
            'estado'             => 1
            ]);

            //  ACTUALIZAR INVENTARIO
            if ($producto === 'G SUPER' || $producto === 'G PREMIUM') {
                $inventario->gasolina = $inventarioFisico;
            } else {
                $inventario->diesel = $inventarioFisico;
            }

            $inventario->save();

            echo json_encode(['success' => true,'message' => 'Registro guardado correctamente']);
            exit;


        } catch (\Exception $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }

    }

    public function updateAditivo()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $id = $data['id'] ?? null;
        $noFactura = $data['no_factura'] ?? null;

        if (!$id || !$noFactura) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            return;
        }

        if (!ModuloService::validaPermiso('bitacora-aditivo', 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'Sin permisos'
            ]);
            return;
        }

        $registro = BitacoraAditivo::find($id);

        if (!$registro) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        $registro->no_factura = $noFactura;
        $registro->save();

        echo json_encode([
            'success' => true,
            'message' => 'Factura actualizada correctamente'
        ]);
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