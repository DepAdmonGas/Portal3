<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\MantenimientoQuincenal;

class BitacoraMantenimientoController extends BaseController{

    protected string $modulo = 'sasisopa';

        public function index()
    {

        $title = 'Mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
            ],
            'scripts' => [
                '/js/vendor.min.js',

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/bitacora-mantenimiento',$data,'sasisopa');
       
    }

    public function mantenimientoQuincenal(){

    $title = 'Mantenimiento Quincenal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Mantenimiento','/sasisopa/control-actividades-procesos/bitacora-mantenimiento');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $carpeta = '';
        if ($this->estacionId() == 1) {
        $carpeta = "interlomas";
        }else if ($this->estacionId() == 2) {
        $carpeta = "palosolo";
        }else if ($this->estacionId() == 4) {
        $carpeta = "gasomira";
        }else if ($this->estacionId() == 5) {
        $carpeta = "valleguadalupe";
        }else if ($this->estacionId() == 6) {
        $carpeta = "esmegas";
        }


        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'carpeta' => $carpeta,
            'links' => [
                 '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/mantenimientoquincenal.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/mantenimientoquincenal.action.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/bitacora-mantenimiento-quincenal',$data,'sasisopa');
    }

    public function datatable(){

    $data = MantenimientoQuincenal::query()

        ->where(
            'id_estacion',
            $this->estacionId()
        )

        ->orderBy('fechacreacion', 'desc')

        ->get()

        ->map(function ($item) {

            return [

                'id' => $item->id,

                'folio' => str_pad(
                    $item->folio,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),

                'fecha' => $item->fechacreacion->format('Y-m-d'),

                'formato1' => $item->formato1,
                'formato2' => $item->formato2,
                'formato3' => $item->formato3,
                'formato4' => $item->formato4,
                'formato5' => $item->formato5,
                'formato6' => $item->formato6,
                'formato7' => $item->formato7,
            ];
        });

    echo json_encode([

        'data' => $data,

        'permisos' => [

            'editar' => ModuloService::validaPermiso(
                $this->modulo,
                'editar'
            ),

            'eliminar' => ModuloService::validaPermiso(
                $this->modulo,
                'eliminar'
            )

        ]
    ]);

    }

public function create()
{
    header('Content-Type: application/json');

    if (!ModuloService::validaPermiso($this->modulo,'editar')) {

        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso'
        ]);

        exit;
    }

    try {

        $fecha = trim($_POST['Fecha'] ?? '');

        if (empty($fecha)) {
            throw new \Exception(
                'La fecha es obligatoria'
            );
        }

        $folio = $this->folioMantenimientoQuincenal($this->estacionId(),$fecha);

        $carpetaFisica = __DIR__ . '../../../public/uploads/archivos/mantenimiento-quincenal/';

        if (!file_exists($carpetaFisica)) {

            mkdir_safe(
                $carpetaFisica,
                true
            );
        }

        $registro = [

            'id_estacion'   => $this->estacionId(),
            'id_empleado'   => $this->userId(),
            'fechacreacion' => $fecha,
            'folio'         => $folio,
            'formato1' => '',
            'formato2' => '',
            'formato3' => '',
            'formato4' => '',
            'formato5' => '',
            'formato6' => '',
            'formato7' => ''
        ];

        $timestamp = time();

        for ($i = 1; $i <= 7; $i++) {

            $campo = "Formato{$i}_file";

            if (
                empty($_FILES[$campo]) ||
                $_FILES[$campo]['error'] !== UPLOAD_ERR_OK
            ) {
                continue;
            }

            $archivo = $_FILES[$campo];

            $extension = strtolower(
                pathinfo(
                    $archivo['name'],
                    PATHINFO_EXTENSION
                )
            );

            if ($extension !== 'pdf') {

                throw new \Exception(
                    "El archivo {$i} debe ser PDF"
                );
            }

            $nombreArchivo = sprintf(
                'MQ%d-F%d-%d.pdf',
                $this->estacionId(),
                $i,
                $timestamp
            );

            $rutaFisica =
                $carpetaFisica .
                $nombreArchivo;

            if (
                !move_uploaded_file(
                    $archivo['tmp_name'],
                    $rutaFisica
                )
            ) {

                throw new \Exception(
                    "No fue posible guardar el archivo {$i}"
                );
            }

            $registro["formato{$i}"] =
                'archivos/mantenimiento-quincenal/' .
                $nombreArchivo;
        }

        MantenimientoQuincenal::create(
            $registro
        );

        echo json_encode([
            'success' => true,
            'folio'   => $folio,
            'message' => 'Mantenimiento creado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function folioMantenimientoQuincenal(
    int $idEstacion,
    string $fecha
): int {

    $ultimo = MantenimientoQuincenal::query()
        ->where('id_estacion', $idEstacion)
        ->orderByDesc('folio')
        ->first();

    if (!$ultimo) {
        return 1;
    }

    $anioActual = date(
        'Y',
        strtotime($fecha)
    );

    $anioUltimo = date(
        'Y',
        strtotime($ultimo->fechacreacion)
    );

    return $anioActual === $anioUltimo
        ? ((int) $ultimo->folio + 1)
        : 1;
}

public function update()
{
    header('Content-Type: application/json');

    try {

        $id = (int) ($_POST['id'] ?? 0);
        $registro = MantenimientoQuincenal::findOrFail($id);
        $fecha = trim($_POST['Fecha'] ?? '');

        if (!$fecha) {

            throw new \Exception(
                'La fecha es obligatoria'
            );
        }

        $registro->fechacreacion = $fecha;
        $carpetaFisica = __DIR__ . '../../../public/uploads/archivos/mantenimiento-quincenal/';

        if (!file_exists($carpetaFisica)) {

            mkdir_safe(
                $carpetaFisica,
                true
            );
        }

        $timestamp = time();

        for ($i = 1; $i <= 7; $i++) {

            $campo =
                "Formato{$i}_file";

            if (empty($_FILES[$campo]) ||
                $_FILES[$campo]['error']
                    !== UPLOAD_ERR_OK
            ) {
                continue;
            }

            $archivo = $_FILES[$campo];

            $extension =
                strtolower(
                    pathinfo(
                        $archivo['name'],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== 'pdf') {

                throw new \Exception(
                    "El archivo {$i} debe ser PDF"
                );
            }

            $campoBD = "formato{$i}";

            if (!empty($registro->$campoBD)) {

                $archivoAnterior = __DIR__ .
                    '../../../public/uploads/' .
                    $registro->$campoBD;

                if (file_exists($archivoAnterior)) {
                    unlink( $archivoAnterior);
                }
            }

            $nombreArchivo = sprintf(
                    'MQ%d-F%d-%d.pdf',
                    $this->estacionId(),
                    $i,
                    $timestamp
                );

            $rutaFisica =
                $carpetaFisica .
                $nombreArchivo;

            if (
                !move_uploaded_file(
                    $archivo['tmp_name'],
                    $rutaFisica
                )
            ) {

                throw new \Exception(
                    "Error al guardar archivo {$i}"
                );
            }

            $registro->$campoBD = 'archivos/mantenimiento-quincenal/' . $nombreArchivo;
        }

        $registro->save();

        echo json_encode([
            'success' => true,
            'message' =>
                'Mantenimiento actualizado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}
    public function delete()
{
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'),true);

    try {

        $id = (int) ($data['id'] ?? 0);

        $registro = MantenimientoQuincenal::findOrFail($id);


        for ($i = 1; $i <= 7; $i++) {

            $campo = "formato{$i}";

            if (empty($registro->$campo)) {
                continue;
            }

            $archivo = __DIR__ . '../../../public/uploads/' . $registro->$campo;

            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' =>
                'Mantenimiento eliminado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar'
        ]);
    }

    exit;
}

}