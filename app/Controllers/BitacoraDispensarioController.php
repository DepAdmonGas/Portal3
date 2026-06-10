<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\DispensarioAperturaBitacora;
use App\Models\Sasisopa\Dispensario;
use App\Models\Estacion;
use Carbon\Carbon;
class BitacoraDispensarioController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

    $title = 'Bitácora de registro de eventos PROFECO';

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
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/bitacoradispensario.datatable.init.js?v=1.2',
                '/js/controlactividadproceso/bitacoradispensario.action.init.js?v=1.3'

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/bitacora-dispensario',$data,'sasisopa');
        
    }

    public function datatable()
    {

    $year = sanitize_input($_GET['year'] ?? null,'int');
    $mes = sanitize_input($_GET['mes'] ?? null,'int');

        $data = DispensarioAperturaBitacora::query()
            ->when($year, function ($q) use ($year) {
                $q->whereYear('fecha',$year);
            })
            ->when($mes, function ($q) use ($mes) {
                $q->whereMonth('fecha',$mes);
            })
            ->with(['dispensario','usuario'])
            ->whereHas(
                'dispensario',
                function ($query) {
                    $query->where(
                        'id_estacion',
                        $this->estacionId()
                    );
                }
            )

            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'id_estacion' => $item->dispensario?->id_estacion,
                    'no_dispensario' => $item->dispensario?->no_dispensario,
                    'marca' => $item->dispensario?->marca,
                    'modelo' => $item->dispensario?->modelo,
                    'serie' => $item->dispensario?->serie,
                    'fecha' => $item->fecha->format('Y-m-d'),
                    'fecha_larga' => formatearFecha($item->fecha->format('Y-m-d')),
                    'hora_inicio' => Carbon::createFromFormat('H:i:s',$item->hora_inicio)->format('g:i a'),
                    'hora_termino' => Carbon::createFromFormat('H:i:s',$item->hora_termino)->format('g:i a'),
                    'lado' => $item->lado,
                    'producto' => $item->producto,
                    'clave_motivo' => $item->clave.'(' . $item->motivo . ')',
                    'responsable' => $item->usuario?->nombre,
                    'detalle' => $item->detalle
                ];
            });

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'eliminar' => ModuloService::validaPermiso($this->modulo,'eliminar'),
                'editar' => ModuloService::validaPermiso($this->modulo,'editar')
            ]
        ]);
    }

    public function delete(){

    header('Content-Type: application/json');

    try{

        $data = json_decode(file_get_contents('php://input'),true);
        $registro = DispensarioAperturaBitacora::find($data['id']);

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
            'message' => 'Registro eliminado correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar Registro'
        ]);
    }

    }

    public function catalogos()
    {

        $estacion = Estacion::find($this->estacionId());
        $dispensarios = Dispensario::query()

            ->where('id_estacion',$this->estacionId())
            ->where('estado',1)
            ->orderBy('no_dispensario')
            ->get(['id','no_dispensario']);

        echo json_encode([
            'dispensarios' => $dispensarios,
            'productos' => [
                $estacion['producto_uno'],
                $estacion['producto_dos'],
                $estacion['producto_tres']
            ]
        ]);
    }

    public function create()
    {
    header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    $categoria = sanitize_input(
        $data['motivo'] ?? null,
        'int'
    );

    $fecha = sanitize_input(
        $data['fecha'] ?? null,
        'string'
    );

    $horaInicio = sanitize_input(
        $data['hora_inicio'] ?? null,
        'string'
    );

    $horaTermino = sanitize_input(
        $data['hora_termino'] ?? null,
        'string'
    );

    $idDispensario = sanitize_input(
        $data['id_dispensario'] ?? null,
        'int'
    );

    $lado = sanitize_input(
        $data['lado'] ?? null,
        'int'
    );

    $producto = sanitize_input(
        $data['producto'] ?? null,
        'string'
    );

    $detalle = sanitize_input(
        $data['detalle'] ?? null,
        'string'
    );

    if (
        !ModuloService::validaPermiso(
            $this->modulo,
            'crear'
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso para crear'
        ]);

        return;
    }

    try {

        if ($categoria == 2) {

            $this->crearCambioPrecio(
                $producto,
                $fecha,
                $horaInicio,
                $horaTermino,
                $detalle
            );

        } else {

            $motivos = [

                1 => [
                    'clave' => 'CALI',
                    'motivo' => 'Ajuste'
                ],

                3 => [
                    'clave' => 'APPU',
                    'motivo' => 'Apertura en puerta'
                ],

                4 => [
                    'clave' => 'ACMO',
                    'motivo' => 'Acceso al modo de programacion'
                ],

                5 => [
                    'clave' => 'CAMF',
                    'motivo' => 'Cambio de fecha y hora'
                ],

                6 => [
                    'clave' => 'ACTU',
                    'motivo' => 'Actualizacion del o los programas de computo'
                ],

                7 => [
                    'clave' => 'MAGRL',
                    'motivo' => 'Mantenimiento General'
                ]
            ];

            if (!isset($motivos[$categoria])) {

                throw new \Exception(
                    'Categoría inválida'
                );
            }

            DispensarioAperturaBitacora::create([

                'id_dispensario' => $idDispensario,

                'fecha' => $fecha,

                'hora_inicio' => $horaInicio,

                'hora_termino' => $horaTermino ?: '',

                'lado' => $lado,

                'producto' => $producto,

                'clave' =>
                    $motivos[$categoria]['clave'],

                'motivo' =>
                    $motivos[$categoria]['motivo'],

                'responsable' =>
                    $this->userId(),

                'detalle' => $detalle ?: ''
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Registro creado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

private function crearCambioPrecio(
    string $producto,
    string $fecha,
    string $horaInicio,
    string $horaTermino,
    string $detalle
): void {

    $dispensarios = Dispensario::query()

        ->where(
            'id_estacion',
            $this->estacionId()
        )

        ->where(
            'estado',
            1
        )

        ->orderBy(
            'no_dispensario'
        )

        ->get();

    foreach ($dispensarios as $dispensario) {

        $mangueras = 0;

        if (
            in_array(
                $producto,
                ['G SUPER', 'MAGNA']
            )
        ) {

            $mangueras =
                (int) $dispensario->producto1;

        } elseif (
            in_array(
                $producto,
                ['G PREMIUM', 'PREMIUM']
            )
        ) {

            $mangueras =
                (int) $dispensario->producto2;

        } elseif (
            in_array(
                $producto,
                ['G DIESEL', 'DIESEL']
            )
        ) {

            $mangueras =
                (int) $dispensario->producto3;
        }

        $this->guardarRegistroCambioPrecio(
            $dispensario->id,
            $mangueras,
            $producto,
            $fecha,
            $horaInicio,
            $horaTermino,
            $detalle
        );
    }
}

private function guardarRegistroCambioPrecio(
    int $idDispensario,
    int $mangueras,
    string $producto,
    string $fecha,
    string $horaInicio,
    string $horaTermino,
    string $detalle
): void {

    if ($mangueras <= 0) {
        return;
    }

    for (
        $lado = 1;
        $lado <= $mangueras;
        $lado++
    ) {

        DispensarioAperturaBitacora::create([

            'id_dispensario' => $idDispensario,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_termino' => $horaTermino,
            'lado' => $lado,
            'producto' => $producto,
            'clave' => 'CAMP',
            'motivo' => 'Cambio de precio',
            'responsable' =>
                $this->userId(),

            'detalle' => $detalle
        ]);
    }
}

    public function excel()
{

    $year = sanitize_input($_GET['year'] ?? null,'int');
    $mes = sanitize_input($_GET['mes'] ?? null,'int');
    $estacion = Estacion::find(
        $this->estacionId()
    );

    if (!$estacion) {
        exit('No se encontró información');
    }

    header('Content-Encoding: UTF-8');
    header('Content-Type:text/csv; charset=UTF-8');
    header(
        'Content-Disposition: attachment; filename="Bitacora-dispensarios.csv"'
    );

    $salida = fopen('php://output', 'w');

    $registros = DispensarioAperturaBitacora::query()

        ->with([
            'dispensario',
            'usuario'
        ])

        ->when($year, function ($q) use ($year) {
                $q->whereYear('fecha',$year);
            })
            ->when($mes, function ($q) use ($mes) {
                $q->whereMonth('fecha',$mes);
            })

        ->whereHas(
            'dispensario',
            fn($q) => $q->where(
                'id_estacion',
                $this->estacionId()
            )
        )

        ->orderByDesc('fecha')
        ->orderByDesc('hora_inicio')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Encabezado estación
    |--------------------------------------------------------------------------
    */

    fputcsv(
        $salida,
        $this->csvEncoding([
            'Razón social:',
            $estacion->razonsocial
        ])
    );

    fputcsv(
        $salida,
        $this->csvEncoding([
            'Permiso CRE:',
            $estacion->permisocre
        ])
    );

    fputcsv(
        $salida,
        $this->csvEncoding([
            'Dirección:',
            $estacion->direccioncompleta
        ])
    );

    fputcsv($salida, []);

    /*
    |--------------------------------------------------------------------------
    | Encabezados
    |--------------------------------------------------------------------------
    */

    fputcsv(
        $salida,
        $this->csvEncoding([
            'Fecha',
            'Hora inicio',
            'Hora término',
            'Dispensario',
            'Marca',
            'Modelo',
            'Serie',
            'Lado',
            'Producto',
            'Motivo',
            'Responsable',
            'Detalle'
        ])
    );

    /*
    |--------------------------------------------------------------------------
    | Registros
    |--------------------------------------------------------------------------
    */

    foreach ($registros as $item) {

        $horaTermino = '';

        if (
            empty($item->hora_termino) ||
            $item->hora_termino === '00:00:00'
        ) {

            $horaTermino = 'S/I';

        } else {

            try {

                $horaTermino = Carbon::parse(
                    $item->hora_termino
                )->format('h:i a');

            } catch (\Throwable $e) {

                $horaTermino = $item->hora_termino;
            }
        }

        fputcsv(
            $salida,
            $this->csvEncoding([
                formatearFecha(
                    $item->fecha
                ),

                Carbon::parse(
                    $item->hora_inicio
                )->format('h:i a'),

                $horaTermino,
                $item->dispensario?->no_dispensario,
                $item->dispensario?->marca,
                $item->dispensario?->modelo,
                $item->dispensario?->serie,
                $item->lado,
                $item->producto,
                $item->clave .' (' .$item->motivo .')',
                $item->usuario?->nombre,
                $item->detalle
            ])
        );
    }

    fclose($salida);
    exit;
}

private function csvEncoding(
    array $row
): array {

    return array_map(
        static fn($campo) =>
            mb_convert_encoding(
                (string) $campo,
                'ISO-8859-1',
                'UTF-8'
            ),
        $row
    );
}

}