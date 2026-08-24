<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\MantenimientoLista;
use App\Models\Sasisopa\ProgramaAnualMantenimiento;
use App\Models\Sasisopa\ProgramaAnualMantenimientoDetalle;
use App\Services\ProgramaMantenimientoService;
use App\Models\Sasisopa\ProgramaAnualMantenimientoCalendario;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dompdf\Dompdf;
use Dompdf\Options;

class ControlActividadesProcesosController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

     $title = '10. CONTROL DE ACTIVIDADES Y PROCESOS';

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
                
            ],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('controlactividadproceso/index', $data,'sasisopa');
        
    }

    public function programaAnual(){

     $title = 'Programa Anual de Mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS', '/sasisopa/control-actividades-procesos');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $programas = ProgramaAnualMantenimiento::where(
            'id_estacion',
            $this->estacionId()
        )
        ->select([
            'id',
            'year',
            'estado'
        ])
        ->orderByDesc('year')
        ->get();

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'programas'=> $programas,
             'links' =>[
              
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/controlactividadproceso/programamantenimiento.action.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('controlactividadproceso/programa-anual-mantenimiento', $data,'sasisopa');

    }

    public function createProgramaAnualMantenimiento()
    {
        header('Content-Type: application/json');

        try {

            if (
                !ModuloService::validaPermiso(
                    $this->modulo,
                    'crear'
                )
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso'
                ]);

                return;
            }

            Capsule::beginTransaction();

            $idEstacion = $this->estacionId();

            $year = date('Y');

            /*
            |--------------------------------------------------------------------------
            | Buscar programa
            |--------------------------------------------------------------------------
            */

            $programa = ProgramaAnualMantenimiento::where(
                'id_estacion',
                $idEstacion
            )
            ->where(
                'year',
                $year
            )
            ->first();

            /*
            |--------------------------------------------------------------------------
            | Crear programa
            |--------------------------------------------------------------------------
            */

            if (!$programa) {

                $programa = ProgramaAnualMantenimiento::create([

                    'id_estacion' => $idEstacion,
                    'year' => $year,
                    'estado' => 0
                ]);

                /*
                |--------------------------------------------------------------------------
                | Copiar información del año anterior
                |--------------------------------------------------------------------------
                */

                $this->copiarProgramaAnterior(
                    $idEstacion,
                    $programa->id,
                    (int)$year
                );
            }

            Capsule::commit();

            echo json_encode([

                'success' => true,

                'message' =>
                    'Programa generado correctamente',

                'data' => [
                    'id' => $programa->id
                ]
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function copiarProgramaAnterior(
    int $idEstacion,
    int $idProgramaNuevo,
    int $yearActual
    ): void {

        $programaAnterior = ProgramaAnualMantenimiento::where(
            'id_estacion',$idEstacion
        )
        ->where('year',$yearActual - 1
        )
        ->first();

        if (!$programaAnterior) {
            return;
        }

        $detalles = ProgramaAnualMantenimientoDetalle::with(
            'mantenimiento'
        )
        ->where('id_programa_fecha',$programaAnterior->id
        )
        ->get();

        foreach ($detalles as $detalleAnterior) {

            $existe = ProgramaAnualMantenimientoDetalle::where(
                'id_programa_fecha', $idProgramaNuevo
            )
            ->where('id_mantenimiento',$detalleAnterior->id_mantenimiento
            )
            ->exists();

            if ($existe) {
                continue;
            }

            $periodicidad = strtolower(
                trim(
                    $detalleAnterior
                    ->mantenimiento
                    ?->periodicidad ?? ''
                )
            );

            $meses = $this->generarMesesPrograma(
                $detalleAnterior,
                $periodicidad,
                $yearActual
            );

                ProgramaAnualMantenimientoDetalle::create([
                    'id_programa_fecha' => $idProgramaNuevo,
                    'id_mantenimiento' =>$detalleAnterior->id_mantenimiento,
                    'ultimafecha' => (
                        normalizarFecha($detalleAnterior->ultimafecha) ?: '0000-00-00'
                    ),

                    'enero' => $meses['enero'],
                    'febrero' => $meses['febrero'],
                    'marzo' => $meses['marzo'],
                    'abril' => $meses['abril'],
                    'mayo' => $meses['mayo'],
                    'junio' => $meses['junio'],
                    'julio' => $meses['julio'],
                    'agosto' => $meses['agosto'],
                    'septiembre' => $meses['septiembre'],
                    'octubre' => $meses['octubre'],
                    'noviembre' => $meses['noviembre'],
                    'diciembre' => $meses['diciembre'],
                    'estado' => 1
                ]);


            if ($periodicidad === 'semanal') {

                $this->copiarSemanalAnterior(
                    $idEstacion,
                    $detalleAnterior->id_mantenimiento,
                    $yearActual - 1
                );
            }
        }
    }

    private function generarMesesPrograma(
    ProgramaAnualMantenimientoDetalle $detalle,
    string $periodicidad,
    int $yearActual
    ): array {

        $meses = [
            'enero',
            'febrero',
            'marzo',
            'abril',
            'mayo',
            'junio',
            'julio',
            'agosto',
            'septiembre',
            'octubre',
            'noviembre',
            'diciembre'
        ];

        $data = [];

        foreach ($meses as $mes) {

            $fechaOriginal = $detalle->$mes;

            if (empty($fechaOriginal) || $fechaOriginal == '0000-00-00') {
                $data[$mes] = '0000-00-00';
                continue;
            }

            $fecha = normalizarFecha($fechaOriginal);

            if (!$fecha) {
                $data[$mes] = '0000-00-00';
                continue;
            }

            try {
                $fechaCarbon = Carbon::parse($fecha);
            } catch (\Throwable $e) {
                $data[$mes] = '0000-00-00';
                continue;
            }

            if ($periodicidad === 'bianual') {
                if ($fechaCarbon->year >= $yearActual) {
                    $data[$mes] = $fechaCarbon->format('Y-m-d');
                } else {
                    $data[$mes] =
                        $fechaCarbon
                        ->copy()
                        ->addYears(2)
                        ->format('Y-m-d');
                }

                continue;
            }


            $data[$mes] =
                $fechaCarbon
                ->copy()
                ->addYear()
                ->format('Y-m-d');
        }

        return $data;
    }

    private function copiarSemanalAnterior(
        int $idEstacion,
        int $idMantenimiento,
        int $yearAnterior
    ): void {

        $fecha = ProgramaAnualMantenimientoCalendario::where('id_estacion',$idEstacion
        )
        ->where('id_mantenimiento',$idMantenimiento
        )
        ->whereYear('fecha',$yearAnterior
        )
        ->orderByDesc('fecha')
        ->value('fecha');

        if (!$fecha) {
            return;
        }

        for ($i = 1; $i <= 53; $i++) {

            $nuevaFecha = Carbon::parse($fecha)
                ->addWeeks($i)
                ->format('Y-m-d');

            $existe = ProgramaAnualMantenimientoCalendario
            ::where('id_estacion',$idEstacion
            )
            ->where('id_mantenimiento',$idMantenimiento
            )
            ->where('fecha',$nuevaFecha
            )
            ->exists();

            if (!$existe) {

                ProgramaAnualMantenimientoCalendario
                ::create([
                    'id_estacion' =>$idEstacion,
                    'id_mantenimiento' => $idMantenimiento,
                    'fecha' => $nuevaFecha
                ]);
            }
        }
    }

    //---------------------------------------------------------
    //---------------------------------------------------------

    public function detalleProgramaAnual(int $id){

    $title = 'Detalle Programa Anual de Mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS', '/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Programa Anual de Mantenimiento', '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idPrograma' => (int)$id,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/programaanualmantenimiento.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/programaanualmantenimiento.action.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('controlactividadproceso/detalle-programa-anual-mantenimiento', $data,'sasisopa');

    }

    public function datatableProgramaMantenimiento(int $id)
    {

        $programa = ProgramaAnualMantenimiento::find($id);

        if (!$programa) {
            echo json_encode([
                'data' => []
            ]);
            return;
        }

        $year = $programa->year;

        $detalles = ProgramaAnualMantenimientoDetalle::with('mantenimiento')
        ->where('id_programa_fecha',$id)
        ->get();

        $data = [];

        foreach ($detalles as $item) {

            $row = [
                'id' => $item->id,
                'id_mantenimiento' => $item->id_mantenimiento,
                'equipo' => $item->mantenimiento?->detalle,
                'periodicidad' => $item->mantenimiento?->periodicidad,
            ];

            $meses = [
                'enero',
                'febrero',
                'marzo',
                'abril',
                'mayo',
                'junio',
                'julio',
                'agosto',
                'septiembre',
                'octubre',
                'noviembre',
                'diciembre'
            ];

            foreach ($meses as $index => $mes) {

                $fecha = $item->$mes;

                if ($item->mantenimiento?->periodicidad == 'Semanal') {
                    $fecha =
                        ProgramaMantenimientoService::buscaFechaSemanal(
                            $this->estacionId(),
                            $item->id_mantenimiento,
                            $year,
                            $index + 1
                        );
                }

                $row[$mes] = [
                    'fecha' => $fecha,
                    'texto' => ProgramaMantenimientoService::txtFecha($fecha),
                    'background' => ProgramaMantenimientoService::colorTD($fecha),
                    'textColor' => ProgramaMantenimientoService::txtColor($fecha)
                ];
            }

            $data[] = $row;
        }

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'editar' =>  ModuloService::validaPermiso($this->modulo, 'editar'),
                'eliminar' =>  ModuloService::validaPermiso($this->modulo, 'eliminar')
            ]
        ]);
    }

    public function equipoProgramaMantenimiento(int $id)
    {
        header('Content-Type: application/json');

        $equipos = MantenimientoLista::where(
            'estado',
            0
        )
        ->get()
        ->filter(function($item) use ($id){

            return !ProgramaAnualMantenimientoDetalle
                ::where(
                    'id_programa_fecha',
                    $id
                )
                ->where(
                    'id_mantenimiento',
                    $item->id
                )
                ->exists();
        })
        ->values();

        echo json_encode([
            'success' => true,
            'data' => $equipos
        ]);
    }

    public function createProgramaMantenimiento()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'),true);

            if (!ModuloService::validaPermiso($this->modulo,'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso'
                ]);
                return;
            }

            $idPrograma = (int)($data['id_programa'] ?? 0);
            $idMantenimiento = (int)($data['id_mantenimiento'] ?? 0);
            $ultimaFecha = trim($data['ultimafecha'] ?? '');
            $select = trim($data['periodicidad'] ?? '');

            if (empty($idPrograma) || empty($idMantenimiento) || empty($ultimaFecha)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Completa los campos'
                ]);

                return;
            }

            $existente = ProgramaAnualMantenimientoDetalle::where('id_programa_fecha', $idPrograma)
            ->where('id_mantenimiento',$idMantenimiento)
            ->exists();

            if ($existente) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El equipo ya existe'
                ]);
                return;
            }

            Capsule::beginTransaction();

            $mantenimiento = MantenimientoLista::find($idMantenimiento);

            if (!$mantenimiento) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ]);
                return;
            }

            $periodicidad = $idMantenimiento != 43 ? $mantenimiento->periodicidad : $select;
            $meses = [];
            $nombresMeses = [
                1  => 'enero',
                2  => 'febrero',
                3  => 'marzo',
                4  => 'abril',
                5  => 'mayo',
                6  => 'junio',
                7  => 'julio',
                8  => 'agosto',
                9  => 'septiembre',
                10 => 'octubre',
                11 => 'noviembre',
                12 => 'diciembre'
            ];

            foreach ($nombresMeses as $numero => $nombre) {
                $meses[$nombre] = ProgramaMantenimientoService::generarMes($periodicidad,$ultimaFecha,$numero);
            }

            $detalle = ProgramaAnualMantenimientoDetalle::create([
                'id_programa_fecha' => $idPrograma,
                'id_mantenimiento' => $idMantenimiento,
                'ultimafecha' => $ultimaFecha,
                'enero' => $meses['enero'],
                'febrero' => $meses['febrero'],
                'marzo' => $meses['marzo'],
                'abril' => $meses['abril'],
                'mayo' => $meses['mayo'],
                'junio' => $meses['junio'],
                'julio' => $meses['julio'],
                'agosto' => $meses['agosto'],
                'septiembre' => $meses['septiembre'],
                'octubre' => $meses['octubre'],
                'noviembre' => $meses['noviembre'],
                'diciembre' => $meses['diciembre'],
                'estado' => 1
            ]);

            if (strtolower($periodicidad) == 'semanal') {

                for ($i = 1; $i <= 53; $i++) {

                    $fecha = Carbon::parse($ultimaFecha)
                        ->addWeeks($i)
                        ->format('Y-m-d');

                    $existe = ProgramaAnualMantenimientoCalendario
                    ::where('id_estacion',$this->estacionId()
                    )
                    ->where('id_mantenimiento',$idMantenimiento
                    )
                    ->where('fecha',$fecha
                    )
                    ->exists();

                    if (!$existe) {

                        ProgramaAnualMantenimientoCalendario
                        ::create([
                            'id_estacion' => $this->estacionId(),
                            'id_mantenimiento' => $idMantenimiento,
                            'fecha' => $fecha
                        ]);
                    }
                }
            }

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro creado correctamente',
                'id' => $detalle->id
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar'
            ]);
        }
    }

    public function deleteProgramaMantenimiento()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'),true);
            
            if (!ModuloService::validaPermiso($this->modulo,'eliminar')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar'
                ]);
                return;
            }

            $id = (int)($data['id'] ?? 0);

            if (empty($id)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);

                return;
            }

            $detalle = ProgramaAnualMantenimientoDetalle::find($id);

            if (!$detalle) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }
          
            $detalle->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el registro'
            ]);
        }
    }

    public function getProgramaMantenimiento(int $id)
    {
        header('Content-Type: application/json');

        try {

            $detalle = ProgramaAnualMantenimientoDetalle::with(
                'mantenimiento'
            )->find($id);

            if (!$detalle) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $meses = [
                'enero',
                'febrero',
                'marzo',
                'abril',
                'mayo',
                'junio',
                'julio',
                'agosto',
                'septiembre',
                'octubre',
                'noviembre',
                'diciembre'
            ];

            $dataMeses = [];

            foreach ($meses as $mes) {

                $fecha = $detalle->$mes;

                if (empty($fecha)) {

                    $dataMeses[$mes] = [
                        'value' => '',
                        'min' => '',
                        'max' => '',
                        'disabled' => false
                    ];

                    continue;
                }

                $fechaCarbon = Carbon::parse($fecha);
                $disabled = $fechaCarbon->lt(Carbon::today());

                $inicioMes = $fechaCarbon
                    ->copy()
                    ->startOfMonth()
                    ->format('Y-m-d');

                $finMes = $fechaCarbon
                    ->copy()
                    ->endOfMonth()
                    ->format('Y-m-d');

                $dataMeses[$mes] = [

                    'value' => $fechaCarbon->format('Y-m-d'),
                    'min' => $inicioMes,
                    'max' => $finMes,
                    'disabled' => $disabled
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $detalle->id,
                    'detalle' => $detalle->mantenimiento?->detalle,
                    'meses' => $dataMeses
                ]
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener información'
            ]);
        }
    }

    public function updateProgramaMantenimiento()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (
                !ModuloService::validaPermiso(
                    $this->modulo,
                    'editar'
                )
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso'
                ]);

                return;
            }

            $detalle = ProgramaAnualMantenimientoDetalle::find(
                (int)($data['id'] ?? 0)
            );

            if (!$detalle) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $meses = [
                'enero',
                'febrero',
                'marzo',
                'abril',
                'mayo',
                'junio',
                'julio',
                'agosto',
                'septiembre',
                'octubre',
                'noviembre',
                'diciembre'
            ];

            $updateData = [];

            foreach ($meses as $mes) {

                $valor =
                    $data['meses'][$mes] ?? null;

                $updateData[$mes] =
                    !empty($valor)
                    ? $valor
                    : null;
            }

            $detalle->update(
                $updateData
            );

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }
    }

    public function pdfProgramaMantenimiento(int $id){

    $estacion = Estacion::find($this->estacionId());
    $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $programa = ProgramaAnualMantenimiento::find($id);
    $year = $programa->year;

    $detalles = ProgramaAnualMantenimientoDetalle::with(
        'mantenimiento'
    )
    ->where(
        'id_programa_fecha',
        $id
    )
    ->orderBy('id_mantenimiento')
    ->get();

        $meses = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre'
        ];

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Programa Anual de Mantenimiento</title>
            <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">

            <style>

            body{
                font-size:10px;
            }

            </style>
        </head>
        <body>

        <table class="table table-bordered table-sm fs-6">
            <tr>
                <td class="text-center align-middle">
                    <img src="'.$logo.'" style="width:120px;">
                </td>
                <td class="text-center align-middle" colspan="2">
                    <b>Programa Anual de Mantenimiento</b>
                </td>
                <td class="text-center align-middle">
                    <b>Fo.ADMONGAS.011</b>
                </td>
            </tr>
            <tr>
                <td class="text-center">Realizado por:<br> Nelly Estrada Garcia</td>
                <td class="text-center">Revisado por:<br> Eduardo Galicia Flores</td>
                <td class="text-center">Autorizado por:<br> '.$apoderado.'</td>
                <td class="text-center">Fecha de aprobación:<br> 01/10/2018</td>
            </tr>
        </table>

            <table class="table table-bordered table-sm">

            <thead>

                <tr>

                    <th class="text-center align-middle"
                    width="200">
                        Equipo o instalación
                    </th>

                    <th class="text-center align-middle"
                    width="50">
                        Periodicidad
                    </th>

                    <th class="text-center">Ene</th>
                    <th class="text-center">Feb</th>
                    <th class="text-center">Mar</th>
                    <th class="text-center">Abr</th>
                    <th class="text-center">May</th>
                    <th class="text-center">Jun</th>
                    <th class="text-center">Jul</th>
                    <th class="text-center">Ago</th>
                    <th class="text-center">Sep</th>
                    <th class="text-center">Oct</th>
                    <th class="text-center">Nov</th>
                    <th class="text-center">Dic</th>

                </tr>

            </thead>

            <tbody>
        ';

        foreach ($detalles as $detalle) {

            $mantenimiento =
                $detalle->mantenimiento;

            $periodicidad =
                $mantenimiento?->periodicidad ?? '';

            /*
            |--------------------------------------------------------------------------
            | Obtener fechas
            |--------------------------------------------------------------------------
            */

            $fechas = [];

            foreach ($meses as $index => $mes) {

                $numeroMes = $index + 1;

                /*
                |--------------------------------------------------------------------------
                | Semanal
                |--------------------------------------------------------------------------
                */

                if (
                    strtolower($periodicidad)
                    === 'semanal'
                ) {

                    $fecha =
                        ProgramaMantenimientoService
                        ::buscaFechaSemanal(
                            $this->estacionId(),
                            $mantenimiento->id,
                            $year,
                            $numeroMes
                        );

                } else {

                    $fecha = $detalle->$mes;
                }

                $fechas[$mes] =
                    ProgramaMantenimientoService
                    ::txtFecha($fecha);
            }

            $html .= '
            <tr>

                <td class="align-middle">

                    '.htmlspecialchars(
                        $mantenimiento?->detalle ?? ''
                    ).'

                </td>

                <td class="align-middle text-center">

                    '.htmlspecialchars(
                        $periodicidad
                    ).'

                </td>

                <td class="text-center">
                    '.$fechas['enero'].'
                </td>

                <td class="text-center">
                    '.$fechas['febrero'].'
                </td>

                <td class="text-center">
                    '.$fechas['marzo'].'
                </td>

                <td class="text-center">
                    '.$fechas['abril'].'
                </td>

                <td class="text-center">
                    '.$fechas['mayo'].'
                </td>

                <td class="text-center">
                    '.$fechas['junio'].'
                </td>

                <td class="text-center">
                    '.$fechas['julio'].'
                </td>

                <td class="text-center">
                    '.$fechas['agosto'].'
                </td>

                <td class="text-center">
                    '.$fechas['septiembre'].'
                </td>

                <td class="text-center">
                    '.$fechas['octubre'].'
                </td>

                <td class="text-center">
                    '.$fechas['noviembre'].'
                </td>

                <td class="text-center">
                    '.$fechas['diciembre'].'
                </td>

            </tr>';
        }

        $html .= '
            </tbody>
        </table>

        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Programa-Anual-de-Mantenimiento.pdf", ["Attachment" => true]);

    }

}