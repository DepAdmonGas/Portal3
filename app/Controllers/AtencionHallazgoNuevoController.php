<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Sasisopa\AtencionHallazgo;
use App\Models\Sasisopa\AtencionHallazgoDetalle;
use App\Models\Sasisopa\AtencionHallazgoEvidencia;
use App\Models\Sasisopa\Sasisopa;

class AtencionHallazgoNuevoController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index($id){

    $title = 'AGREGAR ATENCION';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN', '/sasisopa/monitoreo-verificacion-evaluacion');
        Breadcrumb::add('ATENCIÓN DE HALLAZGOS', '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $hallazgos = AtencionHallazgo::findOrFail($id);
        
        $hallazgos->fecha =
            $hallazgos->fecha_auditoria &&
            $hallazgos->fecha_auditoria->year > 1900
                ? $hallazgos->fecha_auditoria->format('Y-m-d')
                : '';

            $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'hallazgos' => $hallazgos,
            'links' =>[ 
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/monitoreoverificacionevaluacion/atencionhallazgosnuevo.actions.init.js?v=1.4'
            ],
            'help' => false
        ];
        
        View::render('monitoreoverificacionevaluacion/atencion-hallazgos-nuevo', $data,'sasisopa');
        
    }


public function updateEncabezados()
{
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'),true);
    $hallazgo = AtencionHallazgo::find($data['id']);

    $hallazgo->update([
        'fecha_auditoria' =>
            $data['fecha_auditoria'],
        'no_control' =>
            $data['no_control'],
        'tipo_auditoria' =>
            $data['tipo_auditoria']
    ]);

    echo json_encode([
        'success' => true
    ]);

    exit;
}

public function detalle(int $id)
{
    header('Content-Type: application/json');

    $detalles =  AtencionHallazgoDetalle::with([
            'sasisopa',
            'evidencias'
        ])
        ->where('id_atencion', $id)
        ->get();

    $data = [];

    foreach ($detalles as $detalle) {

        $data[] = [
            'id' => $detalle->id,
            'id_sasisopa' =>  $detalle->sasisopa?->id,
            'sasisopa' => $detalle->sasisopa?->nombre,
            'hallazgos' => $detalle->hallazgos,
            'accion' => $detalle->accion,
            'fecha' => $detalle->fecha_implementacion->format('Y-m-d'),
            'fecha_larga' => formatearFecha($detalle->fecha_implementacion->format('Y-m-d')),
            'cumplimiento' =>
                $detalle->evidencias->count()
                    ? '100%'
                    : '0%',

            'evidencias' => $detalle->evidencias
            ->map(function ($evidencia) {

                return [
                    'id' => $evidencia->id,
                    'archivo' => $evidencia->archivo,
                    'url' =>
                        '/uploads/archivos/atencion-hallazgos/' .
                        $evidencia->archivo
                ];
            })
            ->values()
                
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

    exit;
}

public function sasisopaList()
{
    $idAtencion = $_GET['id_atencion'] ?? 0;

    $idsUtilizados = AtencionHallazgoDetalle::where(
        'id_atencion',
        $idAtencion
    )
    ->pluck('id_sasisopa')
    ->toArray();

    $data = Sasisopa::whereNotIn(
        'id',
        $idsUtilizados
    )
    ->orderBy('numero_sasisopa')
    ->get();

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

    exit;
}

public function createDetalle()
{
    header('Content-Type: application/json');

    try {

    $data = json_decode(file_get_contents('php://input'),true);

    $detalle =
        AtencionHallazgoDetalle::create(
            [
                'id_atencion' => $data['id'],
                'id_sasisopa' => $data['id_sasisopa'],
                'hallazgos' => $data['hallazgos'],
                'accion' => $data['accion'],
                'fecha_implementacion' => $data['fecha_implementacion']
            ]
        );

    echo json_encode([
        'success' => true,
        'message' => 'Hallazgo creado',
    ]);

    } catch (\Throwable $e) {

              echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
}

public function updateDetalle()
{
    header('Content-Type: application/json');

    try {

        $data = json_decode(file_get_contents('php://input'),true);

        if (!isset($data['editIdHallazgo']) || empty($data['editIdHallazgo'])) {
            echo json_encode([
            'success' => false,
            'message' => 'ID de detalle requerido'
        ]);
        }

        $detalle = AtencionHallazgoDetalle::find($data['editIdHallazgo']);

        if (!$detalle) {
 
            echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
            ]);

        }

        $detalle->update([
            'id_atencion' => $data['id'],
            'id_sasisopa' => $data['id_sasisopa'],
            'hallazgos' => $data['hallazgos'],
            'accion' => $data['accion'],
            'fecha_implementacion' => $data['fecha_implementacion']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Hallazgo actualizado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function deleteHallazgo()
{
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);
    $detalle = AtencionHallazgoDetalle::find($data['id']);

    if (!$detalle) {

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar'
        ]);

        return;
    }

    $detalle->evidencias()->delete();

    $detalle->delete();

    echo json_encode([
        'success' => true,
        'message' => 'Hallazgo eliminado'
    ]);

    exit;
}

public function evidencias($idHallazgo)
{
    $data =
        AtencionHallazgoEvidencia::where(
            'id_hallazgo',
            $idHallazgo
        )
        ->get()
        ->map(function($item){

            return [

                'id' => $item->id,
                'archivo' => $item->archivo,
                'url' => '/uploads/archivos/atencion-hallazgos/' .
                $item->archivo
            ];
        });

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

    exit;
}

public function createEvidencia()
{
    try {

        if(empty($_FILES['archivo'])){

            echo json_encode([
            'success' => true,
            'message' => 'Archivo requerido'
            ]);

        }

        $idHallazgo = $_POST['id_hallazgo'];
        $file = $_FILES['archivo'];
        $extension =
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            );

        $nombre =
            'Atencion-Hallazgo-' .
            time() .
            '.' .
            $extension;

        $ruta =
            __DIR__ . '../../../public/uploads/archivos/atencion-hallazgos/' .
            $nombre;

        move_uploaded_file(
            $file['tmp_name'],
            $ruta
        );

        AtencionHallazgoEvidencia::create([

            'id_hallazgo' => $idHallazgo,
            'archivo' => $nombre
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Evidencia agregada'
        ]);

    } catch (\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function deleteEvidencia()
{
    try {

        $data = json_decode(file_get_contents('php://input'),true);
        $evidencia = AtencionHallazgoEvidencia::find($data['id']);

        if(!$evidencia){

            echo json_encode([
            'success' => true,
            'message' => 'Registro no encontrado'
            ]);

        }

        $archivo =
             __DIR__ . '../../../public/uploads/archivos/atencion-hallazgos/' .
            $evidencia->archivo;

        if(file_exists($archivo)){
            unlink($archivo);
        }

        $evidencia->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Evidencia eliminada'
        ]);

    } catch (\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

}