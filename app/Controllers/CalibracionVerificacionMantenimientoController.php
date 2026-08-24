<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\TanqueAlmacenamiento;
use App\Models\Sasisopa\SondasMedicion;
use App\Models\Sasisopa\Dispensario;
use App\Models\Sasisopa\JarraPatron;
use App\Models\Sgm\CalibracionEquipo;

use Dompdf\Dompdf;
use Dompdf\Options;

class CalibracionVerificacionMantenimientoController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

    $title = 'CALIBRACIÓN, VERIFICACIÓN Y MANTENIMIENTO DE EQUIPOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN', '/sasisopa/monitoreo-verificacion-evaluacion');
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
                '/js/vendor.min.js',
                '/js/monitoreoverificacionevaluacion/calibracionverificacionmantenimiento.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('monitoreoverificacionevaluacion/calibracionverificacionmantenimiento', $data,'sasisopa');
        
    }

    public function getEquiposCalibracion(): void
    {
        header('Content-Type: application/json');

        try {

            $equipos = $this->getEquipos($this->estacionId());
            echo json_encode([
                'success' => true,
                'data' => $equipos
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public static function getEquipos(
    int $estacionId
    ): array {

        $equipos = [];

        // Tanques
        $tanques = TanqueAlmacenamiento::where(
            'id_estacion',
            $estacionId
        )->get();

        foreach ($tanques as $tanque) {

            $equipos[] = [
                'identificacion' => $tanque->no_tanque,
                'nombre' => $tanque->capacidad . ', ' . $tanque->producto,
                'descripcion' => 'Tanque de almacenamiento',
                'frecuencia' => '10 años'
            ];
        }

        // Sondas
        $sondas = SondasMedicion::where(
            'id_estacion',
            $estacionId
        )->get();

        foreach ($sondas as $sonda) {

            $equipos[] = [
                'identificacion' => $sonda->no_sonda,
                'nombre' => $sonda->marca . ', ' . $sonda->modelo,
                'descripcion' => 'Sonda de medición',
                'frecuencia' => '2 años'
            ];
        }

        // Dispensarios
        $dispensarios = Dispensario::where(
            'id_estacion',
            $estacionId
        )->get();

        foreach ($dispensarios as $dispensario) {

            $equipos[] = [
                'identificacion' => $dispensario->no_dispensario,
                'nombre' => $dispensario->marca . ', ' . $dispensario->modelo,
                'descripcion' => 'Dispensario',
                'frecuencia' => 'Semestral'
            ];
        }

        // Jarras patrón
        $jarras = JarraPatron::where(
            'id_estacion',
            $estacionId
        )->get();

        foreach ($jarras as $index => $jarra) {

            $equipos[] = [
                'identificacion' => $index + 1,
                'nombre' => $jarra->marca . ', ' . $jarra->no_serie,
                'descripcion' => 'Jarra patrón',
                'frecuencia' => 'Anual'
            ];
        }

        return $equipos;
    }
    public function pdfEquiposCalibracion() {

    $estacion = Estacion::find($this->estacionId());
    $equipos = self::getEquipos($this->estacionId());

    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html = <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">

    <style>

    @page {
        margin: 0.5cm 1cm;
        font-family: Arial, Helvetica, sans-serif;
    }

    body{
        font-family: Arial, Helvetica, sans-serif;
        font-size:15px;
        color:#212529;
    }

    table{
        width:100%;
        border-collapse:collapse;
    }

    .table{
        margin-bottom:10px;
    }

    .table th,
    .table td{
        border:1px solid #dee2e6;
        padding:4px;
    }

    .table-bordered{
        border:1px solid #dee2e6;
    }

    .text-center{
        text-align:center;
    }

    .align-middle{
        vertical-align:middle;
    }

    .bg-light{
        background:#f8f9fa;
    }

    </style>

    </head>

    <body>

    <table class="table table-bordered">

        <tr>
            <td class="text-center align-middle" width="30%">
                <img src="{$logo}" style="width:150px;">
            </td>

            <td colspan="2" class="text-center align-middle">
                <strong>Equipos sometidos a calibración</strong>
            </td>

            <td class="text-center align-middle">
                Fo.ADMONGAS.019
            </td>
        </tr>

        <tr>

            <td class="text-center align-middle">
                Realizado por: Nelly Estrada Garcia
            </td>

            <td class="text-center align-middle">
                Revisado por: Eduardo Galicia Flores
            </td>

            <td class="text-center align-middle">
                Autorizado por: {$estacion->apoderado_legal}
            </td>

            <td class="text-center align-middle">
                Fecha de autorización 01/10/2018
            </td>

        </tr>

    </table>

    <table class="table table-bordered">

        <thead>

            <tr>

                <th class="text-center align-middle">
                    Número de identificación
                </th>

                <th class="text-center align-middle">
                    Nombre del equipo (marca y modelo)
                </th>

                <th class="text-center align-middle">
                    Descripción del equipo
                </th>

                <th class="text-center align-middle">
                    Frecuencia de la calibración
                </th>

            </tr>

        </thead>

        <tbody>
    HTML;

        foreach ($equipos as $equipo) {

            $html .= <<<HTML
    <tr>

        <td class="text-center align-middle">
            {$equipo['identificacion']}
        </td>

        <td class="text-center align-middle">
            {$equipo['nombre']}
        </td>

        <td class="text-center align-middle">
            {$equipo['descripcion']}
        </td>

        <td class="text-center align-middle">
            {$equipo['frecuencia']}
        </td>

    </tr>
    HTML;
        }

        $html .= <<<HTML
        </tbody>

    </table>

    </body>
    </html>
    HTML;

        $options = new Options();
        $options->set('isRemoteEnabled',true);
        $options->set('defaultFont','Arial');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','portrait');

        $dompdf->render();

        $dompdf->stream(
            'Equipos sometidos a calibracion.pdf',
            [
                'Attachment' => true
            ]
        );
    }

    public function getCalendarioCalibracion(): void
    {
        header('Content-Type: application/json');

        try {

            $data = self::calendarioCalibracion($this->estacionId());

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

public static function calendarioCalibracion(
    int $estacionId
): array {

    $equipos = CalibracionEquipo::query()
        ->where('id_estacion', $estacionId)
        ->where('categoria', 1)
        ->orderByDesc('fecha')
        ->get();

    $data = [];

    foreach ($equipos as $index => $equipo) {

        $year = (int) $equipo->fecha->year;
        $mes  = (int) $equipo->fecha->month;

        $frecuencia = match ($equipo->equipo) {

            'Dispensario' => 'Semestral',
            'Jarra patron' => 'Anual',
            'Tanques' => '10 años',
            'Tanques de almacenamiento' => '10 años',
            'Sondas de medición' => '2 años',
            default => 'N/D'
        };

        $meses = [];

        for ($i = 1; $i <= 12; $i++) {

            $meses[] = [
                'year' => $mes === $i
                    ? $year
                    : '',

                'color' => $mes === $i
                    ? (
                        $equipo->estado == 1
                            ? 'table-success'
                            : 'table-warning'
                    )
                    : ''
            ];
        }

        $data[] = [

            'numero' => $index + 1,
            'equipo' => $equipo->equipo,
            'frecuencia' => $frecuencia,
            'meses' => $meses
        ];
    }

    return $data;
}

public static function calendarioCalibracionPdf(
    int $estacionId,
    ?int $year = null
): array {

    $equiposFisicos = self::getEquipos($estacionId);

    $calibraciones = CalibracionEquipo::query()
        ->where('id_estacion', $estacionId)
        ->where('categoria', 1)

        ->when(
            $year,
            fn ($q) => $q->whereYear('fecha', $year)
        )

        ->orderByDesc('fecha')
        ->get();

    $resultado = [];

    foreach ($calibraciones as $calibracion) {

        $anio = $calibracion->fecha->year;
        $mes  = $calibracion->fecha->month;

        foreach ($equiposFisicos as $equipo) {

            $aplica = match ($calibracion->equipo) {

                'Dispensario'
                    => $equipo['descripcion'] === 'Dispensario',

                'Jarra patron'
                    => $equipo['descripcion'] === 'Jarra patrón',

                'Sondas de medición'
                    => $equipo['descripcion'] === 'Sonda de medición',

                'Tanques de almacenamiento'
                    => $equipo['descripcion'] === 'Tanque de almacenamiento',

                default => false
            };

            if (!$aplica) {
                continue;
            }

            $meses = [];

            for ($i = 1; $i <= 12; $i++) {

                $meses[] = [

                    'year' => $mes == $i
                        ? $anio
                        : '',

                    'color' => $mes == $i
                        ? (
                            $calibracion->estado == 1
                                ? 'table-success'
                                : 'table-warning'
                        )
                        : ''

                ];
            }

            $resultado[] = [

                'identificacion' => $equipo['identificacion'],

                'nombre' => $equipo['descripcion']
                    .' ('
                    .$equipo['nombre']
                    .')',

                'frecuencia' => $equipo['frecuencia'],

                'meses' => $meses

            ];
        }
    }

    return $resultado;
}

public function pdfCalendarioCalibracion(): void
{

$year = isset($_GET['year'])
    ? (int) $_GET['year']
    : null;

    $calendario = self::calendarioCalibracionPdf(
    $this->estacionId(),
    $year
    );
    $estacion = Estacion::find($this->estacionId());
    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Calendario de calibraciones</title>

        <style>

            @page {
                margin: 0.5cm;
                font-family: Arial, Helvetica, sans-serif;
            }

            body{
                font-family: Arial, Helvetica, sans-serif;
                font-size:15px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            .table th,
            .table td{
                border:1px solid #dee2e6;
                padding:4px;
            }

            .text-center{
                text-align:center;
            }

            .align-middle{
                vertical-align:middle;
            }

            .table-success{
                background:#c3e6cb;
            }

            .table-warning{
                background:#ffeeba;
            }

            .mb-2,
            .my-2 {
            margin-bottom: 0.5rem !important;
            }

        </style>
    </head>

    <body>

        <table class="table mb-2">

            <tr>
                <td class="text-center align-middle">
                    <img src="' . $logo . '" width="150">
                </td>

                <td colspan="2" class="text-center align-middle">
                    <strong>Calendario de calibraciones</strong>
                </td>

                <td class="text-center align-middle">
                    Fo.ADMONGAS.020
                </td>

            </tr>

            <tr>
                <td class="text-center align-middle">
                    Realizado por: Nelly Estrada Garcia
                </td>

                <td class="text-center align-middle">
                    Revisado por: Eduardo Galicia Flores
                </td>

                <td class="text-center align-middle">
                    Autorizado por: ' . $estacion->apoderado_legal . '
                </td>

                <td class="text-center align-middle">
                    Fecha de autorización 01/10/2018
                </td>

            </tr>

        </table>

        <table class="table mt-2 mb-2">

            <thead>

                <tr>

                    <th width="60">Número de identificación</th>
                    <th width="250">Nombre del equipo (marca y modelo)</th>
                    <th width="90">Frecuencia de la calibración</th>
                    <th>Ene</th>
                    <th>Feb</th>
                    <th>Mar</th>
                    <th>Abr</th>
                    <th>May</th>
                    <th>Jun</th>
                    <th>Jul</th>
                    <th>Ago</th>
                    <th>Sep</th>
                    <th>Oct</th>
                    <th>Nov</th>
                    <th>Dic</th>

                </tr>

            </thead>

            <tbody>';

foreach ($calendario as $equipo) {

    $html .= '<tr>';

    $html .= '<td class="text-center">'
        .$equipo['identificacion']
        .'</td>';

    $html .= '<td>'
        .$equipo['nombre']
        .'</td>';

    $html .= '<td class="text-center">'
        .$equipo['frecuencia']
        .'</td>';

    foreach ($equipo['meses'] as $mes) {

        $html .= '<td class="text-center '.$mes['color'].'">'
            .$mes['year']
            .'</td>';
    }

    $html .= '</tr>';
}

    $html .= '
            </tbody>

        </table>

    </body>

    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled',true);
    $options->set('defaultFont','Arial');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','landscape');
    $dompdf->render();

    $dompdf->stream(
        'Calendario de calibraciones.pdf',
        [
            'Attachment' => true
        ]
    );

    exit;
}
}