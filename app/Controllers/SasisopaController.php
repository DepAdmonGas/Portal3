<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Sasisopa\Sasisopa;
use App\Core\Breadcrumb;

use App\Services\ModuleStationService;

use App\Models\Estacion;
use App\Models\Sasisopa\AnalisisRiesgo;
use App\Models\Sasisopa\AnalisisRiesgoAnexo;
use App\Models\Sasisopa\RepresentanteTecnico;
use App\Models\Sasisopa\SasisopaConsulta;

use App\Services\ModuloService;
use App\Services\ReporteRequisitosLegalesService;
use App\Models\Sasisopa\CapacitacionExterna;
use App\Models\Sasisopa\ComunicacionIE;
use App\Models\Sasisopa\QuejasSugerencia;
use App\Models\Sasisopa\ProgramaAnualMantenimiento;
use App\Models\Sasisopa\RequisicionObra;
use App\Models\Sasisopa\ProgramaAnualSimulacros;
use App\Models\Sasisopa\InformeRevisionResultado;
use App\Models\Sasisopa\AtencionHallazgo;
use App\Models\Sasisopa\AuditoriaInterna;
use App\Models\Sasisopa\AuditoriaExterna;
use App\Models\Sasisopa\InvestigacionIncidenteAccidente;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteNo;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteGrupo;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteTercerautorizado;
use App\Models\Sasisopa\RevisionResultados;
use App\Models\Sasisopa\EvaluacionDesempeno;
use App\Models\Sasisopa\ImplementacionSasisopa;

use Dompdf\Dompdf;
use Dompdf\Options;

//--------------------------------------------
//------------------------- REPORTE SASISOPA
use App\Models\Sasisopa\PoliticaListaComprobacion;
use App\Models\Sasisopa\ListaAsistencia;
//------------------------- REPORTE SASISOPA
//--------------------------------------------

use Illuminate\Database\Capsule\Manager as Capsule;

class SasisopaController extends BaseController
{

    protected string $modulo = 'sasisopa';

    public function index()
    {

        ModuleStationService::resetAllContexts();

        $title = 'SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());

        $sasisopa = Sasisopa::all();

        $data = [
            'title' => $title,
            'elementos' => $sasisopa,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sasisopa/index.actions.init.js?v=' . time(),
            ],
            'help' => false

        ];

        View::render('sasisopa/index', $data, 'sasisopa');
    }

    public function reporte($fechainicio, $fechatermino)
    {

        $title = 'REPORTE SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());
        $estacion = Estacion::find($this->estacionId());

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'fechaInicio' => $fechainicio,
            'fechaTermino' => $fechatermino,
            'organigrama' => asset('/images/organigramas/' . $estacion->organigrama),
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sasisopa/reporte.actions.init.js?v=' . time(),
            ],
            'help' => false

        ];

        View::render('sasisopa/reporte', $data, 'sasisopa');
    }

    public function elemento1()
    {
        $inicio = $_GET['inicio'];
        $fin    = $_GET['fin'];

        $listas = PoliticaListaComprobacion::query()
            ->where('id_estacion', $this->estacionId())
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                ];
            });

        $asistencias = ListaAsistencia::query()
            ->where('id_estacion', $this->estacionId())
            ->where('punto_sasisopa', 1)
            ->whereBetween('fecha', [$inicio, $fin])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                    'hora' => $item->hora?->format('g:i a'),
                    'estado' => $item->estado,
                ];
            });

        echo json_encode([
            'listas' => $listas,
            'asistencias' => $asistencias,
        ]);
    }

    public function elemento2()
    {
        header('Content-Type: application/json');

        $inicio = $_GET['inicio'];
        $fin    = $_GET['fin'];

        $analisis = AnalisisRiesgo::query()
            ->where('id_estacion', $this->estacionId())
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                    'descripcion' => $item->descripcion,
                    'documento' => $item->documento,
                ];
            });

        $asistencias = ListaAsistencia::query()
            ->where('id_estacion', $this->estacionId())
            ->where('punto_sasisopa', 2)
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                    'hora' => $item->hora?->format('g:i a'),
                    'estado' => $item->estado,
                ];
            });

        echo json_encode([
            'analisis'    => $analisis,
            'asistencias' => $asistencias
        ]);
    }

    public function elemento3()
    {

        header('Content-Type: application/json; charset=utf-8');

        $service = new ReporteRequisitosLegalesService();


        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $asistencias = ListaAsistencia::query()
            ->where('id_estacion', $this->estacionId())
            ->where('punto_sasisopa', 3)
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                    'hora' => $item->hora?->format('g:i a'),
                    'estado' => $item->estado,
                ];
            });





        if (!$inicio || !$fin) {

            echo json_encode([
                'success' => false,
                'message' => 'Fechas no recibidas'
            ]);

            exit;
        }


        $estacion = $this->estacionId();


        echo json_encode([

            'success' => true,

            'municipal' => $service->obtenerNivel(
                'Municipal',
                $estacion,
                $inicio,
                $fin
            ),


            'estatal' => $service->obtenerNivel(
                'Estatal',
                $estacion,
                $inicio,
                $fin
            ),


            'federal' => $service->obtenerNivel(
                'Federal',
                $estacion,
                $inicio,
                $fin
            ),


            'varios' => $service->obtenerNivel(
                'Varios',
                $estacion,
                $inicio,
                $fin
            ),

            'asistencias' => $asistencias

        ]);


        exit;
    }

    public function calendarioRequisitosLegales()
    {

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        $service = new ReporteRequisitosLegalesService();
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $niveles = $service->obtenerTodosLosNiveles(
            $this->estacionId(),
            $inicio,
            $fin
        );

        $html = '';
        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <title>Calendario anual de renovacion de Requisitos Legales</title>
            <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
            </head>
            <body>

            <table class="table table-bordered" style="font-size: .9em;">
            <tbody>
            <tr>

            <td class="align-middle text-center">
            <img src="' . $logo . '" style="width: 150px;">
            </td>
            <td colspan="2" class="align-middle text-center">
            <b>Calendario anual de renovacion de Requisitos Legales</b>
            </td>
            <td class="align-middle text-center">
            <b>Fo.ADMONGAS.004</b>
            </td>

            </tr>
            //------------------------------------------------------------------
            <tr>
            <td class="align-middle text-center">
            Realizado por:<br> Nelly Estrada Garcia
            </td>
            <td class="align-middle text-center">
            Revisado por:<br> Eduardo Galicia Flores
            </td>
            <td class="align-middle text-center">
            Autorizado por:<br> ' . $apoderado . '
            </td>
            <td class="align-middle text-center">
            Fecha de aprobacion:<br>  01-oct-18
            </td>
            </tr>
            </tbody>
            </table>

            <table class="table table-bordered table-sm mt-4" style="font-size: .75em;" width="100%">

            <tr class="table-active">
            <td class="text-center align-middle"><b>Dependencia</b></td>
            <td class="text-center align-middle"><b>Permiso</b></td>
            <td class="text-center align-middle"><b>Vigencia</b></td>
            <td class="text-center align-middle"><b>Fecha emisión</b></td>
            <td class="text-center align-middle"><b>Fecha vencimiento</b></td>
            <td class="text-center align-middle"><b>Renovación</b></td>
            </tr>';

        $html .= $this->renderNivel('Municipal', $niveles['Municipal']);
        $html .= $this->renderNivel('Estatal', $niveles['Estatal']);
        $html .= $this->renderNivel('Federal', $niveles['Federal']);
        $html .= $this->renderNivel('Varios', $niveles['Varios']);

        $html .= '</table>

            </body>
            </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Calendario-anual-renovacion-Requisitos-Legales.pdf", ["Attachment" => true]);
    }

    private function renderNivel(string $titulo, array $data): string
    {
        $html = '';

        $html .= '
    <tr>
        <td class="text-center table-info" colspan="6">
            <b>Nivel de Gobierno ' . $titulo . '</b>
        </td>
    </tr>';

        foreach ($data['items'] as $row) {

            $html .= '
        <tr>
            <td>' . $row['dependencia'] . '</td>
            <td><b>' . $row['permiso'] . '</b></td>
            <td>' . $row['vigencia'] . '</td>
            <td>' . $row['fecha_emision'] . '</td>
            <td>' . $row['fecha_vencimiento'] . '</td>
            <td>' . $row['renovacion'] . '</td>
        </tr>';
        }

        return $html;
    }

    public function elemento5()
    {
        $inicio = $_GET['inicio'];
        $fin    = $_GET['fin'];

        $asistencias = ListaAsistencia::query()
            ->where('id_estacion', $this->estacionId())
            ->where('punto_sasisopa', 5)
            ->whereBetween('fecha', [$inicio, $fin])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha?->format('Y-m-d')),
                    'hora' => $item->hora?->format('g:i a'),
                    'estado' => $item->estado,
                ];
            });

        echo json_encode([
            'asistencias' => $asistencias,
        ]);
    }

    public function elemento6()
    {
        $inicio = $_GET['inicio'];
        $fin    = $_GET['fin'];

        $capacitaciones = CapacitacionExterna::query()
            ->where('id_estacion', $this->estacionId())
            ->whereBetween('fecha_programada', [$inicio, $fin])
            ->orderBy('fecha_programada')
            ->get()
            ->map(function ($item) {

                return [

                    'id' => $item->id,

                    'curso' => $item->curso,

                    'fecha_programada' => formatearFecha(
                        $item->fecha_programada?->format('Y-m-d')
                    ),

                    'duracion' => trim(
                        $item->duracion . ' ' . $item->duraciondetalle
                    ),

                    'instructor' => $item->instructor,

                    'fecha_real' => (
                        empty($item->fecha_real) ||
                        $item->fecha_real == '0000-00-00'
                    )
                        ? null
                        : formatearFecha(
                            $item->fecha_real?->format('Y-m-d')
                        )

                ];
            });

        echo json_encode($capacitaciones);
        exit;
    }

    public function elemento7()
    {
        $inicio = $_GET['inicio'];
        $fin    = $_GET['fin'];

        $comunicaciones = ComunicacionIE::query()
            ->with('encargado')
            ->where('id_estacion', $this->estacionId())
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {

                return [

                    'id' => $item->id,

                    'numero' => $item->no_comunicacion,

                    'fecha' => formatearFecha(
                        $item->fecha?->format('Y-m-d')
                    ),

                    'tema' => mb_strimwidth(
                        $item->tema,
                        0,
                        60,
                        '...'
                    ),

                    'encargado' => $item->encargado?->nombre ?? 'S/I',

                    'tipo' => $item->tipo_comunicacion,

                    'material' => $item->material,

                    'seguimiento' => $item->seguimiento

                ];
            });

        $quejas = QuejasSugerencia::query()
            ->where('id_estacion', $this->estacionId())
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderByDesc('fecha')
            ->get()
            ->values()
            ->map(function ($item, $index) {

                return [

                    'id' => $item->id,

                    'numero' => $index + 1,

                    'fecha' => formatearFecha(
                        $item->fecha?->format('Y-m-d')
                    )

                ];
            });

        echo json_encode([

            'comunicaciones' => $comunicaciones,

            'quejas' => $quejas

        ]);

        exit;
    }

    public function elemento10()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $year = date('Y', strtotime($fin));

        $programa = ProgramaAnualMantenimiento::query()
            ->where('id_estacion', $this->estacionId())
            ->where('year', $year)
            ->first();

        echo json_encode([
            'year' => $year,
            'programa' => $programa
        ]);

        exit;
    }

    public function elemento12()
    {

        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $registros = RequisicionObra::with([
            'usuario:id,nombre',
            'formato12:id,id_requisicion',
            'formato14:id,id_requisicion,archivo',
            'formato15:id,id_requisicion',
            'cartaResponsiva:id,id_requisicion'
        ])
            ->where('id_estacion', $this->estacionId())
            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha', [$inicio, $fin]);
            })
            ->orderByDesc('fecha')
            ->get();

        echo json_encode([
            'success' => true,
            'data' => $registros->map(function ($item) {

                return [

                    'id' => $item->id,

                    'folio' => str_pad($item->no_folio, 2, '0', STR_PAD_LEFT),

                    'fecha' => formatearFecha(optional($item->fecha)->format('Y-m-d')),

                    'solicitante' => $item->usuario->nombre ?? 'S/I',

                    'formato12' => [
                        'existe' => $item->formato12 !== null,
                        'id' => $item->id ?? null
                    ],

                    'formato13' => [
                        'id' => $item->id
                    ],

                    'formato14' => [
                        'existe' => $item->formato14 !== null,
                        'archivo' => $item->formato14->archivo ?? null
                    ],

                    'formato15' => [
                        'existe' => $item->formato15 !== null,
                        'id' => $item->id ?? null
                    ],

                    'carta' => [
                        'existe' => $item->cartaResponsiva !== null,
                        'id' => $item->id ?? null
                    ]

                ];
            })
        ]);
    }

    public function elemento13()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $registros = ProgramaAnualSimulacros::with([
            'personal',
            'resumen',
            'ultimaEvaluacion'
        ])
            ->where('id_estacion', $this->estacionId())
            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha', [$inicio, $fin]);
            })
            ->orderByDesc('fecha')
            ->get();

        echo json_encode([
            'success' => true,
            'data' => $registros->map(function ($item) {

                return [

                    'id' => $item->id,

                    'nombre_simulacro' => $item->nombre_simulacro,

                    'periodicidad' => $item->periodicidad,

                    'fecha' => $item->fecha
                        ? formatearFecha($item->fecha->format('Y-m-d'))
                        : 'S/I',

                    'personal' => [
                        'total' => $item->personal->count(),
                        'texto' => $item->personal->count() == 0
                            ? 'No se encontró personal'
                            : $item->personal->count() . ' personas'
                    ],

                    'resumen' => $item->resumen->resumen ?? 'S/I',

                    'evaluacion' => [

                        'existe' => !empty($item->ultimaEvaluacion?->archivo),

                        'archivo' => $item->ultimaEvaluacion->archivo ?? null

                    ]

                ];
            })
        ]);
    }

    public function elemento14()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $year = $fin
            ? date('Y', strtotime($fin))
            : date('Y');

        $informes = InformeRevisionResultado::query()

            ->where('id_estacion', $this->estacionId())

            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {

                $q->whereBetween('fecha', [$inicio, $fin]);
            })

            ->orderByDesc('fecha')

            ->get();

        $hallazgos = AtencionHallazgo::query()

            ->where('id_estacion', $this->estacionId())

            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {

                $q->whereBetween(
                    'fecha_auditoria',
                    [$inicio, $fin]
                );
            })

            ->orderByDesc('id')

            ->get();

        echo json_encode([

            'success' => true,

            'year' => $year,

            'informes' => $informes->map(function ($item) {

                return [

                    'id' => $item->id,

                    'fecha' => $item->fecha
                        ? formatearFecha($item->fecha->format('Y-m-d'))
                        : 'S/I',

                    'archivo' => $item->archivo

                ];
            }),

            'hallazgos' => $hallazgos->map(function ($item) {

                return [

                    'id' => $item->id,

                    'folio' => $item->folio,

                    'fecha_auditoria' => $item->fecha_auditoria
                        ? formatearFecha($item->fecha_auditoria->format('Y-m-d'))
                        : 'S/I',

                    'no_control' => $item->no_control,

                    'tipo_auditoria' => $item->tipo_auditoria

                ];
            })

        ]);
    }

    public function elemento15()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $interna = AuditoriaInterna::with('formatos')
            ->where('id_estacion', $this->estacionId())
            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechacreacion', [$inicio, $fin]);
            })
            ->orderByDesc('id')
            ->get();

        $externa = AuditoriaExterna::with([
            'formatos',
            'asea'
        ])
            ->where('id_estacion', $this->estacionId())
            ->when($inicio && $fin, function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechacreacion', [$inicio, $fin]);
            })
            ->orderByDesc('id')
            ->get();

        echo json_encode([

            'success' => true,

            'interna' => $interna->map(function ($item) {

                return [

                    'id' => $item->id,

                    'fecha' => formatearFecha(
                        $item->fechacreacion->format('Y-m-d')
                    ),

                    'auditor' => $item->auditor,

                    'formato024' => optional(
                        $item->formatos
                            ->firstWhere('formato', 'formato024')
                    )->archivo,

                    'formato025' => optional(
                        $item->formatos
                            ->firstWhere('formato', 'formato025')
                    )->archivo

                ];
            }),

            'externa' => $externa->map(function ($item) {

                return [

                    'id' => $item->id,

                    'fecha' => formatearFecha(
                        $item->fechacreacion->format('Y-m-d')
                    ),

                    'prestador' => $item->prestador_servicio,

                    'formato024' => optional(
                        $item->formatos
                            ->firstWhere('formato', 'formato024')
                    )->archivo,

                    'formato025' => optional(
                        $item->formatos
                            ->firstWhere('formato', 'formato025')
                    )->archivo,

                    'asea' => $item->asea->map(function ($a) {

                        return [

                            'id' => $a->id,

                            'fecha' => formatearFecha(
                                $a->fechacreacion->format('Y-m-d')
                            ),

                            'comentario' => $a->comentario,

                            'archivo' => $a->archivo

                        ];
                    })

                ];
            })

        ]);
    }

    public function elemento16()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $investigaciones = InvestigacionIncidenteAccidente::with([
            'usuario.puesto',
            'formatos',
            'grupos',
            'terceroAutorizado'
        ])

            ->where(
                'id_estacion',
                $this->estacionId()
            )

            ->when(
                $inicio && $fin,
                fn($q) => $q->whereBetween(
                    'fechacreacion',
                    [$inicio, $fin]
                )
            )

            ->orderByDesc('id')

            ->get();

        $sinAccidentes = InvestigacionIncidenteAccidenteNo::with(
            'usuario:id,nombre'
        )

            ->where(
                'id_estacion',
                $this->estacionId()
            )

            ->when(
                $inicio && $fin,
                fn($q) => $q->whereBetween(
                    'fecha',
                    [$inicio, $fin]
                )
            )

            ->orderByDesc('id')

            ->get();

        echo json_encode([

            'success' => true,

            'investigaciones' => $investigaciones->values()->map(

                function ($item, $index) {

                    $formato026 = $item
                        ->formatos
                        ->last();

                    return [

                        'id' => $item->id,

                        'numero' => $index + 1,

                        'fecha' => $item->fechacreacion
                            ? formatearFecha(
                                $item->fechacreacion
                                    ->format('Y-m-d')
                            )
                            : 'S/I',

                        'nombre' => $item
                            ->usuario
                            ?->nombre
                            ?? 'S/I',

                        'puesto' => $item
                            ->usuario
                            ?->puesto
                            ?->tipo_puesto
                            ?? 'S/I',

                        'descripcion' => $item->descripcion,

                        'tipo_evento' => $item->tipo_evento,

                        'muertes' => ($item->muertes == 1) ? 'SI' : 'No',

                        'grupo' => [

                            'total' => $item
                                ->grupos
                                ->count()

                        ],

                        'formato026' =>

                        $formato026
                            ? $formato026->archivo
                            : null,

                        'tercer_autorizado' =>

                        $item->tercer_autorizado == 1

                    ];
                }

            ),

            'sin_accidentes' => $sinAccidentes->map(

                function ($item) {

                    return [

                        'id' => $item->id,

                        'fecha' => formatearFecha(
                            $item->fecha->format('Y-m-d')
                        ),

                        'usuario' => $item
                            ->usuario
                            ?->nombre
                            ?? 'S/I',

                        'estatus' => $item->estatus

                    ];
                }

            )

        ]);
    }

    public function grupoInterdisciplinario(
        int $id
    ) {

        $grupo = InvestigacionIncidenteAccidenteGrupo::query()

            ->where(
                'id_investigacion',
                $id
            )

            ->orderBy('id')

            ->get();

        echo json_encode([

            'success' => true,

            'data' => $grupo->map(

                fn($item) => [

                    'id' => $item->id,

                    'nombre' => $item->nombre,

                    'puesto' => $item->puesto,

                    'especialidad' => $item->especialidad

                ]

            )

        ]);
    }

    public function tercerAutorizado(
        int $id
    ) {

        $tercero = InvestigacionIncidenteAccidenteTercerautorizado::query()

            ->where(
                'id_investigacion',
                $id
            )

            ->first();

        echo json_encode([

            'success' => true,

            'data' => [
                'id' => $tercero?->id,
                'nombre' => $tercero?->nombre,

                'numero' => $tercero?->numero,

                'lider' => $tercero?->lider,

                'fecha' =>

                $tercero?->fecha
                    ? formatearFecha(
                        $tercero
                            ->fecha
                            ->format('Y-m-d')
                    )
                    : null,

                'archivo' => $tercero?->archivo

            ]

        ]);
    }

    public function elemento17()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $consulta = RevisionResultados::with('usuario')
            ->where(
                'id_estacion',
                $this->estacionId()
            );

        if (!empty($inicio) && !empty($fin)) {

            $consulta->whereBetween(
                'fecha_hora',
                [
                    $inicio . ' 00:00:00',
                    $fin . ' 23:59:59'
                ]
            );
        }

        $registros = $consulta
            ->orderByDesc('fecha_hora')
            ->get();

        echo json_encode([

            'success' => true,

            'data' => $registros->values()->map(

                fn($item) => [

                    'id' => $item->id,

                    'fecha' => $item->fecha_hora
                        ->translatedFormat('d \d\e F \d\e\l Y'),

                    'nombre' => $item->usuario?->nombre,

                    'archivo' => $item->archivo

                ]

            )

        ]);
    }

    public function elemento18()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $evaluaciones = EvaluacionDesempeno::with('usuario')
            ->where(
                'id_estacion',
                $this->estacionId()
            );

        $implementaciones = ImplementacionSasisopa::with('usuario')
            ->where(
                'id_estacion',
                $this->estacionId()
            );

        if (!empty($inicio) && !empty($fin)) {

            $evaluaciones->whereBetween(
                'fecha_hora',
                [
                    $inicio . ' 00:00:00',
                    $fin . ' 23:59:59'
                ]
            );

            $implementaciones->whereBetween(
                'fecha_hora',
                [
                    $inicio . ' 00:00:00',
                    $fin . ' 23:59:59'
                ]
            );
        }

        echo json_encode([

            'success' => true,

            'evaluaciones' => $evaluaciones
                ->orderByDesc('fecha_hora')
                ->get()
                ->values()
                ->map(

                    fn($item, $i) => [

                        'numero' => $i + 1,

                        'id' => $item->id,

                        'fecha' => $item->fecha_hora
                            ->translatedFormat('d \d\e F \d\e\l Y'),

                        'nombre' => $item->usuario?->nombre,

                        'archivo' => $item->archivo

                    ]

                ),

            'implementaciones' => $implementaciones
                ->orderByDesc('fecha_hora')
                ->get()
                ->values()
                ->map(

                    fn($item, $i) => [

                        'numero' => $i + 1,

                        'id' => $item->id,

                        'fecha' => $item->fecha_hora
                            ->translatedFormat('d \d\e F \d\e\l Y'),

                        'nombre' => $item->usuario?->nombre

                    ]

                )

        ]);
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------ 2 Identificacion de peligros y aspectos ambientales -------------------

    public function identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales()
    {

        $title = '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES';
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
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sasisopa/analisisriesgo.datatable.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/sasisopa/analisisriesgo.actions.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', $data, 'sasisopa');
    }

    public function datatableListaAnalisisRiesgo()
    {

        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = AnalisisRiesgo::where('id_estacion', $this->estacionId())
            ->orderBy('fecha', 'desc')
            ->get();

        echo json_encode([
            "data" => $data,
            "permisos" => [
                "descargar" => $permisoDescargar
            ]
        ]);

        exit;
    }

    public function pdfAspectosAmbientales()
    {

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $html = '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Identificación y evaluación de Aspectos e Impactos Ambientales.</title>
        <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered" style="font-size: .9em;">
        <tbody>
        <tr>

        <td class="align-middle text-center">
        <img src="' . $logo . '" style="width: 150px;">
        </td>
        <td colspan="2" class="align-middle text-center">
        <b>Identificación y evaluación de Aspectos e Impactos Ambientales.</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.002</b>
        </td>

        </tr>
        //------------------------------------------------------------------
        <tr>
        <td class="align-middle text-center">
        Realizado por:<br> Nelly Estrada Garcia
        </td>
        <td class="align-middle text-center">
        Revisado por:<br> Eduardo Galicia Flores
        </td>
        <td class="align-middle text-center">
        Autorizado por:<br> ' . $apoderado . '
        </td>
        <td class="align-middle text-center">
        Fecha de aprobacion:<br>  01-oct-18
        </td>
        </tr>
                    

        </tbody>
        </table>

    <table class="table table-sm table-bordered text-center" style="font-size: .7em;">
    <tbody>
      <thead>
        <tr>
            <th colspan="10" class="align-middle text-center table-success">ETAPA: OPERACIÓN Y MANTENIMIENTO</th>
        </tr>
        <tr>
            <th class="align-middle">Id</th>
            <th class="align-middle">Proceso o Actividad</th>
            <th class="align-middle">Tipo</th>
            <th class="align-middle">Entradas</th>
            <th class="align-middle">Salidas</th>
            <th class="align-middle">Impacto ambiental</th>
            <th class="align-middle">Naturaleza</th>
            <th class="align-middle">Importancia</th>
            <th class="align-middle">Magnitud</th>
            <th class="align-middle">Resultado</th>
        </tr>
        </thead>
  
        <tr>
            <td class="align-middle">1</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">9</td>
            <td class="align-middle">-18</td>
        </tr>
        <tr>
            <td class="align-middle">2</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Energía</td>
            <td class="align-middle">Ruido</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-2</td>
        </tr>
        <tr>
            <td class="align-middle">3</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Energía</td>
            <td class="align-middle">Vibraciones</td>
            <td class="align-middle">Salud</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">1</td>
            <td class="align-middle">-2</td>
        </tr>
        <tr>
            <td class="align-middle">4</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Sustancias químicas (anticongelante, lubricantes)</td>
            <td class="align-middle">Derrames por gote </td>
            <td class="align-middle">suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-6</td>
        </tr>
        <tr>
            <td class="align-middle">5</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustibl </td>
            <td class="align-middle">Derrames por goteo</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-12</td>
        </tr>
        <tr>
            <td class="align-middle">6</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel y otros</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">7</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapo</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">8</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (Lavado parabrisas-servicio)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-8</td>
        </tr>
        <tr>
            <td class="align-middle">9</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">6</td>
            <td class="align-middle">6</td>
            <td class="align-middle">-36</td>
        </tr>
        <tr>
            <td class="align-middle">10</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">5</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-25</td>
        </tr>
        <tr>
            <td class="align-middle">11</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">12</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Papel</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">13</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">6</td>
            <td class="align-middle">7</td>
            <td class="align-middle">-42</td>
        </tr>
        <tr>
            <td class="align-middle">14</td>
            <td class="align-middle">Almacenamiento de combustibles</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">15</td>
            <td class="align-middle">Almacenamiento de combustibles</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">5</td>
            <td class="align-middle">7</td>
            <td class="align-middle">-35</td>
        </tr>
        <tr>
            <td class="align-middle">16</td>
            <td class="align-middle">Limpieza en área de descarga</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">17</td>
            <td class="align-middle">Limpieza en el área de despacho de combustible al publico</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">18</td>
            <td class="align-middle">Limpieza en el área de despacho de combustible al publico</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">19</td>
            <td class="align-middle">Limpieza de trampas de combustible</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">6</td>
            <td class="align-middle">-24</td>
        </tr>
        <tr>
            <td class="align-middle">20</td>
            <td class="align-middle">Limpieza en tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">21</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con jabón)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">22</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">23</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">24</td>
            <td class="align-middle">Mantenimiento de dispensarios</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">25</td>
            <td class="align-middle">Mantenimiento de tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">26</td>
            <td class="align-middle">Venta de productos</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Envases de producto (aceites, anticongelantes, aditivos)</td>
            <td class="align-middle">Residuos peligroso </td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">27</td>
            <td class="align-middle">Operación de la tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel, plástico, cartón</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">28</td>
            <td class="align-middle">Pintado de instalaciones</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        </tbody>
    </table>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Identificación-evaluación-Aspectos-Impactos-Ambientales.pdf", ["Attachment" => true]);
    }

    public function pdfRiesgosPeligros()
    {

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $html = '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</title>
        <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered" style="font-size: .9em;">
        <tbody>
        <tr>

        <td class="align-middle text-center">
        <img src="' . $logo . '" style="width: 150px;">
        </td>
        <td colspan="2" class="align-middle text-center">
        <b>Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.003</b>
        </td>

        </tr>
        //------------------------------------------------------------------
        <tr>
        <td class="align-middle text-center">
        Realizado por:<br> Nelly Estrada Garcia
        </td>
        <td class="align-middle text-center">
        Revisado por:<br> Eduardo Galicia Flores
        </td>
        <td class="align-middle text-center">
        Autorizado por:<br> ' . $apoderado . '
        </td>
        <td class="align-middle text-center">
        Fecha de aprobacion:<br>  01-oct-18
        </td>
        </tr>
                
        </tbody>
        </table>

            <table class="table table-sm table-bordered text-center" style="font-size: .6em;">
            <tbody>
                <tr>
                    <td class="align-middle text-center p-0" rowspan="2"><b>Id</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>PROCESO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>TAREA</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>PELIGRO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>RIESGO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>CONSECUENCIAS</b></td>
                    <td class="align-middle text-center" colspan="5"><b>PROBABILIDAD</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>ÍNDICE<br>DE<br>SEVERIDAD</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>RIE<br>SGO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>T<br>I<br>P<br>O</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>¿Riesgo<br>Signif<br>icativo?</b></td>
                </tr>
                <tr>
                    <td class="align-middle text-center"><b>Índice de personas expuestas (A)</b></td>
                    <td class="align-middle text-center"><b>Índice de procedimientos existentes (B)</b></td>
                    <td class="align-middle text-center"><b>Índice de capacitación (C)</b></td>
                    <td class="align-middle text-center"><b>Índice de exposición al riesgo (D)</b></td>
                    <td class="align-middle text-center"><b>PROBAB<br>ILIDAD</b></td></td>
                </tr>
                <tr>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Mala colocación de la manguera</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">12</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Condiciones climáticas</td>
                    <td class="align-middle text-center">Exposición prolongada</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >4</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Colisión con otro vehÍculo</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">10</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">5</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Despacho a bidones</td>
                    <td class="align-middle text-center">Sobrellenado del bidón</td>
                    <td class="align-middle text-center">Derrame de combustible</td>
                    <td class="align-middle text-center">Incendio en el área</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">16</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >6</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Atropellamiento de una persona</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">7</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">14</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >7</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Colisión con las instalaciones</td>
                    <td class="align-middle text-center">Incidentes materiales</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">8</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Equipos en mal estado (mangueras, pistola de suministro, conexiones, sistema de recuperación de gases etc.)</td>
                    <td class="align-middle text-center">Fuga de combustible</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >9</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Equipos de suministro en mal estado</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">4</td>
                    <td class="align-middle text-center table-secondary">Trivial</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >10</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">vehÍculos de gran tonelaje</td>
                    <td class="align-middle text-center">Exposición al ruido</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >11</td>
                    <td class="align-middle text-center">Descarga del auto tanque</td>
                    <td class="align-middle text-center">Conexión de manguera al tanque fijo</td>
                    <td class="align-middle text-center">Mala colocación de la manguera</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame mayor de combustible</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning font-weight-bold">15</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">12</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Limpieza en drenajes, registros y trampa de combustibles</td>
                    <td class="align-middle text-center">Acumulación de combustibles</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >13</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Prueba de hermeticidad a tanques y tuberÍas</td>
                    <td class="align-middle text-center">Falta de purgado de tanques</td>
                    <td class="align-middle text-center">Acumulación de vapores</td>
                    <td class="align-middle text-center">posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">14</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Revisar la calibración de medidores mediante la jarra patrón</td>
                    <td class="align-middle text-center">Derrame de combustible</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">15</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Pintar la fachada</td>
                    <td class="align-middle text-center">Mala colocación de barandillas, barras intermedias y plintos</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">16</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Cambio de lámparas o focos</td>
                    <td class="align-middle text-center">Mala colocación de la escalera</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >17</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Revisión de instalaciones eléctricas</td>
                    <td class="align-middle text-center">Falta de des energización de equipos</td>
                    <td class="align-middle text-center">Generación de carga electricidad estática</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">18</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Pintado delimitación de áreas de despacho, patios, oficinas</td>
                    <td class="align-middle text-center">Falta de acordonamiento del área</td>
                    <td class="align-middle text-center">Atropellamiento de una persona</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">10</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">19</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Colocación de publicidad</td>
                    <td class="align-middle text-center">Escalera mal colocada</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">20</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Suelo mojado</td>
                    <td class="align-middle text-center">CaÍdas, resbalones</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">21</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Acumulación en suelo de vertidos de aceites, carburantes, lÍquidos de frenos y similares</td>
                    <td class="align-middle text-center">CaÍdas, tropiezos</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >22</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Obstáculos, objetos abandonados o mal situados</td>
                    <td class="align-middle text-center">CaÍdas, tropiezos</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">23</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Medición del volumen de los tanques de almacenamiento</td>
                    <td class="align-middle text-center">Presencia de vapores de combustibles</td>
                    <td class="align-middle text-center">Contacto con vapores de combustibles</td>
                    <td class="align-middle text-center">Intoxicación</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >24</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza de tanques de almacenamiento</td>
                    <td class="align-middle text-center">Presencia de combustibles en el tanque</td>
                    <td class="align-middle text-center">Contacto con vapores de combustibles</td>
                    <td class="align-middle text-center">Intoxicación</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
            </tbody>
        </table>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Identificación-evaluación-Riesgos-Peligros-registrar-análisis.pdf", ["Attachment" => true]);
    }

    public function anexosAnalisisRiesgo($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $analisis = AnalisisRiesgo::find($id);

            if (!$analisis) {
                throw new \Exception('No encontrado');
            }

            $anexos = AnalisisRiesgoAnexo::where('id_analisis', $id)->get();

            echo json_encode([
                'success' => true,
                'data' => [
                    'fecha' => formatearFecha($analisis->fecha),
                    'descripcion' => $analisis->descripcion,
                    'anexos' => $anexos
                ]
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------- 5 Funciones, responsabilidades y auditoria ---------------------------


    public function funcionesResponsabilidadesAutoridad()
    {

        $title = '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $estacion = Estacion::find($this->estacionId());

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'organigrama' => asset('/images/organigramas/' . $estacion->organigrama),
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),
                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),
                '/js/sasisopa/listarepresentantetecnico.datatable.init.js?v=' . time(),
                '/js/sasisopa/representantetecnico.action.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sasisopa/funciones-responsabilidades-autoridad', $data, 'sasisopa');
    }

    public function datatableListaRepresentanteTecnico()
    {

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = RepresentanteTecnico::where('id_estacion', $this->estacionId())
            ->orderBy('fecha')
            ->get();

        echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);

        exit;
    }

    public function createRepresentanteTecnico()
    {

        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }


        $nombre = sanitize_input($_POST['nombre'] ?? null, 'string');
        $fecha = sanitize_input($_POST['fecha'] ?? null, 'string');
        $file  = $_FILES['pdf'] ?? null;

        if (!$nombre || !$fecha) {
            echo json_encode([
                'success' => false,
                'message' => 'Campos obligatorios'
            ]);
            exit;
        }

        // CONFIG RUTA
        $carpeta = __DIR__ . '../../../public/uploads/archivos/representante-tecnico/';

        // SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
        if (!file_exists($carpeta)) {
            mkdir_safe($carpeta, true);
        }

        $nombreArchivo = null;

        try {

            // SUBIR ARCHIVO (opcional)
            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                // Validar extensión
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // nombre único
                $nombreArchivo = uniqid('Formato_') . '.' . $extension;

                $rutaDestino = $carpeta . $nombreArchivo;

                if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                    throw new \Exception('No se pudo guardar el archivo');
                }
            }

            // GUARDAR EN BD
            RepresentanteTecnico::create([
                'id_estacion' => $this->estacionId(),
                'nom_representante'  => $nombre,
                'fecha'       => $fecha,
                'archivo'   => 'archivos/representante-tecnico/' . $nombreArchivo
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Representante técnico almacenado correctamente'
            ]);
        } catch (\Throwable $e) {

            // Si falla BD, borrar archivo
            if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
                unlink($carpeta . $nombreArchivo);
            }

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar representante técnico'
            ]);
        }

        exit;
    }

    public function deleteRepresentanteTecnico()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }

        try {

            // Buscar registro
            $reporte = RepresentanteTecnico::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

            // Ruta archivo
            $rutaBase = __DIR__ . '../../../public/uploads/';
            $rutaArchivo = $rutaBase . $reporte->archivo;

            // TRANSACCIÓN
            Capsule::beginTransaction();

            // Eliminar archivo si existe
            if ($reporte->archivo && file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }

            // Eliminar registro (puedes usar delete o estado = 0)
            $reporte->delete();

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Representante técnico eliminado correctamente'
            ]);
        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------
    //--------------------------------------------------------------

    public function  programaImplementacion()
    {

        $title = 'PROGRAMA DE IMPLEMENTACION';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());

        $sasisopa = Sasisopa::all();

        $data = [
            'title' => $title,
            'elementos' => $sasisopa,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => false

        ];

        View::render('sasisopa/programa-implementacion', $data, 'sasisopa');
    }

    //---------------------------------------
    //---------------- CONSULTA TU SASISOPA

    public function consultaSasisopa()
    {

        $title = 'CONSULTA TU SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());

        $sasisopa = Sasisopa::all();

        $data = [
            'title' => $title,
            'elementos' => $sasisopa,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sasisopa/consultasasisopa.actions.init.js?v=' . time(),
            ],
            'help' => false

        ];

        View::render('sasisopa/consulta-sasisopa', $data, 'sasisopa');
    }

    public function datatableConsulta()
    {
        header('Content-Type: application/json');

        $estacion = Estacion::find($this->estacionId());

        $data = SasisopaConsulta::where(
            'id_estacion',
            $this->estacionId()
        )
            ->orderByDesc('id')
            ->get()

            ->map(function ($item) {

                return [
                    'id' => $item->id,
                    'permisocre' => $item->estacion->permisocre,
                    'razonsocial' => $item->estacion->razonsocial,
                    'version' => $item->version,
                    'documento' => '/uploads/' . $item->documento
                ];
            });

        echo json_encode([
            'success' => true,
            'data' => $data,
        ]);
    }
    //---------------------------------------
    //---------------- CONSULTA TU SASISOPA


}
