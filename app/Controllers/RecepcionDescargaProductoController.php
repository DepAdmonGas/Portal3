<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\RecepcionDescargar;
use Dompdf\Dompdf;
use Dompdf\Options;

class RecepcionDescargaProductoController extends BaseController
{
    protected string $modulo = 'sasisopa';


    public function index()
    {
        $title = 'Recepción y Descarga del Producto';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');

        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );

        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion(
            $this->modulo
        );

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
                '/js/controlactividadproceso/recepciondescargaproducto.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/recepciondescargaproducto.action.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render(
            'controlactividadproceso/recepcion-descarga-producto',
            $data,
            'sasisopa'
        );
    }

    // =====================================================
    // DATATABLE
    // =====================================================

    public function datatable()
    {
        try {

            [
                'year' => $year,
                'mes' => $mes
            ] = $this->filtros();

            $data = $this->queryRegistros(
                $year,
                $mes
            )
            ->get()

            ->map(fn($item) =>
                $this->transformRegistro($item)
            );

            echo json_encode([

                'success' => true,

                'mes' => nombremes($mes),

                'year' => $year,

                'data' => $data
            ]);

        } catch (\Throwable $e) {

            echo json_encode([

                'success' => false,

                'message' => $e->getMessage()
            ]);
        }
    }

    // =====================================================
    // PDF
    // =====================================================

    public function pdf()
    {
        [
            'year' => $year,
            'mes' => $mes
        ] = $this->filtros();

        $estacion = Estacion::find(
            $this->estacionId()
        );

        if (!$estacion) {
            return 'No se encontró información';
        }

        $registros = $this->queryRegistros(
            $year,
            $mes
        )->get();

        $logo = $_ENV['APP_URL']
            . '/assets/images/logos/Logo.png';

        $tituloFecha = !empty($mes)
            ? nombremes($mes) . ' ' . $year
            : $year;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Recepción y Descarga</title>
            <style>
            @page{
                margin:0.6cm 0.6cm;
            }
            body{
                margin:0;
                font-family:Arial, Helvetica, sans-serif;
                color:#212529;
            }
            table{
                width:100%;
                border-collapse:collapse;
            }
            th,
            td{
                border:1px solid #dee2e6;
                padding:3px;
                vertical-align:top;
            }
            .text-center{
                text-align:center;
            }
            .text-end{
                text-align:right;
            }
            .mt-1{
                margin-top:5px;
            }
            .mt-2{
                margin-top:10px;
            }
            .mt-3{
                margin-top:15px;
            }
            .bg-light{
                background:#F5F5F5;
            }
            .border-0{
                border:0 !important;
            }
            .table-header td{
                text-align:center;
                vertical-align:middle;
            }
            .table-active{
                background:rgba(0,0,0,.075);
            }
            .fs-8 { font-size: 0.55rem !important;}

            </style>

        </head>

        <body>

            <div class="text-center mt-2">
                <img src="' . $logo . '" style="width:200px;">
            </div>
            <div class="text-center mt-3">
                <strong>
                    Bitácora de operación
                    (Recepción y Descarga del Producto)
                </strong>
            </div>
            <div class="text-center mt-1">
                <strong>' . $estacion->permisocre . '</strong>
            </div>
            <div class="text-center mt-1">
                ' . $estacion->razonsocial . '
            </div>
            <div class="text-center mt-1">
                ' . $estacion->direccioncompleta . '
            </div>
            <div class="text-center mt-1">
                Código: DLES/SA/003
            </div>
            <div class="text-center mt-2">
                <strong>' . $tituloFecha . '</strong>
            </div>
        ';

        foreach ($registros as $item) {

            $data = $this->transformRegistro($item);

            $html .= '

            <table class="mt-3 fs-8">
                <tr class="table-active">
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Hora llegada</th>
                    <th>Hora salida</th>
                    <th>Tiempo descarga</th>
                    <th>Línea Transporte</th>
                    <th>No. Remolque</th>
                    <th>Vehículo (Placas)</th>
                    <th>Operador</th>
                    <th>No. Factura</th>
                    <th>Litros compra</th>
                    <th>Producto</th>
                </tr>

                <tr style="background:' . $data['row_color'] . ';">

                    <td class="text-center"><b>' . $data['folio'] . '</b></td>
                    <td class="text-center">' . $data['fecha']['display'] . '</td>
                    <td class="text-center">' . $data['hora_llegada']['display'] . '</td>
                    <td class="text-center">' . $data['hora_salida']['display'] . '</td>
                    <td class="text-center">' . $data['tiempo_descarga'] . '</td>
                    <td class="text-center">' . $data['linea_transporte'] . '</td>
                    <td class="text-center">' . $data['no_remolque'] . '</td>
                    <td class="text-center">' . $data['placa'] . '</td>
                    <td class="text-center">' . $data['operador'] . '</td>
                    <td class="text-center">' . $data['no_factura'] . '</td>
                    <td class="text-center"><b>' . $data['litros_compra']['display'] . '</b></td>
                    <td class="text-center">
                        <b style="color:' . $data['producto']['color'] . ';">
                            ' . $data['producto']['nombre'] . '
                        </b>
                    </td>
                </tr>
                <tr>
                    <td colspan="12" style="padding:0;">
                        <table>
                            <tr>
                                <td width="25%" class="border-0">
                                    ' . $this->htmlTanques(
                                        $item->tanques,
                                        $item->litros_compra
                                    ) . '
                                </td>
                                <td width="20%" class="border-0">
            ';

            // =====================================================
            // SELLOS
            // =====================================================

            $html .= '
                <table>
                    <tr>
                        <td
                            colspan="2"
                            class="bg-light text-center">
                            <strong>Sellos</strong>
                        </td>
                    </tr>
            ';
            foreach ($data['sellos']['sellos'] as $sello) {
               $html .= '
                    <tr>
                        <td>
                            ' . $sello['verificar'] . '
                        </td>
                        <td class="text-center">
                            <strong>
                                ' . $sello['resultado'] . '
                            </strong>
                        </td>
                    </tr>
                ';
            }

            $html .= '

                                        <tr>
                                            <td colspan="2">
                                                <strong>No. Serie:</strong>
                                                ' . $data['sello_noserie'] . '
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="20%" class="border-0">
            ';

            // =====================================================
            // NICE
            // =====================================================

            $html .= '

                                    <table>
                                        <tr>
                                            <td
                                                colspan="2"
                                                class="bg-light text-center">
                                                <strong>NICE</strong>
                                            </td>
                                        </tr>
            ';

            foreach (
                $data['sellos']['nice']
                as $nice
            ) {

                $html .= '

                    <tr>
                        <td>
                            ' . $nice['verificar'] . '
                        </td>
                        <td class="text-center">
                            <strong>
                                ' . $nice['resultado'] . '
                            </strong>
                        </td>
                    </tr>
                ';
            }

            $html .= '

                                        <tr>
                                            <td>
                                                Manómetro
                                            </td>
                                            <td>
                                                <strong>
                                                    ' . $data['manometro'] . '
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Temperatura
                                            </td>
                                            <td>
                                                <strong>
                                                    ' . $data['temperatura'] . '
                                                </strong>
                                            </td>
                                        </tr>
                                    </table>
                                </td>

                                <td
                                    width="10%"
                                    class="border-0">
                                    <div
                                        class="bg-light text-center">
                                        <strong>
                                            Observaciones
                                        </strong>
                                    </div>
                                    <div class="mt-1">

                                        ' . (
                                            $data['observaciones']
                                            ?: 'Sin observaciones'
                                        ) . '

                                    </div>
                                </td>
                                <td
                                    width="7.5%"
                                    class="border-0">

                                    <div
                                        class="bg-light text-center">

                                        <strong>
                                            Persona que recibe
                                        </strong>

                                    </div>

                                    <div
                                        class="text-center mt-1">

                                        ' . (
                                            $data['firma_recibe']['firma']
                                            ? '<img
                                                src="' . $data['firma_recibe']['firma'] . '"
                                                style="width:60px;">'
                                            : ''
                                        ) . '

                                    </div>

                                    <div
                                        class="text-center fs-small">

                                        ' . $data['firma_recibe']['nombre'] . '

                                    </div>

                                </td>

                                <td
                                    width="7.5%"
                                    class="border-0">

                                    <div
                                        class="bg-light text-center">

                                        <strong>
                                            Persona que supervisó
                                        </strong>

                                    </div>

                                    <div
                                        class="text-center mt-1">

                                        ' . (
                                            $data['firma_supervisa']['firma']
                                            ? '<img
                                                src="' . $data['firma_supervisa']['firma'] . '"
                                                style="width:60px;">'
                                            : ''
                                        ) . '

                                    </div>

                                    <div
                                        class="text-center fs-small">

                                        ' . $data['firma_supervisa']['nombre'] . '

                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ';
        }
        $html .= '
        </body>
        </html>
        ';

        $options = new Options();
        $options->set('isRemoteEnabled',true);
        $options->set('defaultFont','Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','landscape');
        $dompdf->render();
        return $dompdf->stream(
            'Reporte-de-Recepcion-y-Descarga-del-Producto.pdf',
            [
                'Attachment' => true
            ]
        );
    }

    private function filtros(): array
    {
        return [
           'year' => sanitize_input(
                $_GET['year'] ?? date('Y'),
                'int'
            ),
            'mes' => sanitize_input(
                $_GET['mes'] ?? null,
                'int'
            )
        ];
    }

    private function queryRegistros(?int $year,?int $mes)
    {
        return RecepcionDescargar::query()
            ->with([
                'firmas.usuario:id,nombre,firma',
                'tanques.tanque:id,no_tanque',
                'sellos'
            ])
            ->where('id_estacion',$this->estacionId())
            ->where('estado',1)
            ->when($year, function ($q) use ($year) {
                $q->whereYear('fecha',$year);
            })
            ->when($mes, function ($q) use ($mes) {
                $q->whereMonth('fecha',$mes);
            })
            ->orderByDesc('fecha')
            ->orderByDesc('hora_llegada');
    }

    private function transformRegistro(RecepcionDescargar $item): array 
    {

        $firmaRecibe = $this->obtenerFirma($item->firmas,'FPR');
        $firmaSupervisa = $this->obtenerFirma($item->firmas,'FPS');
        $totalII = $item->tanques->sum('inventario_inicial');
        $totalIF = $item->tanques->sum('inventario_final');
        $merma = $totalIF - ($totalII + $item->litros_compra);

        return [
            'id' => $item->id,
            'folio' => str_pad(
                $item->folio,
                3,
                '0',
                STR_PAD_LEFT
            ),
            'fecha' => [
                'display' => optional($item->fecha)
                    ->format('d/m/Y'),
                'sort' => optional($item->fecha)
                    ->format('Y-m-d'),
                'detalle' => formatearFecha(
                    $item->fecha
                )
            ],
            'hora_llegada' => [
                'display' => optional(
                    $item->hora_llegada
                )->format('g:i a'),

                'sort' => optional(
                    $item->hora_llegada
                )->format('H:i:s')
            ],

            'hora_salida' => [
                'display' => optional(
                    $item->hora_salida
                )->format('g:i a'),

                'sort' => optional(
                    $item->hora_salida
                )->format('H:i:s')
            ],
            'placa' => trim($item->placa),
            'operador' => trim($item->operador),
            'no_factura' => trim($item->no_factura),
            'litros_compra' => [
                'display' => number_format(
                    $item->litros_compra,
                    2
                ),
                'sort' => $item->litros_compra
            ],

            'producto' => [
                'nombre' => trim(
                    $item->producto
                ),
                'color' => $this->productoColor(
                    $item->producto
                )
            ],
            'tiempo_descarga' => $item->tiempo_descarga,
            'linea_transporte' => $item->linea_transporte,
            'no_remolque' => $item->no_remolque,
            'sello_noserie' => $item->sello_noserie,
            'manometro' => $item->manometro,
            'temperatura' => $item->temperatura,
            'observaciones' => trim($item->observaciones ?? ''),
            'row_color' => $item->estado == 0
                ? '#FFE8E8'
                : '#FFFFFF',
            'firma_recibe' => [
                'nombre' =>
                    $firmaRecibe->usuario->nombre ?? '',
                'firma' => $this->firmaUrl(
                    $firmaRecibe->usuario->firma ?? ''
                )
            ],
            'firma_supervisa' => [
                'nombre' =>
                    $firmaSupervisa->usuario->nombre ?? '',
                'firma' => $this->firmaUrl(
                    $firmaSupervisa->usuario->firma ?? ''
                )
            ],
            'tanques' => [
                'items' => $item->tanques->map(
                    function ($tanque) {
                       return [
                            'no_tanque' =>
                                $tanque->tanque->no_tanque ?? '',
                            'inventario_inicial' =>
                                number_format(
                                    $tanque->inventario_inicial,
                                    2
                                ),
                            'inventario_final' =>
                                number_format(
                                    $tanque->inventario_final,
                                    2
                                ),
                            'aditivacion' =>
                                $tanque->aditivacion,
                        ];
                    }
                ),
                'merma' => number_format(
                    $merma,
                    2
                )
            ],

            'sellos' => [
                'sellos' => $item->sellos
                    ->where(
                        'verificar',
                        '!=',
                        'Nivel del producto está a más de 1.5 cm (+/-0.3 cm)'
                    )
                    ->values()
                    ->map(function ($sello) {
                        return [
                            'verificar' =>
                                $sello->verificar,
                            'resultado' =>
                                $sello->resultado,
                        ];
                    }),

                'nice' => $item->sellos
                    ->where(
                        'verificar',
                        'Nivel del producto está a más de 1.5 cm (+/-0.3 cm)'
                    )
                    ->values()
                    ->map(function ($sello) {
                        return [
                            'verificar' =>
                                $sello->verificar,
                            'resultado' =>
                                $sello->resultado,
                        ];
                    })
            ],
            'estado' => (int) $item->estado
        ];
    }

    private function productoColor(string $producto): string 
    {
        return match (trim(strtoupper($producto))) {
            'G PREMIUM' => '#BB1616',
            'G SUPER' => '#16BB43',
            'G DIESEL' => '#212121',
            default => '#000000'
        };
    }

    private function obtenerFirma($firmas, string $tipo) 
    {
        return $firmas
            ->where(
                'tipo_firma',
                $tipo
            )
            ->first();
    }

    private function firmaUrl(?string $firma): string 
    {
        if (empty($firma)) {return '';}
        return $_ENV['APP_URL']. '/uploads/firma-personal/'. $firma;
    }

    private function htmlTanques($tanques, float $litrosCompra): string 
    {
        $html = '
        <table>
            <tr class="bg-light">
                <th>No. Tanque</th>
                <th>Inventario Inicial</th>
                <th>Inventario Final</th>
                <th>Aditivación</th>
            </tr>
        ';

        $totalII = 0;
        $totalIF = 0;

        foreach ($tanques as $tanque) {
            $totalII += $tanque->inventario_inicial;
            $totalIF += $tanque->inventario_final;
            $html .= '

            <tr>
                <td>' . ($tanque->tanque->no_tanque ?? '') . '</td>
                <td class="text-end">
                    ' . number_format(
                        $tanque->inventario_inicial,
                        2
                    ) . '
                </td>
                <td class="text-end">
                    ' . number_format(
                        $tanque->inventario_final,
                        2
                    ) . '
                </td>
                <td>' . $tanque->aditivacion . '</td>
            </tr>
            ';
        }

        $merma = $totalIF - ($totalII + $litrosCompra);

        $html .= '
            <tr>
                <td colspan="4" class="text-end">
                    <strong>
                        Merma:
                        ' . number_format(
                            $merma,
                            2
                        ) . '
                    </strong>
                </td>
            </tr>
        </table>
        ';
        return $html;
    }
}