<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\MedicionIndicadores;
use App\Models\Sasisopa\Implementacionsa;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\ReporteCreProducto;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sasisopa\EncuentaEstacion;
use App\Models\Sasisopa\InvestigacionIncidenteAccidente;
use App\Models\Sasisopa\EncuentasEstacionClientePreguntas;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class MonitoreoVerificacionEvaluacionController extends BaseController{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(){

        $title = '14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $this->agregarIndicadores();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/monitoreoverificacionevaluacion/index.actions.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('monitoreoverificacionevaluacion/index', $data,'sasisopa');
    }

    public function agregarIndicadores(): void
    {
        $estacion = $this->estacionModulo();

        if (!$estacion) {
            return;
        }

        collect([
            1 => '60%',
            2 => '80%',
            3 => '60%',
            4 => 'Buena',
            5 => '60%'
        ])
        ->each(function ($meta, $objeto) use ($estacion) {

            MedicionIndicadores::firstOrCreate(
                [
                    'id_estacion' => $estacion,
                    'objeto'      => $objeto
                ],
                [
                    'meta' => $meta
                ]
            );

        });
    }

    public function buscarIndicadores(): void
    {

     $year = sanitize_input(
        $_GET['year'] ?? date('Y'),
        'int'
    );

       
    echo json_encode([
    'implementacion' => [
        'meta' => $this->meta(1),
        'resultado' => $this->resultadoImplementacion($year)
    ],

    'ventas' => [
        'meta' => $this->meta(2),
        'detalle' => $this->resultadoVentas($year)
    ],

    'capacitacion' => [
        'meta' => $this->meta(3),
        'semestre1' => $this->resultadoCapacitacion($year, 1),
        'semestre2' => $this->resultadoCapacitacion($year, 2)
    ],

    'satisfaccion' => [
        'meta' => $this->meta(4),
        'semestre1' => $this->resultadoSatisfaccion($year, 1),
        'semestre2' => $this->resultadoSatisfaccion($year, 2)
    ],

    'incidentes' => [
        'meta' => $this->meta(5),
        'semestre1' => $this->resultadoIncidentes($year,1),
        'semestre2' => $this->resultadoIncidentes($year,2)
    ]
]);

    
    }

    public function meta(int $objeto): ?string
    {
        return MedicionIndicadores::query()
            ->where('id_estacion', $this->estacionModulo())
            ->where('objeto', $objeto)
            ->latest('id')
            ->value('meta');
    }

    public function resultadoImplementacion(
    int $year
): string {

    $promedio = Implementacionsa::query()

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->whereYear(
            'fecha',
            $year
        )

        ->avg('puntos');

    if (!$promedio) {

        return '<b>S/I</b>';
    }

    $promedio = round(
        $promedio,
        2
    );

    return $promedio >= 60

        ? "<b class='text-success'>{$promedio}% Excelente</b>"

        : "<b class='text-warning'>{$promedio}% Regular</b>";
}

public function ventasMes(
    int $mes,
    int $year
): float {

    $reporte = ReporteCreMes::query()

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->where('mes', $mes)

        ->where('year', $year)

        ->first();

    if (!$reporte) {
        return 0;
    }

    return (float) $reporte
        ->productos()
        ->sum('volumen_venta');
}

public function resultadoVentas(int $year): array
{
    $meses = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic'
    ];

    $yearAnterior = $year - 1;
    $ultimoMes = $year == date('Y')
    ? (int) date('n')
    : 12;

    $ventas = [];

    // Dic año anterior
   $ventas[] = [
    'mes_anterior' => 'Dic',
    'year_anterior' => $yearAnterior,
    'valor_anterior' => $this->ventasMes(12, $yearAnterior),
    'mes_actual' => 'Ene',
    'year_actual' => $year,
    'valor_actual' => $this->ventasMes(1, $year),
    'tc' => $this->tc(
        $this->ventasMes(1, $year),
        $this->ventasMes(12, $yearAnterior)
    )
];

    for ($mes = 2; $mes <= $ultimoMes; $mes++) {

    $anterior = $this->ventasMes($mes - 1, $year);
    $actual   = $this->ventasMes($mes, $year);

    $ventas[] = [
        'mes_anterior'   => $meses[$mes - 1],
        'year_anterior'  => $year,
        'valor_anterior' => $anterior,
        'mes_actual'     => $meses[$mes],
        'year_actual'    => $year,
        'valor_actual'   => $actual,
        'tc'             => $this->tc($actual, $anterior)
    ];
}

    return $ventas;
}
private function tc(
    float $actual,
    float $anterior
): array {

    if ($actual == 0 || $anterior == 0) {

        return [
            'texto' => 'S/I',
            'clase' => 'text-warning'
        ];
    }

    $resul = (($actual - $anterior) / $anterior) * 100;
    $porcentaje = round(100 + $resul, 2);

    return [
        'texto' => $porcentaje . '% ' .
            ($porcentaje >= 80 ? 'Excelente' : 'Regular'),

        'clase' => $porcentaje >= 80
            ? 'text-success'
            : 'text-warning'
    ];
}

public function resultadoCapacitacion(
    int $year,
    int $semestre
): array {

    $rango = $semestre === 1
        ? [1, 6]
        : [7, 12];

    $promedio = CursoCalendario::query()
        ->whereYear('fecha_programada', $year)
        ->whereBetween(
            Capsule::raw('MONTH(fecha_programada)'),
            $rango
        )
        ->whereHas(
            'usuario',
            fn ($q) =>
                $q->where(
                    'id_gas',
                    $this->estacionModulo()
                )
        )
        ->avg('resultado');

    if (!$promedio) {

        return [
            'texto' => 'S/I',
            'clase' => 'text-warning'
        ];
    }

    $promedio = round($promedio, 2);

    return [
        'texto' => $promedio . '% ' .
            ($promedio >= 60
                ? 'Excelente'
                : 'Regular'),

        'clase' => $promedio >= 60
            ? 'text-success'
            : 'text-warning'
    ];
}

public function resultadoSatisfaccion(
    int $year,
    int $semestre
): array {

    $rango = $semestre === 1
        ? [1, 6]
        : [7, 12];

    $encuesta = EncuentaEstacion::query()

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->where(
            'estado',
            1
        )

        ->whereYear(
            'fechacreacion',
            $year
        )

        ->whereBetween(
            Capsule::raw('MONTH(fechacreacion)'),
            $rango
        )

        ->latest('fechacreacion')

        ->first();

    if (!$encuesta) {

        return [
            'mala'       => 0,
            'regular'    => 0,
            'buena'      => 0,
            'excelente'  => 0
        ];
    }

    $resultados = EncuentasEstacionClientePreguntas::query()

        ->whereIn(
            'id_cliente',
            $encuesta
                ->clientes()
                ->pluck('id')
        )

        ->selectRaw('resultado, COUNT(*) as total')

        ->groupBy('resultado')

        ->pluck(
            'total',
            'resultado'
        );

    return [

        'mala' => (int) (
            $resultados[1] ?? 0
        ),

        'regular' => (int) (
            $resultados[2] ?? 0
        ),

        'buena' => (int) (
            $resultados[3] ?? 0
        ),

        'excelente' => (int) (
            $resultados[4] ?? 0
        )

    ];
}

public function resultadoIncidentes(
    int $year,
    int $semestre
): string {

    $rango = $semestre === 1
        ? [1, 6]
        : [7, 12];

    $incidentes = InvestigacionIncidenteAccidente::query()

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->whereYear(
            'fechacreacion',
            $year
        )

        ->whereBetween(
            Capsule::raw('MONTH(fechacreacion)'),
            $rango
        )

        ->withCount([
            'formatos',
            'grupos'
        ])

        ->get();

    if ($incidentes->isEmpty()) {

        return "<b class='text-success'>100% Excelente</b>";
    }

    $atendidos = $incidentes
        ->filter(function ($incidente) {

            return (
                $incidente->formatos_count +
                $incidente->grupos_count
            ) >= 2;

        })
        ->count();

    if ($atendidos === 0) {

        return "<b class='text-warning'>50% Regular</b>";
    }

    $porcentaje = round(
        ($atendidos / $incidentes->count()) * 100,
        2
    );

    return $porcentaje >= 60

        ? "<b class='text-success'>{$porcentaje}% Excelente</b>"

        : "<b class='text-warning'>{$porcentaje}% Regular</b>";
}

public function revisionResultadoPdf($id): void
{

$estacion = Estacion::find($this->estacionModulo());  

$indicadores = [

        'implementacion' => [
            'meta'      => $this->meta(1),
            'resultado' => $this->resultadoImplementacion($id)
        ],

        'ventas' => [
            'meta'    => $this->meta(2),
            'detalle' => $this->resultadoVentas($id)
        ],

        'capacitacion' => [
            'meta'       => $this->meta(3),
            'semestre1'  => $this->resultadoCapacitacion($id, 1),
            'semestre2'  => $this->resultadoCapacitacion($id, 2)
        ],

        'satisfaccion' => [
            'meta'       => $this->meta(4),
            'semestre1'  => $this->resultadoSatisfaccion($id, 1),
            'semestre2'  => $this->resultadoSatisfaccion($id, 2)
        ],

        'incidentes' => [
            'meta'       => $this->meta(5),
            'semestre1'  => $this->resultadoIncidentes($id, 1),
            'semestre2'  => $this->resultadoIncidentes($id, 2)
        ]
    ];

    $mesActual = ($id == date('Y'))
        ? (int) date('n')
        : 12;

$html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>REVISIÓN DE RESULTADOS</title>

        <style>

        @page {margin: 1cm 0.5cm; font-family: Arial, Helvetica, sans-serif;}
*,
*::before,
*::after {
  box-sizing: border-box;
}

html {
  font-family: sans-serif;
  line-height: 1.15;
  -webkit-text-size-adjust: 100%;
  -ms-text-size-adjust: 100%;
  -ms-overflow-style: scrollbar;
  -webkit-tap-highlight-color: transparent;
}

@-ms-viewport {
  width: device-width;
}

article, aside, dialog, figcaption, figure, footer, header, hgroup, main, nav, section {
  display: block;
}
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: #212529;
  text-align: left;
  background-color: #fff;
}

.text-center {
  text-align: center !important;
}
.p-1 {
  padding: 0.25rem !important;
}
.mt-1 {
  margin-top: 0.25rem !important;
}
.mt-3 {
  margin-top: 1rem !important;
}
.mt-4 {
  margin-top: 1.5rem !important;
}
table {
  border-collapse: collapse;
}
.table {
  width: 100%;
  max-width: 100%;
  margin-bottom: 1rem;
  background-color: transparent;
}

.table th,
.table td {
  padding: 0.75rem;
  vertical-align: top;
  border-top: 1px solid #dee2e6;
}

.table thead th {
  vertical-align: bottom;
  border-bottom: 2px solid #dee2e6;
}

.table tbody + tbody {
  border-top: 2px solid #dee2e6;
}

.table .table {
  background-color: #fff;
}

.table-sm th,
.table-sm td {
  padding: 0.3rem;
}

.table-bordered {
  border: 1px solid #dee2e6;
}

.table-bordered th,
.table-bordered td {
  border: 1px solid #dee2e6;
}

.table-bordered thead th,
.table-bordered thead td {
  border-bottom-width: 2px;
}
.table-bordered {
  border: 1px solid #dee2e6;
}

.table-bordered th,
.table-bordered td {
  border: 1px solid #dee2e6;
}

.table-bordered thead th,
.table-bordered thead td {
  border-bottom-width: 2px;
}
.table-sm th,
.table-sm td {
  padding: 0.3rem;
}
.align-middle {
  vertical-align: middle !important;
}
small {
  font-size: 80%;
}
.table-active,
.table-active > th,
.table-active > td {
  background-color: rgba(0, 0, 0, 0.075);
}

.table thead th {
  vertical-align: bottom;
  border-bottom: 2px solid #dee2e6;
}

.table tbody + tbody {
  border-top: 2px solid #dee2e6;
}

img {
  vertical-align: middle;
  border-style: none;
}

.table-success,
.table-success > th,
.table-success > td {
  background-color: #c3e6cb;
}

.table-info,
.table-info > th,
.table-info > td {
  background-color: #bee5eb;
}

hr {
  margin-top: 1rem;
  margin-bottom: 1rem;
  border: 0;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
}
.border {
  border: 1px solid #dee2e6 !important;
}
.p-2 {
  padding: 0.5rem !important;
}
.bg-light {
  background-color: #f8f9fa !important;
}
.text-danger {
  color: #dc3545 !important;
}
.text-success {
  color: #28a745 !important;
}
.text-warning {
  color: #ffc107 !important;
}

.border-0 {
  border: 0 !important;
}

        </style>
    </head>
    <body>';

    $html .= '<div style="text-align: center;font-family: Arial, Helvetica, sans-serif;font-size: 1.2em;">Resumen del Año: '.$id.'</div>
    <div style="text-align: center;font-family: Arial, Helvetica, sans-serif;"><b>'.($estacion?->permisocre ?? '').'</b></div>
    <div style="text-align: center;font-family: Arial, Helvetica, sans-serif;">'.($estacion?->razonsocial ?? '').'</div>
    <div style="text-align: center;font-family: Arial, Helvetica, sans-serif;"><small>'.($estacion?->direccioncompleta ?? '').'</small></div>';


    $html .= '<table class="table table-bordered table-sm pb-0 mb-0" style="margin-top: 50px;">
    <tr>
        <td class="align-middle"><b>Objeto</b></td>
        <td class="align-middle">Implementación del SA</td>
        <td class="align-middle"><b>Indicador</b></td>
        <td class="align-middle">No. Total de elementos implementados VS No. de elementos del SA</td>
    </tr>
    <tr>
        <td class="align-middle"><b>Meta</b></td>
        <td class="align-middle">' . $indicadores['implementacion']['meta'] . '</td>
        <td class="align-middle"><b>Frecuencia</b></td>
        <td class="align-middle">Anual</td>
    </tr>

    <tr>
        <td colspan="4">
            <b>Resultado:</b><div>' . $indicadores['implementacion']['resultado'] . '</div>
        </td>
    </tr>

    </table>

    <hr>

    <table class="table table-bordered table-sm pb-0 mb-0">

    <tr>
        <td class="align-middle"><b>Objeto</b></td>
        <td class="align-middle">Ventas</td>
        <td class="align-middle"><b>Indicador</b></td>
        <td class="align-middle">Venta del mes inmediato anterior VS venta del mes actual</td>
    </tr>

    <tr>
        <td class="align-middle"><b>Meta</b></td>
        <td class="align-middle">' . $indicadores['ventas']['meta'] . '</td>
        <td class="align-middle"><b>Frecuencia</b></td>
        <td class="align-middle">Mensual</td>
    </tr>

    </table>';

    $html .= '<div><b>Resultado:</b></div><br>';

    $ventas = $indicadores['ventas']['detalle'];

foreach (array_chunk($ventas, 6) as $grupo) {

    $html .= '<table class="table table-bordered table-sm" style="font-size:.8em;">';

    // Encabezados
    $html .= '<thead><tr>';

    foreach ($grupo as $venta) {

        $html .= '
            <th class="text-center bg-light">
                '.$venta['mes_anterior'].' '.$venta['year_anterior'].'
            </th>

            <th class="text-center bg-light">
                '.$venta['mes_actual'].' '.$venta['year_actual'].'
            </th>';
    }

    $html .= '</tr></thead>';

    // Valores
    $html .= '<tbody><tr>';

    foreach ($grupo as $venta) {

        $html .= '
            <td class="text-center bg-light">
                '.number_format($venta['valor_anterior'],2).'
            </td>

            <td class="text-center bg-light">
                '.number_format($venta['valor_actual'],2).'
            </td>';
    }

    $html .= '</tr>';

    // TC
    $html .= '<tr>';

    foreach ($grupo as $venta) {

        $html .= '
            <td colspan="2" class="text-center bg-light">
                <strong class="'.$venta['tc']['clase'].'">
                    '.$venta['tc']['texto'].'
                </strong>
            </td>';
    }

    $html .= '</tr>';

    $html .= '</tbody></table>';
}



    $html .= '
    <hr>
    <table class="table table-bordered table-sm pb-0 mb-0">

    <tr>
        <td class="align-middle"><b>Objeto</b></td>
        <td class="align-middle">Capacitación</td>
        <td class="align-middle"><b>Indicador</b></td>
        <td class="align-middle">No. de personal capacitado vs No. de personal de la estación</td>
    </tr>

    <tr>
        <td class="align-middle"><b>Meta</b></td>
        <td class="align-middle">'.$indicadores['capacitacion']['meta'].'</td>
        <td class="align-middle"><b>Frecuencia</b></td>
        <td class="align-middle">Semestral</td>
    </tr>

    <tr>

    <td colspan="4">

    <b>Resultado:</b>

    <table class="border-0" style="width: 100%;">
    <tr>

    <td class="border-0">
    Primer semestre:
    <div><span class="'.$indicadores['capacitacion']['semestre1']['clase'].'">
    '.$indicadores['capacitacion']['semestre1']['texto'].'
    </span></div>
    </td>
    
    <td class="border-0">';

    if ($mesActual >= 7):

    $html .= '<div style="margin-top:8px">

    Segundo semestre:

    <div><span class="'.$indicadores['capacitacion']['semestre2']['clase'].'">
    '.$indicadores['capacitacion']['semestre2']['texto'].'
    </span></div>

    </div>';

    endif;

    $html .= '
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    
    <hr>';

    $html .= '<table class="table table-bordered table-sm pb-0 mb-0">

    <tr>
        <td class="align-middle"><b>Objeto</b></td>
        <td class="align-middle">Satisfacción del cliente</td>
        <td class="align-middle"><b>Indicador</b></td>
        <td class="align-middle">Media del total de clientes con experiencia: Mala, Buena y Excelente</td>
    </tr>

    <tr>
        <td><b>Meta</b></td>
        <td>'.$indicadores['satisfaccion']['meta'].'</td>
        <td><b>Frecuencia</b></td>
        <td>Semestral</td>
    </tr>

    <tr>

    <td colspan="4">

    <b>Resultado:</b>

    <table class="border-0" style="width: 100%;">
    <tr>

    <td class="border-0">
    Primer semestre
    <ul>
    <li class="text-danger">Mala: '.$indicadores['satisfaccion']['semestre1']['mala'].'</li>
    <li class="text-warning">Regular: '.$indicadores['satisfaccion']['semestre1']['regular'].'</li>
    <li>Buena: '.$indicadores['satisfaccion']['semestre1']['buena'].'</li>
    <li class="text-success">Excelente: '.$indicadores['satisfaccion']['semestre1']['excelente'].'</li>
    </ul>
    </td>
    
    <td class="border-0">';    

    if ($mesActual >= 7):
    $html .= 'Segundo semestre
    <ul>
    <li class="text-danger">Mala: '.$indicadores['satisfaccion']['semestre2']['mala'].'</li>
    <li class="text-warning">Regular: '.$indicadores['satisfaccion']['semestre2']['regular'].'</li>
    <li>Buena: '.$indicadores['satisfaccion']['semestre2']['buena'].'</li>
    <li class="text-success">Excelente: '.$indicadores['satisfaccion']['semestre2']['excelente'].'</li>
    </ul>';
    endif;

    
    $html .= '
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    
    <hr>';


    $html .= '<table class="table table-bordered table-sm pb-0 mb-0">

    <tr>
        <td class="align-middle"><b>Objeto</b></td>
        <td class="align-middle">Incidentes y accidentes</td>
        <td class="align-middle"><b>Indicador</b></td>
        <td class="align-middle">No total de accidentes e incidentes ocurridos VS número total de accidentes e incidentes atendidos</td>
    </tr>

    <tr>
        <td><b>Meta</b></td>
        <td>'.$indicadores['incidentes']['meta'].'</td>
        <td><b>Frecuencia</b></td>
        <td>Semestral</td>
    </tr>

    <tr>

    <td colspan="4">

    <div><b>Resultado:</b></div>

    <table class="border-0" style="width: 100%;">
    <tr>
    <td class="border-0">
    Primer semestre:
    <div>'.$indicadores['incidentes']['semestre1'].'</div>
    </td>
    <td class="border-0">';

    if ($mesActual >= 7):

    $html .= '
    Segundo semestre:
    <div>'.$indicadores['incidentes']['semestre2'].'</div>
    ';

    endif;

    $html .= '</td></tr>
    </table>';

    $html .= '</td>
    </tr>
    </table>';

    $html .= '
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
        'REVISIÓN DE RESULTADOS.pdf',
        [
            'Attachment' => true
        ]
    );

    exit;

}

//---------------------------------------------

public function indexVentasMes(){

$title = 'VENTAS DEL MES';

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
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'    
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/monitoreoverificacionevaluacion/ventasmes.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('monitoreoverificacionevaluacion/ventasmes', $data,'sasisopa');

}

    public function getVentasMes()
    {
        header('Content-Type: application/json');

        try {

            $year = (int) (
                $_GET['year']
                ?? date('Y')
            );

            $data = self::getVentasMeses(
                $this->estacionModulo(),
                $year
            );

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

        public static function ventasMensual(
        ?int $estacionId,
        int $mes,
        int $year
    ): float {

        $reporte = ReporteCreMes::query()
            ->where('id_estacion', $estacionId)
            ->where('mes', $mes)
            ->where('year', $year)
            ->first();

        if (!$reporte) {
            return 0;
        }

        return (float) ReporteCreProducto::query()
            ->where('id_re_mes', $reporte->id)
            ->sum('volumen_venta');
    }

public static function getVentasMeses(
    ?int $estacionId,
    int $year
): array {

    $data = [];

    for ($mes = 1; $mes <= 12; $mes++) {

        $mesAnterior = $mes - 1;

        if ($mesAnterior === 0) {

            $mesAnterior = 12;
            $yearAnterior = $year - 1;

        } else {

            $yearAnterior = $year;
        }

        $ventaActual = self::ventasMensual(
            $estacionId,
            $mes,
            $year
        );

        $ventaAnterior = self::ventasMensual(
            $estacionId,
            $mesAnterior,
            $yearAnterior
        );

        $resultado = 0;
        $tendencia = 'IGUAL';

        if ($ventaAnterior > 0) {

            $resultado = round(
                (
                    ($ventaActual - $ventaAnterior)
                    / $ventaAnterior
                ) * 100,
                2
            );

            if ($resultado > 0) {

                $tendencia = 'ALZA';

            } elseif ($resultado < 0) {

                $tendencia = 'BAJA';
            }
        }

        $data[] = [

            'mes' => nombremes($mes) . ' del ' . $year,

            'ventas_actual' => number_format(
                $ventaActual,
                2
            ),

            'mes_anterior' => nombremes(
                $mesAnterior
            ) . ' del ' . $yearAnterior,

            'ventas_anterior' => number_format(
                $ventaAnterior,
                2
            ),

            'resultado' => $resultado,

            'tendencia' => $tendencia
        ];
    }

    return $data;
}

}