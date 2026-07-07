<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\CambioPrecio;
use App\Models\Sasisopa\Dispensario;
use App\Models\Sasisopa\DispensarioAperturaBitacora;

use Illuminate\Database\Capsule\Manager as Capsule;

class CambioPrecioController extends BaseController{
    protected string $modulo = 'sasisopa';

    public function index()
    {

    $title = 'CAMBIO PRECIO';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');        
        Breadcrumb::add($title, '');


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
                '/js/cambioprecio/index.datatable.init.js?v=1.3',
                '/js/cambioprecio/index.actions.init.js?v=1.2'
            ],
            'help' => true
        ];
        
        View::render('cambioprecio/index', $data,'sasisopa');

    }

    public function datetable(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

        $rows = CambioPrecio::query()
            ->where('id_estacion', $this->estacionId())
            ->orderByDesc('id')
            ->get();

        $data = [];

        foreach ($rows as $row) {

            $estado = [
                "titulo"     => "",
                "color_css"  => "",
                "color_hexa" => "",
                "estatus"    => false
            ];

            if ($row->estado == 0) {

                    $estado = [
                        'icon' => 'ti-alert-triangle',
                        'color_css' => 'text-warning'
                    ];

                } else {

                    $estado = [
                        'icon' => 'ti-check',
                        'color_css' => 'text-success'
                    ];

                }

            $data[] = [

                "id" => $row->id,
                "fecha" => $row->fecha->format('Y-m-d'),
                "hora" => $row->hora,
                "gsuper" => $row->gsuper,
                "gpremium" => $row->gpremium,
                "gdiesel" => $row->gdiesel,
                "estado" => $estado

            ];

        }

        echo json_encode([
            "data" => $data,
            "permisos" => [
                "editar" => $permisoEditar,
                "eliminar" => $permisoEliminar
            ]
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            "data" => [],
            "success" => false,
            "message" => $e->getMessage()
        ]);

    }

    exit;
}

public function create(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $data = json_decode(file_get_contents('php://input'), true);


        $idEstacion = $this->estacionId();
        $idUsuario  = $this->userId();

        Capsule::transaction(function () use ($data, $idEstacion, $idUsuario) {

            $productos = $this->validarProductos(
                $idEstacion,
                (float)$data['gsuper'],
                (float)$data['gpremium'],
                (float)$data['gdiesel']
            );

            /*
            |--------------------------------------------------------------------------
            | Crear bitácoras únicamente cuando cambie el precio
            |--------------------------------------------------------------------------
            */

            if ($productos['super']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $data['fecha'],
                    $data['hora'],
                    'G SUPER',
                    $idUsuario,
                    $data['gsuper']
                );

            }

            if ($productos['premium']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $data['fecha'],
                    $data['hora'],
                    'G PREMIUM',
                    $idUsuario,
                    $data['gpremium']
                );

            }

            if ($productos['diesel']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $data['fecha'],
                    $data['hora'],
                    'G DIESEL',
                    $idUsuario,
                    $data['gdiesel']
                );

            }

            /*
            |--------------------------------------------------------------------------
            | Guardar cambio de precio
            |--------------------------------------------------------------------------
            */

            CambioPrecio::create([

                'id_estacion' => $idEstacion,

                'fecha' => $data['fecha'],

                'hora' => $data['hora'],

                'gsuper' => $data['gsuper'],

                'gpremium' => $data['gpremium'],

                'gdiesel' => $data['gdiesel'],

                'estado' => 0

            ]);

        });

        echo json_encode([
            'success' => true,
            'message' => 'Cambio de precio agregado correctamente.'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}

private function validarProductos(
    int $idEstacion,
    float $gsuper,
    float $gpremium,
    float $gdiesel
): array
{

    $ultimo = CambioPrecio::query()
        ->where('id_estacion', $idEstacion)
        ->latest('id')
        ->first();

    if (!$ultimo) {

        return [
            'super'   => $gsuper > 0,
            'premium' => $gpremium > 0,
            'diesel'  => $gdiesel > 0
        ];

    }

    return [

        'super' => $gsuper > 0 &&
            abs((float)$ultimo->gsuper - $gsuper) > 0.0001,

        'premium' => $gpremium > 0 &&
            abs((float)$ultimo->gpremium - $gpremium) > 0.0001,

        'diesel' => $gdiesel > 0 &&
            abs((float)$ultimo->gdiesel - $gdiesel) > 0.0001,

    ];

}

private function crearBitacoraCambioPrecio(
    int $idEstacion,
    string $fecha,
    string $horaInicio,
    string $producto,
    int $idUsuario,
    string $detalle
): void
{

    $dispensarios = Dispensario::query()
        ->where('id_estacion', $idEstacion)
        ->where('estado', 1)
        ->orderBy('no_dispensario')
        ->get();

    foreach ($dispensarios as $dispensario) {

        $mangueras = 0;

        switch ($producto) {

            case 'G SUPER':
                $mangueras = (int) $dispensario->producto1;
                break;

            case 'G PREMIUM':
                $mangueras = (int) $dispensario->producto2;
                break;

            case 'G DIESEL':
                $mangueras = (int) $dispensario->producto3;
                break;

        }

        $this->guardarRegistroBitacora(

            $dispensario->id,

            $fecha,

            $horaInicio,

            '',

            $producto,

            $idUsuario,

            $detalle,

            $mangueras

        );

    }

}

private function guardarRegistroBitacora(
    int $idDispensario,
    string $fecha,
    string $horaInicio,
    string $horaTermino,
    string $producto,
    int $idUsuario,
    string $detalle,
    int $mangueras
): void
{

    if ($mangueras <= 0) {
        return;
    }

    for ($lado = 1; $lado <= $mangueras; $lado++) {

        DispensarioAperturaBitacora::create([

            'id_dispensario' => $idDispensario,

            'fecha' => $fecha,

            'hora_inicio' => $horaInicio,

            'hora_termino' => $horaTermino,

            'lado' => $lado,

            'producto' => $producto,

            'clave' => 'CAMP',

            'motivo' => 'Cambio de precio',

            'responsable' => $idUsuario,

            'detalle' => $detalle

        ]);

    }

}

public function delete(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $data = json_decode(file_get_contents('php://input'), true);

        $id = (int)($data['id'] ?? 0);

        $cambio = CambioPrecio::find($id);

        if (!$cambio) {

            echo json_encode([
                'success' => false,
                'message' => 'El cambio de precio no existe.'
            ]);

            return;

        }

        Capsule::transaction(function () use ($cambio) {

           $this->eliminarBitacoraCambioPrecio(
                $cambio->id_estacion,
                $cambio->fecha->format('Y-m-d'),
                $cambio->hora
            );

            $cambio->delete();

        });

        echo json_encode([
            'success' => true,
            'message' => 'Cambio de precio eliminado correctamente.'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}

private function eliminarBitacoraCambioPrecio(
    int $idEstacion,
    string $fecha,
    string $hora
): void
{
    $idsDispensarios = Dispensario::query()
        ->where('id_estacion', $idEstacion)
        ->where('estado', 1)
        ->pluck('id');

    DispensarioAperturaBitacora::query()
        ->whereIn('id_dispensario', $idsDispensarios)
        ->where('fecha', $fecha)
        ->where('hora_inicio', $hora)
        ->where('clave', 'CAMP')
        ->where('motivo', 'Cambio de precio')
        ->delete();
}
}