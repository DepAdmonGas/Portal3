<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\ReporteCreProducto;
use App\Models\Sasisopa\ReporteCrePipa;
use App\Models\Sasisopa\ReporteCreMensaje;
use App\Models\Estacion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class ReporteDiarioController extends BaseController
{
    protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'REPORTE ESTADÍSTICO DE LA CRE';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $this->crearMesesReporteCre($this->estacionId());

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/reportediario/index.action.init.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('reportediario/index', $data, 'sasisopa');
    }

    public function crearMesesReporteCre(int $idEstacion): void
    {
        $anio = date('Y');

        for ($mes = 1; $mes <= 12; $mes++) {

            ReporteCreMes::firstOrCreate(
                [
                    'id_estacion' => $idEstacion,
                    'mes'         => $mes,
                    'year'        => $anio,
                ],
                [
                    'f_producto_uno'  => '',
                    'f_producto_dos'  => '',
                    'f_producto_tres' => '',
                    'fi_producto_uno' => '',
                    'fi_producto_dos' => '',
                    'fi_producto_tres' => '',
                    'ff_producto_uno' => '',
                    'ff_producto_dos' => '',
                    'ff_producto_tres' => '',
                ]
            );
        }
    }

    public function meses()
    {
        header('Content-Type: application/json');

        try {

            $year = (int)($_GET['year'] ?? date('Y'));

            $actualYear = (int)date('Y');
            $actualMes  = (int)date('m');

            $meses = ReporteCreMes::where(
                'id_estacion',
                $this->estacionId()
            )
                ->where('year', $year)
                ->orderBy('mes')
                ->get()
                ->map(function ($item) use ($year, $actualYear, $actualMes) {

                    $habilitado = $year < $actualYear
                        || $item->mes <= $actualMes;

                    return [
                        'mes' => $item->mes,
                        'year' => $item->year,
                        'nombre' => nombremes($item->mes),
                        'habilitado' => $habilitado,
                        'url' => $habilitado
                            ? '/sasisopa/' . $item->mes . '/' . $item->year
                            : null

                    ];
                });

            echo json_encode([
                'success' => true,
                'data' => $meses
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function reporteMes(int $mes, int $year)
    {

        $title = 'REPORTE DIARIO ' . strtoupper(nombremes($mes)) . ' ' . $year;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('REPORTE ESTADÍSTICO DE LA CRE', '/sasisopa/reporte-diario');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'mes' => $mes,
            'year' => $year,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/reportediario/reportemes.action.init.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('reportediario/reporte-mes', $data, 'sasisopa');
    }

    public function datatableMes()
    {

        header('Content-Type: application/json');

        $mes = (int)($_GET['mes'] ?? date('m'));
        $year = (int)($_GET['year'] ?? date('Y'));

        $head = $this->headCorte(
            Estacion::find($this->estacionId())
        );

        $productos = collect($head['productos']);

        $reporte = ReporteCreMes::with([
            'productos.pipas',
            'mensajes'
        ])
            ->where('id_estacion', $this->estacionId())
            ->where('mes', $mes)
            ->where('year', $year)
            ->firstOrFail();

        $fechas = $reporte->productos
            ->groupBy(fn($producto) => $producto->fecha->format('Y-m-d'))
            ->sortKeysDesc();

        $rows = [];
        $totales = [];

        foreach ($productos as $producto) {
            $nombre = $producto['nombre'];

            $totales[$nombre] = [
                'vi' => 0,
                'vv' => 0,
                'vf' => 0,
                'vc' => 0,
                'merma' => 0
            ];
        }


        $footer = [];

        foreach ($fechas as $fecha => $productosFecha) {

            $fechaObj = new \DateTime($fecha);

            $row = [
                'id' => $reporte->id,
                'id_fecha' => strtotime($fecha),
                'fecha' => $fecha,
                'fecha_larga' => $fechaObj->format('d') . ' de ' . nombremes((int)$fechaObj->format('m')),
                'productos' => []
            ];

            foreach ($productos as $producto) {

                $nombre = $producto['nombre'];

                $registro = $productosFecha->firstWhere('producto', $nombre);

                if (!$registro) {
                    $row['productos'][] = [
                        'vi' => 0,
                        'vv' => 0,
                        'vf' => 0,
                        'vc' => 0,
                        'merma' => 0
                    ];
                    continue;
                }

                $compra = $registro->pipas->sum('volumen');

                $merma =
                    ($registro->volumen_final + $registro->volumen_venta)
                    - ($registro->volumen_inicial + $compra);

                $row['productos'][] = [
                    'id' => $registro->id,
                    'producto' => $registro->producto,
                    'vi' => $registro->volumen_inicial,
                    'vv' => $registro->volumen_venta,
                    'vf' => $registro->volumen_final,
                    'vc' => $compra,
                    'merma' => round($merma, 2)
                ];

                $totales[$nombre]['vi'] += $registro->volumen_inicial;
                $totales[$nombre]['vv'] += $registro->volumen_venta;
                $totales[$nombre]['vf'] += $registro->volumen_final;
                $totales[$nombre]['vc'] += $compra;
                $totales[$nombre]['merma'] += $merma;
            }


            $timestamp = is_numeric($fecha) ? $fecha : strtotime($fecha);

            $row['mensajes'] = [
                'total' => $reporte->mensajes
                    ->where('id_fecha', $timestamp)
                    ->count()
            ];

            $row['acciones'] = [
                'id_reporte' => $reporte->id,
                'fecha' => $timestamp
            ];

            $rows[] = $row;
        }


        foreach ($productos as $producto) {

            $nombre = $producto['nombre'];

            $footer[] = [
                'producto' => $nombre,
                'vi' => round($totales[$nombre]['vi'], 2),
                'vv' => round($totales[$nombre]['vv'], 2),
                'vf' => round($totales[$nombre]['vf'], 2),
                'vc' => round($totales[$nombre]['vc'], 2),
                'merma' => round($totales[$nombre]['merma'], 2)
            ];
        }

        echo json_encode([
            'success' => true,
            'head' => $head,
            'data' => $rows,
            'footer' => $footer
        ]);
    }

    public function headCorte(Estacion $estacion)
    {

        $productos = [];

        if (!empty($estacion->producto_uno)) {
            $productos[] = [
                'nombre' => $estacion->producto_uno,
                'color'  => 'success'
            ];
        }

        if (!empty($estacion->producto_dos)) {
            $productos[] = [
                'nombre' => $estacion->producto_dos,
                'color'  => 'danger'
            ];
        }

        if (!empty($estacion->producto_tres)) {
            $productos[] = [
                'nombre' => $estacion->producto_tres,
                'color'  => 'dark'
            ];
        }

        return $head = [
            'productos' => $productos,
            'columnas' => [
                [
                    'campo' => 'volumen_inicial',
                    'titulo' => 'Vo. (Lt) inicial',
                    'tooltip' => 'Volumen (Lt) Inicial'
                ],
                [
                    'campo' => 'volumen_venta',
                    'titulo' => 'Vo. (Lt) venta',
                    'tooltip' => 'Volumen (Lt) Venta'
                ],
                [
                    'campo' => 'volumen_final',
                    'titulo' => 'Vo. (Lt) final',
                    'tooltip' => 'Volumen (Lt) Final'
                ],
                [
                    'campo' => 'compra',
                    'titulo' => 'Vo. (Lt) compra',
                    'tooltip' => 'Volumen (Lt) Compra'
                ],
                [
                    'campo' => 'merma',
                    'titulo' => 'Merma',
                    'tooltip' => 'Merma'
                ]
            ]
        ];
    }

    public function reportePdf()
    {
        $estacion = Estacion::find($this->estacionId());

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $reporte = ReporteCreMes::where('id_estacion', $this->estacionId())
            ->where('mes', (int)$_GET['idMes'])
            ->where('year', (int)$_GET['idYear'])
            ->firstOrFail();

        $idReporteCre = $reporte->id;

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Atención de Hallazgos</title>
        <style>
          @page {margin: 0.5cm 1cm; font-family: Arial, Helvetica, sans-serif;}
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


body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: #212529;
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

.mb-2,
.my-2 {
  margin-bottom: 0.5rem !important;
}

table {
  border-collapse: collapse;
}
.table {
  width: 100%;
  max-width: 100%;
  margin-bottom: 10px;
  background-color: transparent;
}

.table th,
.table td {
  padding: 0.30rem;
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
  padding: 0.2rem;
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
  padding: 0.2rem;
}
.align-middle {
  vertical-align: middle !important;
}

.border {
  border: 1px solid #dee2e6 !important;
}

.mt-3,
.my-3 {
  margin-top: 1rem !important;
}

.p-3 {
  padding: 1rem !important;
}

.mb-3,
.my-3 {
  margin-bottom: 1rem !important;
} 
        </style>
    </head>
    <body>
        <table class="table table-bordered">
            <tr>
                <td class="text-center align-middle">
                    <img src="' . $logo . '" width="150">
                </td>
                <td colspan="2" class="text-center align-middle">
                    <strong>Reporte Estadistico Diario</strong>
                </td>
                <td class="text-center align-middle">
                    Fo.ADMONGAS.005
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
        ';

        $productosReporte = [];

        $productosSesion = [
            [
                'nombre' => $estacion->producto_uno,
                'color'  => '#74bc1f',
            ],
            [
                'nombre' => $estacion->producto_dos,
                'color'  => '#e01883',
            ],
        ];

        if (!empty($estacion->producto_tres)) {
            $productosSesion[] = [
                'nombre' => $estacion->producto_tres,
                'color'  => '#5c108c',
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Obtener información de cada producto
    |--------------------------------------------------------------------------
    */

        foreach ($productosSesion as &$item) {

            $detalle = $this->detalleReporteCre($item['nombre'], $idReporteCre);

            $item['detalle'] = $detalle;

            $item['merma'] =
                $detalle['VolumenInicial']
                + $detalle['VolumenCompra']
                - $detalle['VolumenVenta']
                - $detalle['VolumenFinal'];
        }

        unset($item);

        /*
    |--------------------------------------------------------------------------
    | Tabla de Volúmenes
    |--------------------------------------------------------------------------
    */

        $html .= '<table class="table table-bordered" style="font-size:1em;margin-top:50px;">';
        $html .= '<tbody>';

        $html .= '
    <tr>
        <td class="align-middle text-center"><b>Producto</b></td>
        <td class="align-middle text-center"><b>Volumen (Lt) Inicial</b></td>
        <td class="align-middle text-center"><b>Volumen (Lt) de Venta</b></td>
        <td class="align-middle text-center"><b>Volumen (Lt) Final</b></td>
        <td class="align-middle text-center"><b>Volumen (Lt) de Compra</b></td>
    </tr>';

        foreach ($productosSesion as $producto) {

            $d = $producto['detalle'];

            $html .= '
        <tr>
            <td class="align-middle text-center" style="background-color:' . $producto['color'] . ';color:#fff;">
                <b>' . $producto['nombre'] . '</b>
            </td>

            <td class="align-middle text-center">' . number_format($d['VolumenInicial'], 2) . '</td>

            <td class="align-middle text-center">' . number_format($d['VolumenVenta'], 2) . '</td>

            <td class="align-middle text-center">' . number_format($d['VolumenFinal'], 2) . '</td>

            <td class="align-middle text-center">' . number_format($d['VolumenCompra'], 2) . '</td>
        </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        /*
    |--------------------------------------------------------------------------
    | Tabla de Merma
    |--------------------------------------------------------------------------
    */

        $html .= '<div style="font-size:1.2em;margin-top:30px;">Merma</div>';

        $html .= '<table class="table table-bordered" style="font-size:1em;margin-top:30px;">';
        $html .= '<tbody>';

        foreach ($productosSesion as $producto) {

            $html .= '
        <tr>
            <td class="align-middle text-center" style="background-color:' . $producto['color'] . ';color:#fff;">
                <b>' . $producto['nombre'] . '</b>
            </td>

            <td class="align-middle text-center">
                ' . number_format($producto['merma'], 2) . '
            </td>
        </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '
    </body>
    </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream(
            'Reporte Estadistico Diario.pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    }

    function detalleReporteCre(string $producto, int $idReporteCre): array
    {
        $detalle = [
            'VolumenInicial' => 0,
            'VolumenVenta'   => 0,
            'VolumenFinal'   => 0,
            'VolumenCompra'  => 0,
        ];

        $productos = ReporteCreProducto::where('id_re_mes', $idReporteCre)
            ->where('producto', $producto)
            ->get();

        foreach ($productos as $item) {

            $detalle['VolumenInicial'] += (float) $item->volumen_inicial;
            $detalle['VolumenVenta']   += (float) $item->volumen_venta;
            $detalle['VolumenFinal']   += (float) $item->volumen_final;

            $detalle['VolumenCompra'] += (float) ReporteCrePipa::where(
                'id_re_producto',
                $item->id
            )->sum('volumen');
        }

        return $detalle;
    }

    //---------- Facturas

    public function facturas(int $year)
    {

        $title = 'FACTURAS DE PRODUCTOS ' . $year;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('REPORTE ESTADÍSTICO DE LA CRE', '/sasisopa/reporte-diario');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $reporte = ReporteCreMes::where('id_estacion', $this->estacionId())
            ->where('year', $year)
            ->firstOrFail();

        $meses = $this->obtenerMeses($this->estacionId(), $year);
        $productos = $this->obtenerProductos();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'year' => $year,
            'idReporteCre' => $reporte->id,
            'meses'     => $meses,
            'productos' => $productos,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/reportediario/facturas.action.init.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('reportediario/facturas', $data, 'sasisopa');
    }

    public function getFacturas(int $year): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            echo json_encode([

                'success' => true,

                'meses' => $this->obtenerMeses(
                    $this->estacionId(),
                    $year
                ),

                'productos' => $this->obtenerProductos(),

                'etapas' => $this->etapas()

            ]);
        } catch (\Throwable $e) {

            echo json_encode([

                'success' => false,

                'message' => $e->getMessage()

            ]);
        }
    }

    private function obtenerMeses(
        int $idEstacion,
        int $year
    ): array {

        return ReporteCreMes::query()

            ->where('id_estacion', $idEstacion)

            ->where('year', $year)

            ->orderBy('mes')

            ->get()

            ->map(function ($item) {

                return [

                    'id' => $item->id,

                    'mes' => $item->mes,

                    'nombre' => nombremes($item->mes),

                    'year' => $item->year,

                    'f_producto_uno' => $item->f_producto_uno,
                    'f_producto_dos' => $item->f_producto_dos,
                    'f_producto_tres' => $item->f_producto_tres,

                    'fi_producto_uno' => $item->fi_producto_uno,
                    'fi_producto_dos' => $item->fi_producto_dos,
                    'fi_producto_tres' => $item->fi_producto_tres,

                    'ff_producto_uno' => $item->ff_producto_uno,
                    'ff_producto_dos' => $item->ff_producto_dos,
                    'ff_producto_tres' => $item->ff_producto_tres,

                ];
            })

            ->toArray();
    }

    private function obtenerProductos(): array
    {
        $productos = [];

        $estacioon = Estacion::find($this->estacionId());

        if (!empty($estacioon->producto_uno)) {

            $productos[] = [
                'id'     => 1,
                'nombre' => $estacioon->producto_uno,
                'campo'  => 'producto_uno'
            ];
        }

        if (!empty($estacioon->producto_dos)) {

            $productos[] = [
                'id'     => 2,
                'nombre' => $estacioon->producto_dos,
                'campo'  => 'producto_dos'
            ];
        }

        if (!empty($estacioon->producto_tres)) {

            $productos[] = [
                'id'     => 3,
                'nombre' => $estacioon->producto_tres,
                'campo'  => 'producto_tres'
            ];
        }

        return $productos;
    }

    protected function etapas(): array
    {
        return [

            [
                'id'      => 1,
                'titulo'  => 'Inicio de mes',
                'prefijo' => 'f'
            ],

            [
                'id'      => 2,
                'titulo'  => 'Mediados de mes',
                'prefijo' => 'fi'
            ],

            [
                'id'      => 3,
                'titulo'  => 'Final de mes',
                'prefijo' => 'ff'
            ]

        ];
    }

    public function guardarFacturas(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $idReporte = (int)($_POST['idReporte'] ?? 0);
            $tipo      = (int)($_POST['tipo'] ?? 0);

            $reporte = ReporteCreMes::findOrFail($idReporte);

            $prefijo = match ($tipo) {
                1 => 'f',
                2 => 'fi',
                3 => 'ff',
                default => throw new \Exception('Periodo inválido.')
            };

            $productos = $this->obtenerProductos();

            // Todos los archivos son obligatorios
            foreach ($productos as $index => $producto) {

                $input = 'file' . ($index + 1);

                if (
                    !isset($_FILES[$input]) ||
                    $_FILES[$input]['error'] !== UPLOAD_ERR_OK
                ) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Debe seleccionar el PDF de {$producto['nombre']}."
                    ]);
                }
            }

            // mismo timestamp para todos
            $timestamp = time();

            foreach ($productos as $index => $producto) {

                $input = 'file' . ($index + 1);

                $ruta = $this->subirPdf(
                    $_FILES[$input],
                    $producto['id'],
                    $timestamp
                );

                $campo = "{$prefijo}_{$producto['campo']}";

                $reporte->$campo = $ruta;
            }

            $reporte->save();

            echo json_encode([
                'success' => true,
                'message' => 'Facturas guardadas correctamente.'
            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function subirPdf(
        array $archivo,
        int $producto,
        int $timestamp
    ): string {

        $extension = strtolower(
            pathinfo($archivo['name'], PATHINFO_EXTENSION)
        );

        if ($extension !== 'pdf') {
            echo json_encode([
                'success' => false,
                'message' => 'Todos los archivos deben ser PDF.'
            ]);
        }

        $directorio =   __DIR__ . '/../../public/uploads/archivos/cre/';

        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }

        $nombre = sprintf(
            '%s-P%s-%s.pdf',
            $this->estacionId(),
            $producto,
            $timestamp
        );

        $destino = $directorio . $nombre;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            echo json_encode([
                'success' => false,
                'message' => 'No fue posible guardar el PDF del producto'
            ]);
        }

        return 'archivos/cre/' . $nombre;
    }


    //---------- Facturas

    //----------------------------------------------------------------
    //----------------------------------------------------------------


    public function reporteMesNuevo($mes,$year)
{

    $title='AGREGAR REPORTE ESTADÍSTICO DE LA CRE';

    Breadcrumb::add('Home','/home');
    Breadcrumb::add('SASISOPA','/sasisopa');
    Breadcrumb::add(
        'REPORTE DIARIO '.strtoupper(nombremes($mes)).' '.$year,
        '/sasisopa/reporte-diario/'.$mes.'/'.$year
    );
    Breadcrumb::add($title,'');

    $permisos=ModuloService::permisosSesion($this->modulo);

    $reporte=ReporteCreMes::where('id_estacion',$this->estacionId())
        ->where('mes',$mes)
        ->where('year',$year)
        ->firstOrFail();

    $ultimoDia=date(
        'd',
        mktime(
            0,
            0,
            0,
            $mes+1,
            0,
            $year
        )
    );

    $mesFormateado=str_pad($mes,2,'0',STR_PAD_LEFT);

    $data=[

        'title'=>$title,

        'permisos'=>$permisos,

        'modulo'=>$this->modulo,

        'filtro_usuario'=>$this->filtro_usuario,

        'mes'=>$mes,

        'year'=>$year,

        'diamin'=>$year.'-'.$mesFormateado.'-01',

        'diamax'=>$year.'-'.$mesFormateado.'-'.$ultimoDia,

        'idReporteCre'=>$reporte->id,
        'modo' => 'crear',

        'links'=>[],

        'scripts'=>[
            '/js/vendor.min.js',
            '/js/reportediario/reportemesnuevo.action.init.js?v=' . time(),
        ],

        'help'=>false

    ];

    View::render(
        'reportediario/reporte-mes-nuevo',
        $data,
        'sasisopa'
    );

    }

        public function reporteMesEditar($idReporteCre,$fechaUnix)

    {

        $title = 'AGREGAR REPORTE ESTADÍSTICO DE LA CRE';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            'REPORTE DIARIO ' . strtoupper(nombremes($mes)) . ' ' . $year,
            '/sasisopa/reporte-diario/' . $mes . '/' . $year
        );
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $reporte = ReporteCreMes::where('id_estacion', $this->estacionId())
            ->where('mes', $mes)
            ->where('year', $year)
            ->firstOrFail();

        $ultimoDia = date(
            'd',
            mktime(
                0,
                0,
                0,
                $mes + 1,
                0,
                $year
            )
        );

        $mesFormateado = str_pad($mes, 2, '0', STR_PAD_LEFT);

        $data = [

            'title' => $title,

            'permisos' => $permisos,

            'modulo' => $this->modulo,

            'filtro_usuario' => $this->filtro_usuario,

            'mes' => $mes,

            'year' => $year,

            'diamin' => $year . '-' . $mesFormateado . '-01',

            'diamax' => $year . '-' . $mesFormateado . '-' . $ultimoDia,

            'idReporteCre' => $reporte->id,
            'modo' => 'crear',

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',

                '/js/reportediario/reportemesnuevo.action.init.js?v=1.1'

            ],

            'help' => false

        ];

        View::render(
            'reportediario/reporte-mes-nuevo',
            $data,
            'sasisopa'
        );
    }

    public function reporteMesEditar($idReporteCre, $fechaUnix)
    {

        $reporte = ReporteCreMes::where(
            'id_estacion',
            $this->estacionId()
        )
            ->findOrFail($idReporteCre);

        $fecha = date('Y-m-d', $fechaUnix);

        $mes = $reporte->mes;
        $year = $reporte->year;

        $title = 'EDITAR REPORTE ESTADÍSTICO DE LA CRE';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            'REPORTE DIARIO ' . strtoupper(nombremes($mes)) . ' ' . $year,
            '/sasisopa/reporte-diario/' . $mes . '/' . $year
        );
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $ultimoDia = date(
            'd',
            mktime(
                0,
                0,
                0,
                $mes + 1,
                0,
                $year
            )
        );

        $mesFormateado = str_pad($mes, 2, '0', STR_PAD_LEFT);

        $data = [

            'title' => $title,

            'permisos' => $permisos,

            'modulo' => $this->modulo,

            'filtro_usuario' => $this->filtro_usuario,

            'mes' => $mes,

            'year' => $year,

            'fecha' => $fecha,

            'diamin' => $year . '-' . $mesFormateado . '-01',

            'diamax' => $year . '-' . $mesFormateado . '-' . $ultimoDia,

            'idReporteCre' => $idReporteCre,
            'modo' => 'editar',

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/reportediario/reportemesnuevo.action.init.js?v=2.0'
            ],

            'help' => false

        ];

        View::render(
            'reportediario/reporte-mes-nuevo',
            $data,
            'sasisopa'
        );
    }

    public function baseReporteDiario(): void
    {

        header('Content-Type: application/json');

        $estacion = Estacion::findOrFail($this->estacionId());

        $productos = [];

        $colores = [

            'producto_uno' => 'success',

            'producto_dos' => 'danger',

            'producto_tres' => 'dark'

        ];

        foreach ($colores as $campo => $color) {

            if (blank($estacion->$campo)) {
                continue;
            }

            $productos[] = [

                'id' => null,

                'nombre' => $estacion->$campo,

                'color' => $color,

                'volumen' => [

                    'inicial' => null,

                    'venta' => null,

                    'final' => null

                ],

                'pipas' => [

                    [
                        'id' => null,
                        'volumen' => null,
                        'precio' => null,
                        'costo' => null,
                        'factura' => '',
                        'transportista' => '',
                        'importe' => null,
                        'eliminar' => false
                    ],

                    [
                        'id' => null,
                        'volumen' => null,
                        'precio' => null,
                        'costo' => null,
                        'factura' => '',
                        'transportista' => '',
                        'importe' => null,
                        'eliminar' => false
                    ],

                    [
                        'id' => null,
                        'volumen' => null,
                        'precio' => null,
                        'costo' => null,
                        'factura' => '',
                        'transportista' => '',
                        'importe' => null,
                        'eliminar' => false
                    ]

                ]

            ];
        }

        echo json_encode([

            'success' => true,

            'data' => $productos

        ]);
    }

    public function baseReporteDiarioEditar(int $idReporteCre, string $fecha): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $estacion = Estacion::findOrFail($this->estacionId());

            $productos = [];

            $colores = [
                'producto_uno'  => 'success',
                'producto_dos'  => 'danger',
                'producto_tres' => 'dark',
            ];

            foreach ($colores as $campo => $color) {

                $nombreProducto = trim($estacion->$campo ?? '');

                if ($nombreProducto === '') {
                    continue;
                }

                $producto = ReporteCreProducto::where('id_re_mes', $idReporteCre)
                    ->where('fecha', $fecha)
                    ->where('producto', $nombreProducto)
                    ->first();

                /*
                |--------------------------------------------------------------
                | Si por alguna razón no existe el producto lo agregamos vacío
                |--------------------------------------------------------------
                */

                if (!$producto) {

                    $productos[] = [

                        'id'      => null,
                        'nombre'  => $nombreProducto,
                        'color'   => $color,

                        'volumen' => [
                            'inicial' => null,
                            'venta'   => null,
                            'final'   => null,
                        ],

                        'pipas' => array_fill(0, 3, [
                            'id'             => null,
                            'volumen'        => null,
                            'precio'         => null,
                            'costo'          => null,
                            'factura'        => '',
                            'transportista'  => '',
                            'importe'        => null,
                            'eliminar'       => false,
                        ])

                    ];

                    continue;
                }

                $listaPipas = ReporteCrePipa::where('id_re_producto', $producto->id)
                    ->orderBy('pipa_numero')
                    ->get();

                $pipas = [];
                $totalCompra = 0;

                foreach ($listaPipas as $pipa) {

                    $totalCompra += (float)$pipa->volumen;

                    $pipas[] = [

                        'id' => $pipa->id,

                        'volumen' => $pipa->volumen,

                        'precio' => $pipa->precio_litro,

                        'costo' => $pipa->costo_flete,

                        'factura' => $pipa->no_factura,

                        'transportista' => $pipa->nombre_razonsocial,

                        'importe' => $pipa->importe_total,

                        'eliminar' => false

                    ];
                }

                /*
                |--------------------------------------------------------------
                | Siempre mostrar mínimo 3 filas
                |--------------------------------------------------------------
                */



                $merma =
                    (float)$producto->volumen_inicial +
                    $totalCompra -
                    (float)$producto->volumen_venta -
                    (float)$producto->volumen_final;

                $productos[] = [

                    'id' => $producto->id,

                    'nombre' => $producto->producto,

                    'color' => $color,

                    'volumen' => [

                        'inicial' => $producto->volumen_inicial,

                        'venta' => $producto->volumen_venta,

                        'final' => $producto->volumen_final,

                    ],

                    'totalCompra' => round($totalCompra, 2),

                    'merma' => round($merma, 2),

                    'pipas' => $pipas

                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $productos,
                'fecha_larga' => formatearFecha($fecha),


            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'No fue posible obtener la información.',
                'error'   => $e->getMessage()
            ]);
        }
    }

    public function guardarReporteDiario(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $idReporteCre = sanitize_input($data['idReporteCre'] ?? null, 'int');
            $fecha        = sanitize_input($data['fecha'] ?? null, 'string');
            $productos    = $data['productos'] ?? [];

            if (empty($idReporteCre) || empty($fecha)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Información incompleta.'
                ]);
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Detectar si es CREAR o EDITAR
            |--------------------------------------------------------------------------
            */

            $esEditar = false;

            foreach ($productos as $producto) {

                if (!empty($producto['id'])) {
                    $esEditar = true;
                    break;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Permisos
            |--------------------------------------------------------------------------
            */

            if ($esEditar) {

                if (!ModuloService::validaPermiso($this->modulo, 'editar')) {

                    echo json_encode([
                        'success' => false,
                        'message' => 'No tienes permiso para editar.'
                    ]);
                    return;
                }
            } else {

                if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

                    echo json_encode([
                        'success' => false,
                        'message' => 'No tienes permiso para crear.'
                    ]);
                    return;
                }

                $existe = ReporteCreProducto::where('id_re_mes', $idReporteCre)
                    ->where('fecha', $fecha)
                    ->exists();

                if ($existe) {

                    echo json_encode([
                        'success' => false,
                        'message' => 'Ya existe un reporte para esa fecha.'
                    ]);
                    return;
                }
            }

            Capsule::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | RECORRER PRODUCTOS
            |--------------------------------------------------------------------------
            */

            foreach ($productos as $producto) {

                /*
                |--------------------------------------------------------------------------
                | CREAR PRODUCTO
                |--------------------------------------------------------------------------
                */

                if (empty($producto['id'])) {

                    $productoBD = ReporteCreProducto::create([

                        'id_re_mes' => $idReporteCre,

                        'fecha' => $fecha,

                        'producto' => $producto['nombre'],

                        'volumen_inicial' => $producto['volumen']['inicial'],

                        'volumen_venta' => $producto['volumen']['venta'],

                        'volumen_final' => $producto['volumen']['final']

                    ]);
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | EDITAR PRODUCTO
                    |--------------------------------------------------------------------------
                    */

                    $productoBD = ReporteCreProducto::findOrFail($producto['id']);

                    $productoBD->update([

                        'volumen_inicial' => $producto['volumen']['inicial'],

                        'volumen_venta' => $producto['volumen']['venta'],

                        'volumen_final' => $producto['volumen']['final']

                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | PIPAS
                |--------------------------------------------------------------------------
                */

                foreach ($producto['pipas'] as $index => $pipa) {

                    $vacia = empty($pipa['volumen']) &&
                        empty($pipa['precio']) &&
                        empty($pipa['costo']) &&
                        empty($pipa['factura']) &&
                        empty($pipa['transportista']) &&
                        empty($pipa['importe']);

                    /*
                    |--------------------------------------------------------------------------
                    | ELIMINAR
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($pipa['eliminar'])) {

                        if (!empty($pipa['id'])) {

                            ReporteCrePipa::where('id', $pipa['id'])->delete();
                        }

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SI ESTA VACIA NO HACER NADA
                    |--------------------------------------------------------------------------
                    */

                    if ($vacia) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | NUEVA PIPA
                    |--------------------------------------------------------------------------
                    */

                    if (empty($pipa['id'])) {

                        ReporteCrePipa::create([

                            'id_re_producto' => $productoBD->id,

                            'pipa_numero' => $index + 1,

                            'volumen' => $pipa['volumen'],

                            'precio_litro' => $pipa['precio'],

                            'costo_flete' => $pipa['costo'],

                            'no_factura' => $pipa['factura'],

                            'nombre_razonsocial' => $pipa['transportista'],

                            'importe_total' => $pipa['importe']

                        ]);
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | ACTUALIZAR PIPA
                        |--------------------------------------------------------------------------
                        */

                        ReporteCrePipa::where('id', $pipa['id'])->update([

                            'pipa_numero' => $index + 1,

                            'volumen' => $pipa['volumen'],

                            'precio_litro' => $pipa['precio'],

                            'costo_flete' => $pipa['costo'],

                            'no_factura' => $pipa['factura'],

                            'nombre_razonsocial' => $pipa['transportista'],

                            'importe_total' => $pipa['importe']

                        ]);
                    }
                }
            }

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => $esEditar
                    ? 'Reporte actualizado correctamente.'
                    : 'Reporte guardado correctamente.'
            ]);
        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => 'Ocurrió un error al guardar el reporte.'
            ]);
        }
    }

    public function createReporteDiario(): void
    {
        $this->guardarReporteDiario();
    }

    public function updateReporteDiario(): void
    {
        $this->guardarReporteDiario();
    }

    //---------------------------------------------------------------------
    //---------------------------------------------------------------------

    public function getMensajes(int $idReporte, int $fecha): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $mensajes = ReporteCreMensaje::with('usuario:id,nombre')
                ->where('id_reporte', $idReporte)
                ->where('id_fecha', $fecha)
                ->orderBy('id')
                ->get();

            $data = [];

            foreach ($mensajes as $mensaje) {

                $nombre = '';

                if ($mensaje->usuario) {

                    $partes = explode(' ', trim($mensaje->usuario->nombre));

                    $nombre = implode(' ', array_slice($partes, 0, 2));
                }

                $data[] = [

                    'id'       => $mensaje->id,

                    'usuario'  => $nombre,

                    'inicial'  => strtoupper(substr($nombre, 0, 1)),

                    'mensaje'  => $mensaje->mensaje,

                    'tipo'     => (int)$mensaje->tipo,

                    'fecha'    => formatearFecha($mensaje->fecha->format('Y-m-d')),

                    'hora' => strtolower($mensaje->fecha->format('h:i A')),

                    'propio'   => $mensaje->id_usuario == $this->userId()

                ];
            }


            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e
            ]);
        }
    }

    public function guardarMensaje(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            if (!ModuloService::validaPermiso($this->modulo, 'editar')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso.'
                ]);
                return;
            }

            $request = json_decode(file_get_contents('php://input'), true);

            $idReporte = sanitize_input(
                $request['idReporte'] ?? null,
                'int'
            );

            $fecha = sanitize_input(
                $request['fecha'] ?? null,
                'string'
            );

            $mensaje = trim(
                sanitize_input(
                    $request['mensaje'] ?? '',
                    'string'
                )
            );

            $tipo = sanitize_input(
                $request['tipo'] ?? 0,
                'int'
            );

            if (
                empty($idReporte) ||
                empty($fecha) ||
                $mensaje === ''
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Completa la información.'
                ]);
                return;
            }

            ReporteCreMensaje::create([

                'id_reporte' => $idReporte,

                'id_fecha' => $fecha,

                'id_usuario' => $this->userId(),

                'mensaje' => $mensaje,

                'tipo' => $tipo,

                'fecha' => date('Y-m-d H:i:s')

            ]);

            $total = ReporteCreMensaje::where('id_reporte', $idReporte)
                ->where('id_fecha', $fecha)
                ->count();

            echo json_encode([
                'success' => true,
                'message' => 'Mensaje enviado.',
                'total' => $total
            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'No fue posible enviar el mensaje.'
            ]);
        }
    }
}
