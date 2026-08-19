<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\CalendarioActividad;
use App\Models\Sasisopa\CursoCalendario;

use App\Models\Sasisopa\PoliticaListaComprobacion;
use App\Models\Sasisopa\SeguimientoObjetivosMetas;
use App\Models\Sasisopa\SeguimientoObjetivosMetasDetalle;

use App\Models\Sasisopa\SeguimientoReporteIndicador;

use App\Models\Sasisopa\CapacitacionExterna;
use App\Models\Sasisopa\ComunicacionIE;
use App\Models\Sasisopa\ProgramaAnualSimulacros;
use App\Models\Sasisopa\ProtocoloEmergencias;
use App\Models\Sasisopa\AtencionHallazgo;
use App\Models\Sasisopa\RevisionResultados;
use App\Models\Sasisopa\EvaluacionDesempeno;
use App\Models\Sasisopa\ImplementacionSasisopa;
use App\Models\Sasisopa\ImplementacionSasisopaProcedimientos;

use App\Models\Sasisopa\SasisopaActividad;
use App\Models\Sasisopa\SasisopaEstacionActividad;

use App\Models\Sgm\CalendarioActividadSgm;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

class CalendarioController extends BaseController
{

    protected string $modulo = 'sasisopa';

    private const CRITERIOS_POLITICA = [
        'La política es adecuada a la naturaleza magnitud y actividades del proyecto',
        'La política incluye la seguridad operativa',
        'La política incluye la protección al medio ambiente',
        'Los trabajadores, la alta dirección, los clientes y los subcontratistas tienen conocimiento de la política',
        'La política se revisa periódicamente',
        'La política se compromete al control de los peligros e impactos ambientales',
        'La política considera la participación del personal',
    ];

    private const URLS = [

        '1|Fo.ADMONGAS.001' => '/sasisopa/politica',

        '2|DLES/SA/005' => '/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales',

        '3|Fo.ADMONGAS.004' => '/sasisopa/requisitos-legales',

        '4|Fo.ADMONGAS.006' => '/sasisopa/objetivos-metas-indicadores',
        '4|Fo.ADMONGAS.007' => '/sasisopa/objetivos-metas-indicadores',

        '6|Fo.ADMONGAS.008' => '/sasisopa/competencia-personal-capacitacion-entrenamiento/perfiles-personal',
        '6|FO.ADMONGAS.009' => '/sasisopa/competencia-personal-capacitacion-entrenamiento/capacitacion-externa',

        '7|Fo.ADMONGAS.010' => '/sasisopa/comunicacion-participacion-consulta',

        '10|DLES.ADMONGAS.001' => '/sasisopa/control-actividades-procesos',
        '10|DLES.ADMONGAS.002' => '/sasisopa/control-actividades-procesos/mantenimiento-preventivo',
        '10|DLES.ADMONGAS.003' => '/sasisopa/control-actividades-procesos/recepcion-descarga-producto',
        '10|Fo.ADMONGAS.011' => '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento',

        '11|DLES.ADMONGAS.001' => '/sasisopa/integridad-mecanica-aseguramiento',
        '11|DLES.ADMONGAS.002' => '/sasisopa/control-actividades-procesos/mantenimiento-preventivo',
        '11|Fo.ADMONGAS.011' => '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento',

        '12|DLES.ADMONGAS.001' => '/sasisopa/integridad-mecanica-aseguramiento',
        '12|Fo.ADMONGAS.012' => '/sasisopa/seguridad-contratistas',
        '12|Fo.ADMONGAS.013' => '/sasisopa/seguridad-contratistas',
        '12|FO.ADMONGAS.014' => '/sasisopa/seguridad-contratistas',
        '12|Fo.ADMONGAS.015' => '/sasisopa/seguridad-contratistas',

        '13|Fo.ADMONGAS.016' => '/sasisopa/preparacion-emergencias',
        '13|Fo.ADMONGAS.16ª' => '/sasisopa/preparacion-emergencias',
        '13|DLES/SA/005' => '/sasisopa/preparacion-emergencias',

        '14|Fo.ADMONGAS.017' => '/sasisopa/monitoreo-verificacion-evaluacion',
        '14|Fo.ADMONGAS.019' => '/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos',
        '14|Fo.ADMONGAS.020' => '/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos',
        '14|DLES.ADMONGAS.002' => '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos',
        '14|Fo.ADMONGAS.021' => '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales',
        '14|Fo.ADMONGAS.022' => '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales',
        '14|Fo.ADMONGAS.018' => '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos',

        '15|Fo.ADMONGAS.023' => '/sasisopa/auditorias/programa',
        '15|Fo.ADMONGAS.024' => '/sasisopa/auditorias/interna',
        '15|Fo.ADMONGAS.025' => '/sasisopa/auditorias/interna',

        '16|Fo.ADMONGAS.026' => '/sasisopa/investigacion-incidentes-accidentes',

        '17|Fo.ADMONGAS.027' => '/sasisopa/revision-resultados',

        '18|Fo.ADMONGAS.028 IED.' => '/sasisopa/informes-desempeno',
        '18|Fo.ADMONGAS.029' => '/sasisopa/informes-desempeno'

    ];

    public function index()
    {

        $title = 'Calendario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/fullcalendar/index.global.min.js',

                '/js/sasisopa/calendar-init.js?v=1.0.1'

            ]
        ];

        View::render('sasisopa/calendario', $data, 'sasisopa');
    }

    public function sgmIndex()
    {

        $title = 'Calendario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion('sgm');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => 'sgm',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/fullcalendar/index.global.min.js',
                '/js/sasisopa/calendar-init.js?v=1.0.1'
            ]
        ];

        View::render('sasisopa/calendario', $data, 'sgm');
    }

    //-----------------------------------------------------------------

    public function eventos()
    {
        header('Content-Type: application/json; charset=utf-8');

        $modulo = mb_strtoupper($_GET['modulo']);
        $inicio = $_GET['start'] ?? null;
        $fin    = $_GET['end'] ?? null;

        if (!$inicio || !$fin) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Parámetros start y end requeridos.'
            ]);

            exit;
        }

        // FullCalendar envía fecha con hora y zona horaria
        $inicio = date('Y-m-d', strtotime($inicio));
        $fin    = date('Y-m-d', strtotime($fin));

        $idEstacion = $this->estacionId();

        if ($modulo == 'SASISOPA') {

            $actividades = CalendarioActividad::query()
                ->with('actividad')
                ->where('id_estacion', $idEstacion)
                ->whereBetween('fecha_inicio', [$inicio, $fin])
                ->get();
        } else if ($modulo == 'SGM') {
            $actividades = CalendarioActividadSgm::query()
                ->with('actividad')
                ->where('id_estacion', $idEstacion)
                ->whereBetween('fecha_inicio', [$inicio, $fin])
                ->get();
        }

        $cursos = CursoCalendario::query()
            ->with('tema')
            ->where('id_estacion', $idEstacion)
            ->whereHas('tema', function ($query) use ($modulo) {
                $query->where('categoria', $modulo);
            })
            ->whereBetween('fecha_programada', [$inicio, $fin])
            ->get();

        $eventos = [];

        foreach ($actividades as $actividad) {

            $calendar = $actividad->estado ? 'Success' : 'Danger';

            $eventos[] = [
                'id'      => 'A' . $actividad->id,
                'title'   => $actividad->actividad?->actividad ?? 'Actividad',
                'start'   => $actividad->fecha_inicio->format('Y-m-d'),
                'allDay'  => true,
                'extendedProps' => [
                    'id'       => $actividad->id,
                    'tipo'     => 'actividad',
                    'nombre'   => $actividad->actividad?->actividad,
                    'calendar' => $calendar
                ]
            ];
        }

        foreach ($cursos as $curso) {

            if ($curso->estado == 0) {
                // Pendiente
                $calendar = 'Danger';
            } elseif ($curso->resultado < 60) {
                // Finalizado, pero no aprobó
                $calendar = 'Warning';
            } else {
                // Finalizado y aprobado
                $calendar = 'Success';
            }

            $eventos[] = [
                'id' => 'C' . $curso->id,
                'title' => $curso->tema?->titulo ?? 'Curso',
                'start' => $curso->fecha_programada->format('Y-m-d'),
                'allDay' => true,
                'extendedProps' => [
                    'id' => $curso->id,
                    'tipo' => 'curso',
                    'nombre' => $curso->tema?->titulo,
                    'resultado' => $curso->resultado,
                    'estado' => $curso->estado,
                    'calendar' => $calendar,
                ]
            ];
        }

        $totales = [

            'pendientes' => ($cursos->where('estado', 0)->count() + $actividades->where('estado', 0)->count()),

            'finalizados' => ($cursos->where('estado', 1)->count() + $actividades->where('estado', 1)->count()),

            'total' => ($cursos->count() + $actividades->count())
        ];

        echo json_encode([
            'eventos' => $eventos,
            'totales' => $totales
        ]);

        exit;
    }

    public function dia()
    {
        header('Content-Type: application/json; charset=utf-8');

        $modulo = mb_strtoupper($_GET['modulo']) ??  null;
        $fecha = $_GET['fecha'] ?? null;

        if (!$fecha) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Fecha requerida.'
            ]);

            exit;
        }

        $idEstacion = $this->estacionId();

        if ($modulo == 'SASISOPA') {

            $actividades = CalendarioActividad::query()
                ->with('actividad')
                ->where('id_estacion', $idEstacion)
                ->whereDate('fecha_inicio', $fecha)
                ->orderBy('fecha_inicio')
                ->get();
        } else if ($modulo == 'SGM') {
            $actividades = CalendarioActividadSgm::query()
                ->with('actividad')
                ->where('id_estacion', $idEstacion)
                ->whereDate('fecha_inicio', $fecha)
                ->orderBy('fecha_inicio')
                ->get();
        }

        $cursos = CursoCalendario::query()
            ->with(['tema', 'usuario'])
            ->where('id_estacion', $idEstacion)
            ->whereHas('tema', function ($query) use ($modulo) {
                $query->where('categoria', $modulo);
            })
            ->whereDate('fecha_programada', $fecha)
            ->get();

        $data = [];

        foreach ($actividades as $actividad) {

            $data[] = [
                'id'     => $actividad->id,
                'tipo'   => 'actividad',
                'nombre' => $actividad->actividad->actividad ?? 'Actividad',
                'estado' => $actividad->estado,
                'usuario' => null
            ];
        }

        foreach ($cursos as $curso) {

            $data[] = [
                'id'      => $curso->id,
                'tipo'    => 'curso',
                'nombre'  => $curso->tema->titulo ?? 'Curso',
                'estado'  => $curso->estado,
                'usuario' => $curso->usuario
                    ? $curso->usuario->nombre
                    : ''
            ];
        }

        echo json_encode($data);

        exit;
    }

    public function detalle()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id   = $_GET['id'] ?? null;
        $tipo = $_GET['tipo'] ?? null;

        if (!$id || !$tipo) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Parámetros inválidos.'
            ]);

            exit;
        }

        if ($tipo == 'actividad') {

            $actividad = CalendarioActividad::query()
                ->with('actividad')
                ->find($id);

            if (!$actividad) {

                http_response_code(404);

                echo json_encode([
                    'success' => false
                ]);

                exit;
            }

            echo json_encode([
                'id'             => $actividad->id,
                'tipo'           => 'actividad',
                'nombre'         => $actividad->actividad->actividad ?? '',
                'fecha'          => formatearFecha($actividad->fecha_inicio?->format('Y-m-d')),
                'fecha_termino'  => formatearFecha($actividad->fecha_termino?->format('Y-m-d')),
                'folio'          => $actividad->folio,
                'estado'         => $actividad->estado
            ]);

            exit;
        }

        $curso = CursoCalendario::query()
            ->with(['tema', 'usuario'])
            ->find($id);

        if (!$curso) {

            http_response_code(404);

            echo json_encode([
                'success' => false
            ]);

            exit;
        }

        echo json_encode([
            'id'            => $curso->id,
            'tipo'          => 'curso',
            'nombre'        => $curso->tema->titulo ?? '',
            'fecha'         => formatearFecha($curso->fecha_programada?->format('Y-m-d')),
            'estado'        => $curso->estado,
            'resultado'     => $curso->resultado,
            'participante'  => $curso->usuario->nombre ?? '',
            'observaciones' => $curso->observaciones
        ]);

        exit;
    }

    public function abrirActividad()
    {

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

        $id = (int) ($data['id'] ?? 0);

        $actividad = CalendarioActividad::with('actividad')->findOrFail($id);

        if ($actividad->estado == 0) {

            $url = $this->crearActividad($actividad);
        } else {

            $url = $this->urlDetalle(
                $actividad->actividad->id_sasisopa,
                $actividad->actividad->formato
            );
        }

        echo json_encode([
            'success' => true,
            'url' => $url
        ]);
    }

    private function crearActividad(CalendarioActividad $actividad): string
    {
        $sasisopa = $actividad->actividad->id_sasisopa;
        $formato = $actividad->actividad->formato;

        return match (true) {

            $sasisopa == 1 && $formato == 'Fo.ADMONGAS.001'
            => $this->crearPolitica($actividad),

            $sasisopa == 4 && $formato == 'Fo.ADMONGAS.006'
            => $this->crearSeguimientoObjetivos($actividad),

            $sasisopa == 4 && $formato == 'Fo.ADMONGAS.007'
            => $this->crearReporteIndicadores($actividad),

            $sasisopa == 6 && $formato == 'FO.ADMONGAS.009'
            => $this->crearCapacitacionExterna($actividad),

            $sasisopa == 7 && $formato == 'Fo.ADMONGAS.010'
            => $this->crearComunicacion($actividad),

            $sasisopa == 13 && $formato == 'Fo.ADMONGAS.16ª'
            => $this->crearProgramaAnualSimulacro($actividad),

            $sasisopa == 13 && $formato == 'DLES/SA/005'
            => $this->crearProtocoloEmergencia($actividad),

            $sasisopa == 14 && $formato == 'Fo.ADMONGAS.018'
            => $this->crearAtencionHallazgo($actividad),

            $sasisopa == 17 && $formato == 'Fo.ADMONGAS.027'
            => $this->crearRevisionResultados($actividad),

            $sasisopa == 18 && $formato == 'Fo.ADMONGAS.028 IED.'
            => $this->crearEvaluacionDesempeno($actividad),

            $sasisopa == 18 && $formato == 'Fo.ADMONGAS.029'
            => $this->crearImplementacionSasisopa($actividad),

            default => $this->crearSimple($actividad),
        };
    }

    private function crearSimple(CalendarioActividad $actividad): string
    {
        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today(),
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearPolitica(CalendarioActividad $actividad): string
    {


        Capsule::transaction(function () use ($actividad) {

            $lista = PoliticaListaComprobacion::create([
                'id_estacion' => $actividad->id_estacion,
                'id_usuario'  => $this->userId(),
                'fecha'       => $actividad->fecha_inicio
            ]);

            $detalles = [];

            foreach (self::CRITERIOS_POLITICA as $criterio) {

                $detalles[] = [
                    'criterio' => $criterio,
                    'resultado' => '',
                ];
            }

            $lista->detalles()->createMany($detalles);

            $actividad->update([
                'estado' => 1,
                'fecha_termino' => Carbon::today(),
            ]);
        });

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearSeguimientoObjetivos(CalendarioActividad $actividad): string
    {
        Capsule::transaction(function () use ($actividad) {

            $seguimiento = SeguimientoObjetivosMetas::create([
                'id_estacion' => $actividad->id_estacion,
                'id_usuario' => $this->userId()
            ]);

            $idSeguimiento = $seguimiento->id;

            $map = [
                'satisfaccion' => 'Satisfacción del cliente',
                'mantenimiento' => 'Mantenimiento',
                'capacitacion' => 'Capacitación',
                'quejas' => 'Quejas y sugerencias',
                'legislacion' => 'Cumplimiento de legislación'
            ];

            $insert = [];

            foreach ($map as $key => $nombre) {

                $item = $json[$key] ?? null;

                if (!$item) continue;

                $fecha = sanitize_input($item['fecha'] ?? '', 'string');
                $fechaAplicacion = sanitize_input($item['fecha_aplicacion'] ?? '', 'string');

                $insert[] = [
                    'id_seguimiento'       => $idSeguimiento,
                    'fecha'               => $fecha,
                    'objetivo_meta'       => $nombre,
                    'nivel_cumplimiento'  => $item['cumplimiento'] ?? '',
                    'medidas'             => $item['accion'] ?? '',
                    'fecha_aplicacion'    => $fechaAplicacion
                ];
            }

            SeguimientoObjetivosMetasDetalle::insert($insert);

            $actividad->update([
                'estado' => 1,
                'fecha_termino' => Carbon::today()
            ]);
        });

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearReporteIndicadores(CalendarioActividad $actividad): string
    {
        SeguimientoReporteIndicador::create([
            'id_estacion' => $actividad->id_estacion,
            'id_usuario' => $this->userId(),
            'fecha' => $actividad->fecha_inicio
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today()
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearCapacitacionExterna(CalendarioActividad $actividad): string
    {

        CapacitacionExterna::create([
            'id_estacion' => $this->estacionId(),
            'id_usuario' => $this->userId(),
            'curso' => '',
            'fecha_programada' => $actividad->fecha_inicio,
            'duracion' => '',
            'duraciondetalle' => '',
            'instructor' => '',
            'fecha_real' => null,
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today()
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearComunicacion(
        CalendarioActividad $actividad
    ): string {

        ComunicacionIE::create([
            'id_estacion' => $this->estacionId(),
            'no_comunicacion' => $this->folioRASCIE(),
            'fecha' => $actividad->fecha_inicio,
            'tema' => 'Bitácoras con el registro de la atención y el seguimiento a la comunicación interna y externa.',
            'detalle' => '',
            'encargado_comunicacion' => $this->userId(),
            'tipo_comunicacion' => '',
            'material' => '',
            'seguimiento' => '',
            'dirigidoa' => '',
            'url' => '',
            'asistencia' => 0,
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today()
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function folioRASCIE(): int
    {
        $ultimoFolio = ComunicacionIE::query()
            ->where('id_estacion', $this->estacionId())
            ->max('no_comunicacion');

        return $ultimoFolio ? $ultimoFolio + 1 : 1;
    }

    private function crearProgramaAnualSimulacro(
        CalendarioActividad $actividad
    ): string {

        ProgramaAnualSimulacros::create([
            'id_estacion' => $this->estacionId(),
            'nombre_simulacro' => '',
            'periodicidad' => 'Trimestral',
            'fecha' => $actividad->fecha_inicio,
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today()
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearProtocoloEmergencia(
        CalendarioActividad $actividad
    ): string {

        ProtocoloEmergencias::create([
            'id_estacion' => $this->estacionId(),
            'fechacreacion' => $actividad->fecha_inicio,
            'archivo' => '',
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today()
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearAtencionHallazgo(
        CalendarioActividad $actividad
    ): string {

        AtencionHallazgo::create([
            'id_estacion' => $this->estacionId(),
            'folio' => $this->folioAH(),
            'fecha_auditoria' => $actividad->fecha_inicio,
            'no_control' => '',
            'tipo_auditoria' => '',
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today(),
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function folioAH(): int
    {
        return (
            AtencionHallazgo::query()
            ->where('id_estacion', $this->estacionId())
            ->max('folio') ?? 0
        ) + 1;
    }

    private function crearRevisionResultados(
        CalendarioActividad $actividad
    ): string {

        RevisionResultados::create([
            'id_estacion' => $this->estacionId(),
            'id_usuario' => $this->userId(),
            'fecha_hora' => $actividad->fecha_inicio . ' ' . date('H:i:s'),
            'archivo' => '',
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today(),
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function crearEvaluacionDesempeno(
        CalendarioActividad $actividad
    ): string {

        EvaluacionDesempeno::create([
            'id_estacion' => $this->estacionId(),
            'id_usuario' => $this->userId(),
            'fecha_hora' => $actividad->fecha_inicio . ' ' . date('H:i:s'),
            'archivo' => '',
        ]);

        $actividad->update([
            'estado' => 1,
            'fecha_termino' => Carbon::today(),
        ]);

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }


    private function crearImplementacionSasisopa(
        CalendarioActividad $actividad
    ): string {

        Capsule::transaction(function () use ($actividad) {

            $reporte = ImplementacionSasisopa::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario' => $this->userId(),
                'fecha_hora' => $actividad->fecha_inicio . ' ' . date('H:i:s'),
            ]);

            $procedimientos = [
                'I. Política.',
                'II. Identificación de peligros y aspectos ambientales, análisis de riesgo y evaluación de impactos ambientales.',
                'III. Requisitos legales.',
                'IV. Objetivos, metas, indicadores.',
                'V. Funciones, responsabilidades y autoridad.',
                'VI. Competencia del personal, capacitación y entrenamiento',
                'VII. Comunicación, participación y consulta.',
                'VIII. Control de documentos y registros.',
                'IX. Mejores prácticas y estándares.',
                'X. Control de actividades y procesos.',
                'XI. Integridad mecánica y aseguramiento de la calidad.',
                'XII. Seguridad de contratistas.',
                'XIII. Preparación y respuesta a emergencias.',
                'XIV. Monitoreo, verificación y evaluación.',
                'XV. Auditorías.',
                'XVI. Investigación de incidentes y accidentes.',
                'XVII. Revisión de resultados.',
                'XVIII. Informes de desempeño.',
            ];

            $registros = [];

            foreach ($procedimientos as $procedimiento) {
                $registros[] = [
                    'id_reporte' => $reporte->id,
                    'fecha_implementacion' => null,
                    'procedimiento' => $procedimiento,
                    'descripcion' => '',
                    'informacion' => '',
                    'observaciones' => '',
                ];
            }

            ImplementacionSasisopaProcedimientos::insert($registros);

            $actividad->update([
                'estado' => 1,
                'fecha_termino' => Carbon::today(),
            ]);
        });

        return $this->urlDetalle(
            $actividad->actividad->id_sasisopa,
            $actividad->actividad->formato
        );
    }

    private function urlDetalle(int $sasisopa, string $formato): string
    {

        if ($sasisopa === 4 && $formato === 'Fo.ADMONGAS.005') {
            $year = date('Y');
            $mes = (int) date('m');

            return "/sasisopa/reporte-diario/{$mes}/{$year}";
        }

        return self::URLS["{$sasisopa}|{$formato}"] ?? '';
    }

    public function reagendar()
    {

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

        try {

            $id = (int) ($data['id'] ?? 0);

            $curso = CursoCalendario::findOrFail($id);

            if (
                $curso->estado != 1 ||
                $curso->resultado >= 60
            ) {

                throw new \Exception(
                    'El curso no puede reagendarse.'
                );
            }

            $nuevo = $curso->replicate();

            $nuevo->fecha_programada = $curso->fecha_programada->format('Y-m-d');
            $nuevo->fecha_real = null;
            $nuevo->resultado = 0;
            $nuevo->observaciones = '';
            $nuevo->estado = 0;

            $nuevo->save();

            echo json_encode([
                'success' => true,
                'message' => 'Curso Reagendado'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    //----------------------------------------------------------------------

    public function actividadesDisponibles()
    {
        $actividades = SasisopaActividad::query()
            ->with('sasisopa')
            ->orderBy('id_sasisopa')
            ->orderByDesc('id')
            ->get();

        $existentes = SasisopaEstacionActividad::query()
            ->where('id_estacion', $this->estacionId())
            ->pluck('id_actividad')
            ->toArray();

        $grupos = [];

        foreach ($actividades as $actividad) {

            if (in_array($actividad->id, $existentes)) {
                continue;
            }

            $key = $actividad->id_sasisopa;

            if (!isset($grupos[$key])) {

                $grupos[$key] = [

                    'id' => $key,

                    'label' =>

                    $actividad->sasisopa->numero_sasisopa .

                        ' - ' .

                        $actividad->sasisopa->nombre,

                    'actividades' => []

                ];
            }

            $grupos[$key]['actividades'][] = [

                'id' => $actividad->id,

                'nombre' =>

                $actividad->formato .

                    ' - ' .

                    $actividad->actividad

            ];
        }

        echo json_encode(

            array_values($grupos)

        );
    }

    public function createActividad()
    {

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        try {

            $actividadId = (int) ($data['actividad'] ?? 0);
            $fecha       = $data['fecha'] ?? null;

            $actividad = SasisopaActividad::findOrFail($actividadId);

            $this->registrarActividadEstacion($actividadId);

            $this->crearActividadCalendario(
                $actividadId,
                $fecha
            );

            $this->crearRecurrencias(
                $actividad,
                $fecha
            );

            echo json_encode([
                'success' => true
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function registrarActividadEstacion(int $actividad): void
    {
        SasisopaEstacionActividad::firstOrCreate([
            'id_estacion' => $this->estacionId(),
            'id_actividad' => $actividad
        ]);
    }

    private function crearActividadCalendario(
        int $actividad,
        string $fecha
    ): void {

        CalendarioActividad::create([

            'id_estacion' => $this->estacionId(),

            'id_actividad' => $actividad,

            'folio' => $this->folioActividad($actividad),

            'fecha_inicio' => $fecha,

            'fecha_termino' => '',

            'estado' => 0

        ]);
    }

    private function folioActividad(
        int $actividad
    ): int {

        return CalendarioActividad::query()

            ->where('id_estacion', $this->estacionId())

            ->where('id_actividad', $actividad)

            ->max('folio') + 1;
    }

    private function crearRecurrencias(
        SasisopaActividad $actividad,
        string $fecha
    ): void {

        switch ($actividad->periodicidad) {

            case 'Diario':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    1800,
                    'day'
                );

                break;

            case 'Mensual':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    60,
                    'month'
                );

                break;

            case 'Trimestral':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    20,
                    'quarter'
                );

                break;

            case 'Semestral':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    20,
                    'half'
                );

                break;

            case 'Anual':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    12,
                    'year'
                );

                break;

            case '5 años':

                $this->crearPeriodo(
                    $actividad->id,
                    $fecha,
                    12,
                    'five'
                );

                break;
        }
    }

    private function crearPeriodo(
        int $actividad,
        string $fecha,
        int $cantidad,
        string $tipo
    ): void {

        $fechaBase = new \DateTime($fecha);

        for ($i = 1; $i <= $cantidad; $i++) {

            $nueva = clone $fechaBase;

            switch ($tipo) {

                case 'day':

                    $nueva->modify("+{$i} day");

                    break;

                case 'month':

                    $nueva->modify("+{$i} month");

                    break;

                case 'quarter':

                    $nueva->modify("+" . ($i * 3) . " month");

                    break;

                case 'half':

                    $nueva->modify("+" . ($i * 6) . " month");

                    break;

                case 'year':

                    $nueva->modify("+{$i} year");

                    break;

                case 'five':

                    $nueva->modify("+" . ($i * 5) . " year");

                    break;
            }

            $this->crearActividadCalendario(

                $actividad,

                $nueva->format('Y-m-d')

            );
        }
    }
}
