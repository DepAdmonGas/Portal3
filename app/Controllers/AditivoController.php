<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Operativo\BitacoraAditivo;
use App\Models\Operativo\InventarioAditivo;
use App\Models\Operativo\InventarioAditivoHist;
use App\Models\Operativo\BitacoraReporte;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use Illuminate\Database\Capsule\Manager as Capsule;


class AditivoController extends BaseController{

     protected string $modulo = 'bitacora-aditivo';
     public function index(){

        $title = 'Bitácora de aditivo';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);
        //inventario de aditivo
        $inventario = InventarioAditivo::where('id_estacion', $this->estacionId())->first();

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'inventario' =>[
                'gasolina' => $inventario->gasolina,
                'diesel' => $inventario->diesel
            ],
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
        $permisos = [
        'eliminar' => ModuloService::validaPermiso($this->modulo, 'eliminar'),
        'editar' => ModuloService::validaPermiso($this->modulo, 'editar')
        ];

         $aditivo = BitacoraAditivo::where('id_estacion', $this->estacionId())->get();

         echo json_encode([
            "data" => $aditivo,
            "permisos" => $permisos
        ]);
        
        exit;
    }

    public function totalInventario()
    {
        $inventario = InventarioAditivo::where('id_estacion', $this->estacionId())->first();

        echo json_encode([
            'gasolina' => $inventario->gasolina ?? 0,
            'diesel'   => $inventario->diesel ?? 0
        ]);
        exit;
    }


    public function deleteAditivo(){

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

        // BITÁCORA
        $bitacora = BitacoraAditivo::find($id);

        if (!$bitacora) {
            echo json_encode(['success' => false, 'message' => 'Folio no encontrado']);
            exit;
        }

        if ($bitacora->estado == 0) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar un folio ya inactivo']);
            exit;
        }

        // INVENTARIO
        $inventario = InventarioAditivo::where('id_estacion', $bitacora->id_estacion)->first();

        if (!$inventario) {
            echo json_encode(['success' => false, 'message' => 'Inventario no encontrado']);
            exit;
        }

        $producto = $bitacora->producto;
        $galones  = $bitacora->galones;
        $folio    = $bitacora->folio;

        // CALCULAR INVENTARIO
        if ($producto === "G SUPER" || $producto === "G PREMIUM") {
            $inventario->gasolina += $galones;
            $aditivoNombre = 'Gasolina Hitec 6590C';
        } elseif ($producto === "G DIESEL") {
            $inventario->diesel += $galones;
            $aditivoNombre = 'Diesel Hitec 4133G';
        }

        Capsule::beginTransaction();

        try {

            // ELIMINAR (SOFT)
            $bitacora->estado = 0;
            $bitacora->save();

            // INVENTARIO
            $inventario->save();

            // HISTÓRICO
            InventarioAditivoHist::create([
                'id_estacion' => $bitacora->id_estacion,
                'aditivo'     => $aditivoNombre,
                'galones'     => $galones,
                'detalle'     => 'Se agrega aditivo por cancelación del folio 00' . $folio
            ]);

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Folio eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar',
                'error'   => $e->getMessage()
            ]);
        }

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

        
        $litros     = sanitize_input($data['litros'] ?? null, 'float');
        $producto   = sanitize_input($data['producto'] ?? null, 'string');
        $galones    = sanitize_input($data['galones'] ?? 0, 'float');
        $fecha      = sanitize_input($data['fecha'] ?? null, 'string');
        $factura    = sanitize_input($data['no_factura'] ?? null, 'string');

        // Validar campos obligatorios
        $errors = validate_input($data, [
            'litros' => 'required|numeric',
            'producto' => 'required|max:100',
            'fecha' => 'required|max:20'
        ]);

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        if (!$litros || !$producto || !$fecha) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes'
        ]);
        exit;
        }
        
        Capsule::beginTransaction();

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

            Capsule::commit();

            echo json_encode(['success' => true,'message' => 'Registro guardado correctamente']);
            exit;


        } catch (\Exception $e) {

            Capsule::rollBack();

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
        
        $id = sanitize_input($data['id'] ?? null, 'int');
        $noFactura = sanitize_input($data['no_factura'] ?? null, 'string');

        if (!$id || !$noFactura) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            return;
        }

        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
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

    //--------- Reporte Bitacora Aditivo --------------
    //-------------------------------------------------

    public function reporte(){

        $title = 'Reporte aditivo';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Bitácora de aditivo', '/bitacora-aditivo');        
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                 '/assets/js/bitacora/reporte.datatable.init.js?v=1.0',
                '/assets/js/bitacora/reporte.actions.init.js?v=1.0'
            ]
        ];
        
        View::render('aditivo/reporte', $data,'main');
    }

    public function datatableReporte(){
         // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

         $reporte = BitacoraReporte::where('id_estacion', $this->estacionId())->get();

         echo json_encode([
            "data" => $reporte,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "descargar" => $permisoDescargar,
                "editar"   => $permisoEditar
            ]
        ]);
        
        exit;
    }

    public function createReporte()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Permisos
        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }

        
        $fecha = sanitize_input($_POST['fecha'] ?? null, 'string');
        $file  = $_FILES['documento'] ?? null;

        // Validar campos obligatorios
        $errors = validate_input($_POST, [
            'fecha' => 'required|max:20'
        ]);

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        if (!$fecha) {
            echo json_encode([
                'success' => false,
                'message' => 'La fecha es obligatoria'
            ]);
            exit;
        }

        // CONFIG RUTA
        $carpeta = __DIR__ . '../../../public/uploads/archivos/';

        // SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
        if (!file_exists($carpeta)) {
            mkdir_safe($carpeta, true);
        }

        $nombreArchivo = null;

        try {

            // SUBIR ARCHIVO (opcional)
            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                // Validar extensión
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // nombre único
                $nombreArchivo = uniqid('rep_') . '.' . $extension;

                $rutaDestino = $carpeta . $nombreArchivo;

                if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                    throw new \Exception('No se pudo guardar el archivo');
                }
            }

            // GUARDAR EN BD
            BitacoraReporte::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario'  => $this->userId(),
                'fecha'       => $fecha,
                'hora'        => date('H:i:s'),
                'documento'   => $nombreArchivo
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Reporte guardado correctamente'
            ]);

        } catch (\Throwable $e) {

            // Si falla BD, borrar archivo
            if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
                unlink($carpeta . $nombreArchivo);
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteReporte(){
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

            try {

            // Buscar registro
            $reporte = BitacoraReporte::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

            // Ruta archivo
            $rutaBase = __DIR__ . '../../../public/uploads/archivos/bitacora-aditivo/';
            $rutaArchivo = $rutaBase . $reporte->documento;

            // TRANSACCIÓN
            Capsule::beginTransaction();

            // Eliminar archivo si existe
            if ($reporte->documento && file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }

            // Eliminar registro (puedes usar delete o estado = 0)
            $reporte->delete();

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Reporte eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //----------- Inventario -----------------
    //----------------------------------------
    public function inventario(){

        $title = 'Inventario aditivo';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);
        //inventario de aditivo
        $inventario = InventarioAditivo::where('id_estacion', $this->estacionId())->first();

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Bitácora de aditivo', '/bitacora-aditivo');        
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'inventario' =>[
                'gasolina' => $inventario->gasolina,
                'diesel' => $inventario->diesel
            ],
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/bitacora/inventario.datatable.init.js?v=1.0',
                '/assets/js/bitacora/inventario.actions.init.js?v=1.0'
            ]
        ];
        
        View::render('aditivo/inventario', $data,'main');
    }

    public function datatableInventario(){

        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

         $inventario = InventarioAditivoHist::where('id_estacion', $this->estacionId())->get();

         echo json_encode([
            "data" => $inventario,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar
            ]
        ]);
        
        exit;
    }

    public function createInventario(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }
 
        $gasolina = sanitize_input($data['gasolina'] ?? 0, 'float');
        $diesel   = sanitize_input($data['diesel'] ?? 0, 'float');

        if ($gasolina === 0 && $diesel === 0) {
        echo json_encode(['success' => false, 'message' => 'No se ingresado ningun aditivo']);
        exit;
        }

        Capsule::beginTransaction();

        try {
        // INVENTARIO
        $inventario = InventarioAditivo::firstOrCreate(
            ['id_estacion' => $this->estacionId()],
            ['gasolina' => 0, 'diesel' => 0]
        );

        if (!$inventario) {
        throw new \Exception('Inventario no encontrado');
        }

        if($gasolina > 0){

             InventarioAditivoHist::create([
            'id_estacion'        => $this->estacionId(),
            'aditivo'              => 'Gasolina Hitec 6590C',
            'galones'             => $gasolina,
            'detalle'              => 'Se agrega aditivo'
            ]);

            $inventario->gasolina += $gasolina;

        }

        if($diesel > 0){

             InventarioAditivoHist::create([
            'id_estacion'        => $this->estacionId(),
            'aditivo'              => 'Diesel Hitec 4133G',
            'galones'             => $diesel,
            'detalle'              => 'Se agrega aditivo'
            ]);

            $inventario->diesel += $diesel;
            
        }        
        
        $inventario->save();

        Capsule::commit();

         echo json_encode(['success' => true,'message' => 'Registro guardado correctamente']);
            exit;

        } catch (\Exception $e) {
            
            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }

    }


}