<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Services\MultiestacionService;
use DateTimeImmutable;
use Throwable;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

use App\Reports\Kpi\ResumenAceitesReport;
use App\Reports\Kpi\ResumenAceitesExcelReport;
use App\Reports\Kpi\ConcentradoVentasReport;
use App\Reports\Kpi\ConcentradoVentasExcelReport;
use App\Reports\Kpi\SolicitudChequeReport;
use App\Reports\Kpi\SolicitudChequeExcelReport;
use App\Reports\Kpi\SolicitudValeReport;
use App\Reports\Kpi\SolicitudValeExcelReport;
use App\Reports\Kpi\ReciboNominaReport;
use App\Reports\Kpi\ReciboNominaExcelReport;
use App\Reports\Kpi\TesoreriaReport;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\VentasDiaOtros;
use App\Models\Operativo\AceiteLubricante;

class ReportesController extends BaseController
{

    protected string $modulo = 'reportes';

    public function index()
    {
        $yearActual = (int) date('Y');
        $title = 'Reporte Anual ' . $yearActual;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Reporte', '');
        Breadcrumb::add($this->dropdownMesReportes(0), '');
        Breadcrumb::add($this->dropdownYearReportes($yearActual), '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $mostrarSelectorEstaciones = !empty(ModuleStationService::getAvailableStations('reportes'));
        $data = [
            'title' => $title,
            'pageTitleId' => 'reportes-page-title',
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'year' => $yearActual,
            'mes' => 0,
            'moduleStationKey' => $mostrarSelectorEstaciones ? 'reportes' : null,
            'moduleStationPlaceholder' => 'Todas las estaciones y departamentos',
            'moduleStationExtraDepartments' => [
                ['id' => 9, 'nombre' => 'Autolavado'],
            ],
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                $mostrarSelectorEstaciones ? '/js/core/module-station-selector.js?v=2.0.0' : null,
                '/js/reportes/index.actions.init.js?v=' . time()
            ],
            'help' => false
        ];

        $data['scripts'] = array_values(array_filter($data['scripts']));

        View::render('reportes/index', $data, 'main');
    }

    private function dropdownMesReportes(int $mesSeleccionado): string
    {
        $opciones = [
            0 => 'Todo el año',
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $actual = htmlspecialchars($opciones[$mesSeleccionado] ?? 'Todo el año', ENT_QUOTES, 'UTF-8');
        $html = '<a class="dropdown-toggle breadcrumb-item active pointer" role="button" data-bs-toggle="dropdown" aria-expanded="false">'
            . '<i class="ti ti-calendar-stats"></i> <span class="ms-1" id="reportes-mes-label">' . $actual . '</span></a>'
            . '<ul class="dropdown-menu animated rubberBand">';

        foreach ($opciones as $mes => $nombre) {
            $clase = ($mes > (int) date('n')) ? ' class="d-none"' : '';
            $html .= '<li data-reportes-month-option="' . $mes . '"' . $clase . '><a class="dropdown-item pointer" href="#" data-reportes-month="' . $mes . '">'
                . '<i class="ti ti-calendar-stats me-1"></i>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        return $html . '</ul>';
    }

    private function dropdownYearReportes(int $yearSeleccionado): string
    {
        $html = '<a class="dropdown-toggle breadcrumb-item active pointer" role="button" data-bs-toggle="dropdown" aria-expanded="false">'
            . '<i class="ti ti-calendar"></i> <span class="ms-1" id="reportes-year-label">' . $yearSeleccionado . '</span></a>'
            . '<ul class="dropdown-menu animated rubberBand">';

        for ($year = (int) date('Y'); $year >= 2021; $year--) {
            $html .= '<li><a class="dropdown-item pointer" href="#" data-reportes-year="' . $year . '">'
                . '<i class="ti ti-calendar me-1"></i>' . $year . '</a></li>';
        }

        return $html . '</ul>';
    }

    public function data(): void
    {
        try {

            $idEstacion = isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;

            $idDepartamento = isset($_GET['idDepartamento'])
                ? (int) $_GET['idDepartamento']
                : 0;

            $year = isset($_GET['year'])
                ? trim((string) $_GET['year'])
                : '';

            $mes = isset($_GET['mes'])
                ? (int) $_GET['mes']
                : 0;

            if ($idEstacion < 0) {

                JsonResponse::error(
                    'La estación no es válida.'
                );

                return;
            }

            if ($year !== '') {

                $yearNumero = (int) $year;

                if (
                    $yearNumero < 2021
                    || $yearNumero > (int) date('Y')
                ) {

                    JsonResponse::error(
                        'El año seleccionado no es válido.'
                    );

                    return;
                }

                $year = (string) $yearNumero;
            }

            if ($mes < 0 || $mes > 12) {

                JsonResponse::error(
                    'El mes seleccionado no es válido.'
                );

                return;
            }

            if ($idDepartamento !== 0) {
                if ($idEstacion !== 0 || $idDepartamento !== 9) {
                    JsonResponse::error('El departamento seleccionado no es válido.');
                    return;
                }

                // Autolavado es un departamento/localidad del catálogo RH.
                $idEstacion = $idDepartamento;
            } elseif ($idEstacion !== 0) {
                // El selector multiestación usa IDs de tb_estaciones; los reportes
                // operativos consultan op_rh_localidades. Convierte por numlista.
                $localidades = MultiestacionService::convertIds(
                    [$idEstacion],
                    MultiestacionService::TABLA_ESTACIONES,
                    MultiestacionService::TABLA_RH_LOCALIDADES
                );
                $idEstacion = $localidades[0] ?? 0;
                if ($idEstacion === 0) {
                    JsonResponse::error('No se encontró la estación.');
                    return;
                }
            }

            $estacion = null;

            if ($idEstacion !== 0) {
                $estacion = RhLocalidad::query()
                    ->select([
                        'id',
                        'localidad'
                    ])
                    ->find($idEstacion);

                if (!$estacion) {

                    JsonResponse::error(
                        'No se encontró la estación.'
                    );

                    return;
                }
            }

            if ($year !== '') {

                $nombreReporte = $mes > 0
                    ? 'Mensual ' . nombremes($mes) . ' ' . $year
                    : 'Anual ' . $year;

                $tipoReporte = 'anual';
            } else {

                $nombreReporte =
                    'Anual ' . date('Y');

                $tipoReporte =
                    'anual';

                $year =
                    date('Y');
            }

            $nombreEstacion =
                $idEstacion === 0
                ? 'Todas las estaciones'
                : ($estacion?->localidad ?? '');

            $reportes =
                $this->generarReportes(
                    $idEstacion,
                    $year,
                    $mes,
                    $tipoReporte
                );

            JsonResponse::custom([
                'success' => true,

                'data' => [

                    'filtros' => [
                        'idEstacion' => $idEstacion,
                        'year' => $year,
                        'mes' => $mes,
                    ],

                    'reporte' => [
                        'nombre' => $nombreReporte,
                        'tipo' => $tipoReporte,
                        'estacion' => $nombreEstacion,
                    ],

                    'reportes' =>
                    $reportes,
                ]
            ]);
        } catch (Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar los reportes.'
            );
        }
    }


    public function autolavadoAnual(): void
    {
        try {

            $year = isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;

            $idEstacion = isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;

            if (
                $year < 2021
                || $year > (int) date('Y')
            ) {

                JsonResponse::error(
                    'El año seleccionado no es válido.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Una sola consulta para los 12 meses
            |--------------------------------------------------------------------------
            */

            $resultados = VentasDiaOtros::query()
                ->selectRaw(
                    '
                    MONTH(op_corte_dia.fecha) AS mes,
                    SUM(op_ventas_dia_otros.importe) AS total
                    '
                )
                ->join(
                    'op_corte_dia',
                    'op_corte_dia.id',
                    '=',
                    'op_ventas_dia_otros.idreporte_dia'
                )
                ->where(
                    'op_ventas_dia_otros.concepto',
                    '5 AUTOLAVADO'
                )
                ->whereYear(
                    'op_corte_dia.fecha',
                    $year
                )
                ->groupByRaw(
                    'MONTH(op_corte_dia.fecha)'
                )
                ->get()
                ->keyBy(
                    fn($item) => (int) $item->mes
                );


            /*
            |--------------------------------------------------------------------------
            | Meses
            |--------------------------------------------------------------------------
            */

            $nombresMeses = [
                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
                6 => 'Junio',
                7 => 'Julio',
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre',
            ];

            $meses = [];

            $total = 0.0;

            foreach ($nombresMeses as $numero => $nombre) {

                $monto =
                    (float) (
                        $resultados
                        ->get($numero)
                        ?->total
                        ?? 0
                    );

                $total += $monto;

                $meses[] = [
                    'numero' => $numero,
                    'nombre' => $nombre,
                    'monto' => $monto,
                ];
            }


            JsonResponse::custom([
                'success' => true,

                'data' => [
                    'year' => $year,
                    'idEstacion' => $idEstacion,
                    'meses' => $meses,
                    'total' => $total,
                ]
            ]);
        } catch (Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar el reporte anual de autolavado.'
            );
        }
    }

    public function autolavadoDiario(): void
    {
        try {
            $dia = trim((string) ($_GET['dia'] ?? ''));
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);

            if (!$fecha || $fecha->format('Y-m-d') !== $dia) {
                JsonResponse::error('La fecha seleccionada no es válida.');
                return;
            }

            if ((int) $fecha->format('Y') < 2021 || $fecha > new DateTimeImmutable('today')) {
                JsonResponse::error('La fecha seleccionada está fuera del periodo disponible.');
                return;
            }

            $total = VentasDiaOtros::query()
                ->join(
                    'op_corte_dia',
                    'op_corte_dia.id',
                    '=',
                    'op_ventas_dia_otros.idreporte_dia'
                )
                ->where('op_ventas_dia_otros.concepto', '5 AUTOLAVADO')
                ->whereDate('op_corte_dia.fecha', $dia)
                ->sum('op_ventas_dia_otros.importe');

            JsonResponse::custom([
                'success' => true,
                'data' => [
                    'dia' => $dia,
                    'dia_formateado' => $fecha->format('d/m/Y'),
                    'total' => (float) $total,
                ],
            ]);
        } catch (Throwable $e) {
            JsonResponse::error('No fue posible cargar el concentrado diario de autolavado.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generar tarjetas
    |--------------------------------------------------------------------------
    */

    private function generarReportes(
        int $idEstacion,
        string $year,
        int $mes,
        string $tipoReporte
    ): array {

        $base =
            '/reportes';


        /*
        |--------------------------------------------------------------------------
        | Reglas heredadas
        |--------------------------------------------------------------------------
        */

        $mostrarGenerales = true;
        $mostrarNomina = true;
        $mostrarAutolavado = true;

        if ($idEstacion === 6) {

            $mostrarNomina = false;
            $mostrarAutolavado = false;
        } elseif ($idEstacion === 9) {

            $mostrarNomina = false;
            $mostrarGenerales = false;
        } elseif ($idEstacion === 2) {

            $mostrarAutolavado = true;
        } elseif ($idEstacion !== 0) {

            $mostrarAutolavado = false;
        }

        $query = http_build_query([
            'idEstacion' => $idEstacion,
            'year' => $year,
        ]);

        $reportes = [];

        if ($mostrarGenerales) {

            $pdfAceites =
                '/reportes/resumen-aceites/pdf?'
                . http_build_query([
                    'idEstacion' => $idEstacion,
                    'year' => $year,
                ]);

            $reportes[] = [
                'id' => 'aceites',

                'nombre' =>
                'Resumen de Aceites',

                'icono' =>
                'ti-droplet',

                'acciones' => [
                    [
                        'tipo' =>
                        'pdf',

                        'nombre' =>
                        'PDF',

                        'icono' =>
                        'ti-file-type-pdf',

                        'url' =>
                        $pdfAceites,
                    ],

                    [
                        'tipo' =>
                        'excel',

                        'nombre' =>
                        'Excel',

                        'icono' =>
                        'ti-file-spreadsheet',

                        'url' =>
                        '/reportes/resumen-aceites/excel?'
                            . http_build_query([
                                'idEstacion' =>
                                $idEstacion,

                                'year' =>
                                $year,
                            ]),
                    ],
                ]
            ];


            /*
            |--------------------------------------------------------------------------
            | Concentrado de ventas
            |--------------------------------------------------------------------------
            */

            $pdfVentas =
                '/reportes/concentrado-ventas/pdf?'
                . http_build_query([
                    'idEstacion' =>
                    $idEstacion,

                    'year' =>
                    $year,
                ]);

            $excelVentas =
                '/reportes/concentrado-ventas/excel?'
                . http_build_query([
                    'idEstacion' => $idEstacion,
                    'year' => $year,
                ]);

            $reportes[] = [
                'id' => 'ventas',
                'nombre' => 'Concentrado de Ventas',
                'icono' => 'ti-chart-bar',
                'acciones' => [
                    [
                        'tipo' => 'pdf',
                        'nombre' => 'PDF',
                        'icono' => 'ti-file-type-pdf',
                        'url' => $pdfVentas,
                    ],
                    [
                        'tipo' => 'excel',
                        'nombre' => 'Excel',
                        'icono' => 'ti-file-spreadsheet',
                        'url' =>
                        $excelVentas
                            . '?'
                            . $query,
                    ],
                ]
            ];


            /*
            |--------------------------------------------------------------------------
            | Solicitud de cheque
            |--------------------------------------------------------------------------
            */

            $reportes[] = [
                'id' =>
                'cheque',

                'nombre' =>
                'Solicitud de Cheque',

                'icono' =>
                'ti-cash-banknote',

                'acciones' => [

                    [
                        'tipo' =>
                        'pdf',

                        'nombre' =>
                        'PDF',

                        'icono' =>
                        'ti-file-type-pdf',

                        'url' =>
                        '/reportes/solicitud-cheque/pdf?'
                            . $query,
                    ],
                    [
                        'tipo' =>
                        'excel',

                        'nombre' =>
                        'Excel',

                        'icono' =>
                        'ti-file-spreadsheet',

                        'url' =>
                        '/reportes/solicitud-cheque/excel?'
                            . $query,
                    ],
                ]
            ];

            /*
            |--------------------------------------------------------------------------
            | Solicitud de vales
            |--------------------------------------------------------------------------
            */

            $reportes[] = [
                'id' =>
                'vales',

                'nombre' =>
                'Solicitud de Vales',

                'icono' =>
                'ti-ticket',

                'acciones' => [
                    [
                        'tipo' =>
                        'pdf',

                        'nombre' =>
                        'PDF',

                        'icono' =>
                        'ti-file-type-pdf',

                        'url' =>
                        '/reportes/solicitud-vales/pdf?'
                            . $query,
                    ],
                    [
                        'tipo' =>
                        'excel',

                        'nombre' =>
                        'Excel',

                        'icono' =>
                        'ti-file-spreadsheet',

                        'url' =>
                        '/reportes/solicitud-vales/excel?'
                            . $query,
                    ],
                ]
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Nómina
        |--------------------------------------------------------------------------
        */

        if (
            $mostrarGenerales
            && $mostrarNomina
        ) {

            $reportes[] = [
                'id' =>
                'nomina',

                'nombre' =>
                'Recibos de Nómina',

                'icono' =>
                'ti-receipt',

                'acciones' => [

                    [
                        'tipo' =>
                        'pdf',

                        'nombre' =>
                        'PDF',

                        'icono' =>
                        'ti-file-type-pdf',

                        'url' =>
                        '/reportes/recibo-nomina/pdf?'
                            . $query,
                    ],

                    [
                        'tipo' =>
                        'excel',

                        'nombre' =>
                        'Excel',

                        'icono' =>
                        'ti-file-spreadsheet',

                        'url' =>
                        '/reportes/recibo-nomina/excel?'
                            . $query,
                    ],
                ]
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Autolavado
        |--------------------------------------------------------------------------
        */

        if ($mostrarAutolavado) {

            $reportes[] = [
                'id' => 'autolavado',
                'nombre' =>
                'Concentrado de Ventas (Autolavado)',
                'icono' => 'ti-car',
                'acciones' => [
                    [
                        'tipo' => 'detalle',
                        'nombre' => 'Ver detalle',
                        'icono' => 'ti-eye',
                        'url' => null,
                    ]
                ]
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Tesorería
        |--------------------------------------------------------------------------
        */

        $urlTesoreria =
            '/reportes/tesoreria/pdf?'
            . http_build_query([
                'idEstacion' =>
                $idEstacion,

                'year' =>
                $year,

                'mes' =>
                $mes,
            ]);


        if ($idEstacion !== 9) {
            $reportes[] = [
                'id' =>
                'tesoreria',

                'nombre' =>
                'Tesorería',

                'icono' =>
                'ti-building-bank',

                'acciones' => [
                    [
                        'tipo' =>
                        'pdf',

                        'nombre' =>
                        'PDF',

                        'icono' =>
                        'ti-file-type-pdf',

                        'url' =>
                        $urlTesoreria,
                    ]
                ]
            ];
        }


        return $reportes;
    }

    //----------------------------------

    public function pdfResumenAceites(): void
    {
        try {

            $idEstacion = isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;

            $year = isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;

            $report =
                new ResumenAceitesReport();

            $report->generar(
                $idEstacion,
                $year
            );
        } catch (RuntimeException $e) {

            $this->pdfError(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {

            error_log(
                $e->getMessage()
            );

            $this->pdfError(
                'No fue posible generar el reporte de aceites.',
                500
            );
        }
    }

    private function pdfError(
        string $message,
        int $status = 422
    ): void {

        if (!headers_sent()) {

            http_response_code(
                $status
            );

            header(
                'Content-Type: text/plain; charset=UTF-8'
            );
        }

        echo $message;
    }

    public function excelResumenAceites(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;

            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;

            $report =
                new ResumenAceitesExcelReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );

            $this->downloadError(
                'No fue posible generar el reporte Excel de aceites.',
                500
            );
        }
    }

    private function downloadError(
        string $message,
        int $status = 422
    ): void {

        if (!headers_sent()) {

            http_response_code(
                $status
            );

            header(
                'Content-Type: text/plain; charset=UTF-8'
            );
        }

        echo $message;
    }

    public function pdfConcentradoVentas(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;

            $report =
                new ConcentradoVentasReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el concentrado de ventas.',
                500
            );
        }
    }

    public function excelConcentradoVentas(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;

            $report =
                new ConcentradoVentasExcelReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el Excel del concentrado de ventas.',
                500
            );
        }
    }

    public function pdfSolicitudCheque(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new SolicitudChequeReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el PDF de solicitud de cheques.',
                500
            );
        }
    }

    public function excelSolicitudCheque(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new SolicitudChequeExcelReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el Excel de solicitud de cheques.',
                500
            );
        }
    }

    public function pdfSolicitudVales(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new SolicitudValeReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el PDF de solicitud de vales.',
                500
            );
        }
    }

    public function excelSolicitudVales(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new SolicitudValeExcelReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el Excel de solicitud de vales.',
                500
            );
        }
    }

    public function pdfReciboNomina(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new ReciboNominaReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el PDF de recibos de nómina.',
                500
            );
        }
    }

    public function excelReciboNomina(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $report =
                new ReciboNominaExcelReport();


            $report->generar(
                $idEstacion,
                $year
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el Excel de recibos de nómina.',
                500
            );
        }
    }

    public function pdfTesoreria(): void
    {
        try {

            $idEstacion =
                isset($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            $year =
                isset($_GET['year'])
                ? (int) $_GET['year']
                : 0;


            $mes =
                isset($_GET['mes'])
                ? (int) $_GET['mes']
                : 0;

            $report =
                new TesoreriaReport();


            $report->generar(
                $idEstacion,
                $year,
                $mes
            );
        } catch (\RuntimeException $e) {

            $this->downloadError(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {

            error_log(
                $e->getMessage()
            );


            $this->downloadError(
                'No fue posible generar el reporte de tesorería.',
                500
            );
        }
    }
}
