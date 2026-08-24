<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalAsistencia;
use App\Models\Operativo\RhListaIncidencias;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhLocalidadesHorario;
use App\Models\Operativo\RhPersonalHorario;
use App\Models\Operativo\RhDiasDobles;
use App\Models\Operativo\RhPuestos;
use App\Services\ModuloDptoOperativoService;

class IncidenciasNominaService
{
    public const MODULE_KEY = 'incidencias-nomina';

    public const ESTACIONES_IDS = [1, 2, 3, 4, 5, 6, 7, 14];

    public const EXCLUIDOS = [387, 358, 296, 326, 300, 335];

    public const IDIA_DOBLE_INCIDENCIAS = [7, 8, 18];

    private const SESSION_KEY = 'incidencias_nomina_contexto';

    public static function getSemanaFromSession(): ?int
    {
        $ctx = Session::get(self::SESSION_KEY) ?? [];
        $semana = $ctx['semana'] ?? null;
        return $semana !== null ? (int)$semana : null;
    }

    public static function setSemanaSession(int $semana): void
    {
        Session::set(self::SESSION_KEY, ['semana' => $semana]);
    }

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

        return [
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_puesto'      => $idPuesto,
            'nombre_puesto'  => $nombrePuesto,
            'multiestacion'  => $multiestacion,
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
        ];
    }

    public static function getNombreEstacion(int $idEstacion): string
    {
        $e = RhLocalidad::find($idEstacion);
        return $e ? $e->localidad : '';
    }

    public static function getWeeksForYear(int $year): array
    {
        $jan1 = new \DateTime("$year-01-01");
        $dayOfWeek = (int)$jan1->format('w');
        $isLeap = (int)$jan1->format('L');
        $totalWeeks = ($dayOfWeek === 4 || ($dayOfWeek === 3 && $isLeap)) ? 53 : 52;

        $weeks = [];
        for ($n = 1; $n <= $totalWeeks; $n++) {
            $range = self::getWeekDateRange($year, $n);
            $weeks[] = [
                'numero' => $n,
                'inicio' => $range['inicio'],
                'fin'    => $range['fin'],
                'label'  => 'Semana ' . $n,
            ];
        }

        return $weeks;
    }

    public static function getCurrentWeekNumber(int $year): int
    {
        $today = new \DateTime();
        $todayYear = (int)$today->format('Y');

        if ($todayYear !== $year) {
            return 1;
        }

        return (int)$today->format('W');
    }

    public static function getWeekDateRange(int $year, int $semana): array
    {
        $inicioDay = new \DateTime();
        $inicioDay->setISODate($year, $semana, 1);
        $inicioDay->modify('last thursday');

        $finDay = clone $inicioDay;
        $finDay->modify('+6 days');

        return [
            'inicio' => $inicioDay->format('Y-m-d'),
            'fin'    => $finDay->format('Y-m-d'),
        ];
    }

public static function getWeekTitle(int $year, int $semana): string
{
    $range = self::getWeekDateRange($year, $semana);
    return 'Semana ' . $semana . " (del " . formatearFechaLarga($range['inicio']) . ' al ' . formatearFechaLarga($range['fin']) . ')';
}

public static function getWeekData(int $year, int $semana): array
{
    $range = self::getWeekDateRange($year, $semana);
    return [
        'titulo' => 'Semana ' . $semana,
        'rango'  => 'del ' . formatearFechaLarga($range['inicio']) . ' al ' . formatearFechaLarga($range['fin']),
    ];
}


    public static function getDiasEntre(string $fechaInicio, string $fechaFin): array
    {
        $dias = [];
        $start = new \DateTime($fechaInicio);
        $end = new \DateTime($fechaFin);
        $end->modify('+1 day');

        while ($start < $end) {
            $dias[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }

        return $dias;
    }

    public static function getNombreDiaCorto(string $fecha): string
    {
        $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $dayOfWeek = (int)(new \DateTime($fecha))->format('w');
        return $dayNames[$dayOfWeek];
    }

    public static function getDayHeaderLabel(string $fecha): string
    {
        $dia = (int)(new \DateTime($fecha))->format('j');
        $mesNames = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $mes = (int)(new \DateTime($fecha))->format('n');
        return $dia . ' ' . ($mesNames[$mes] ?? '');
    }

    public static function validarFecha(int $idPersonal, string $fecha): string
    {
        $asistencia = RhPersonalAsistencia::where('id_personal', $idPersonal)
            ->where('fecha', $fecha)
            ->first();

        if (!$asistencia) {
            return 'S/I';
        }

        $incidenciaId = (int)$asistencia->incidencia;

        if (in_array($incidenciaId, self::IDIA_DOBLE_INCIDENCIAS, false)) {
            return 'Dia doble';
        }

        if ($incidenciaId > 0) {
            $listaIncidencia = RhListaIncidencias::find($incidenciaId);
            if ($listaIncidencia) {
                return self::validarDiaDoble($listaIncidencia->detalle, $fecha);
            }
        }

        return 'S/I';
    }

    public static function validarDiaDoble(string $detalle, string $fecha): string
    {
        $fechaDt = new \DateTime($fecha);
        $dia = (int)$fechaDt->format('j');
        $mes = (int)$fechaDt->format('n');
        $yearShort = (int)$fechaDt->format('y');
        $yearFull = (int)$fechaDt->format('Y');

        $holiday = RhDiasDobles::where('dia', $dia)->where('mes', $mes)->first();

        if ($holiday) {
            $descripcion = $holiday->descripcion;
            $fechaComputada = null;

            if ($descripcion === 'Día de la Constitución') {
                $fechaComputada = date("Y-m-d", strtotime("first monday of February $yearShort"));
            } elseif ($descripcion === 'Natalicio de Benito Juárez') {
                $fechaComputada = date("Y-m-d", strtotime("third monday of March $yearShort"));
            } elseif ($descripcion === 'Revolución Mexicana') {
                $fechaComputada = date("Y-m-d", strtotime("third monday of November $yearShort"));
            } else {
                $fechaComputada = date("Y-m-d", strtotime("$yearFull-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-" . str_pad($dia, 2, '0', STR_PAD_LEFT)));
            }

            if ($fecha === $fechaComputada) {
                return 'Dia doble';
            }
        }

        return $detalle;
    }

    public static function getAsistenciaData(int $idEstacion, int $year, int $semana): array
    {
        $weekRange = self::getWeekDateRange($year, $semana);
        $diasEntre = self::getDiasEntre($weekRange['inicio'], $weekRange['fin']);

        $personal = RhPersonal::where('id_estacion', $idEstacion)
            ->where('estado', 1)
            ->orderBy('nombre_completo', 'ASC')
            ->get();

        $rows = [];
        foreach ($personal as $p) {
            if (in_array((int)$p->id, self::EXCLUIDOS, false)) continue;

            $puesto = RhPuestos::find($p->puesto);
            $retardos = 0;
            $faltas = 0;
            $diaDoble = 0;
            $dias = [];

            foreach ($diasEntre as $diaFecha) {
                $detalle = self::validarFecha((int)$p->id, $diaFecha);

                if ($detalle === 'Retardo') $retardos++;
                if ($detalle === 'Falta' || $detalle === 'Falta fin de semana') $faltas++;
                if ($detalle === 'Dia doble') $diaDoble++;

                $dias[] = [
                    'fecha'    => formatearFecha($diaFecha),
                    'detalle'  => $detalle,
                    'color'    => self::getColorForDetalle($detalle),
                ];
            }

            $rows[] = [
                'id'              => (int)$p->id,
                'nombre_completo' => $p->nombre_completo,
                'puesto_nombre'   => $puesto ? $puesto->puesto : '',
                'no_colaborador'  => $p->no_colaborador,
                'dias'            => $dias,
                'retardos'        => $retardos,
                'faltas'          => $faltas,
                'dia_doble'       => $diaDoble,
            ];
        }

        return $rows;
    }

    public static function getAllStationsData(int $year, int $semana, ?array $stationIds = null): array
    {
        $weekRange = self::getWeekDateRange($year, $semana);
        $diasEntre = self::getDiasEntre($weekRange['inicio'], $weekRange['fin']);

        $ids = $stationIds ?: self::ESTACIONES_IDS;

        $personal = RhPersonal::whereIn('id_estacion', $ids)
            ->where('estado', 1)
            ->orderBy('id_estacion', 'ASC')
            ->orderBy('nombre_completo', 'ASC')
            ->get();

        $rows = [];
        foreach ($personal as $p) {
            if (in_array((int)$p->id, self::EXCLUIDOS, false)) continue;

            $puesto = RhPuestos::find($p->puesto);
            $estacion = RhLocalidad::find($p->id_estacion);
            $retardos = 0;
            $faltas = 0;
            $diaDoble = 0;
            $dias = [];

            foreach ($diasEntre as $diaFecha) {
                $detalle = self::validarFecha((int)$p->id, $diaFecha);

                if ($detalle === 'Retardo') $retardos++;
                if ($detalle === 'Falta' || $detalle === 'Falta fin de semana') $faltas++;
                if ($detalle === 'Dia doble') $diaDoble++;

                $dias[] = [
                    'fecha'    => formatearFecha($diaFecha),
                    'detalle'  => $detalle,
                    'color'    => self::getColorForDetalle($detalle),
                ];
            }

            $rows[] = [
                'id'              => (int)$p->id,
                'nombre_completo' => $p->nombre_completo,
                'puesto_nombre'   => $puesto ? $puesto->puesto : '',
                'no_colaborador'  => $p->no_colaborador,
                'id_estacion'     => (int)$p->id_estacion,
                'estacion_nombre' => $estacion ? $estacion->localidad : '',
                'dias'            => $dias,
                'retardos'        => $retardos,
                'faltas'          => $faltas,
                'dia_doble'       => $diaDoble,
            ];
        }

        return $rows;
    }

    public static function getColorForDetalle(string $detalle): string
    {
        switch ($detalle) {
            case 'Dia doble':
                return 'text-success fw-bold';
            case 'Retardo':
                return 'text-warning';
            case 'Falta':
            case 'Falta fin de semana':
                return 'text-danger';
            case 'OK':
                return 'text-success fw-bold';
            case 'Descanso':
                return 'text-secondary';
            default:
                return 'text-black';
        }
    }

    public static function getStationIdsForReport(): array
    {
        $available = ModuleStationService::getAvailableStations(self::MODULE_KEY);
        return array_column($available, 'id');
    }

    public static function buildStationTableHtml(int $idEstacion, int $year, int $semana): string
    {
        $nombreEstacion = self::getNombreEstacion($idEstacion);
        $weekRange = self::getWeekDateRange($year, $semana);
        $diasEntre = self::getDiasEntre($weekRange['inicio'], $weekRange['fin']);
        $weekRangeInicio = $weekRange['inicio'];
        $weekRangeFin = $weekRange['fin'];

        $html = '<h1 class="text-secondary">' . htmlspecialchars($nombreEstacion) . '<br>';
        $html .= '<div class="mt-1" style="font-size: .7em;">Semana ' . $semana . '</div>';
        $html .= '<div class="mt-1" style="font-size: .5em;">' . formatearFecha($weekRangeInicio) . ' al ' . formatearFecha($weekRangeFin) . '</div>';
        $html .= '</h1>';

        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-sm table-bordered table-hover pb-0 mb-0 mt-2" style="font-size: .75em;">';
        $html .= '<thead class="tables-bg">';

        $html .= '<tr>';
        $html .= '<th class="text-center align-middle fw-bold" width="48px">No.</th>';
        $html .= '<th class="text-start align-middle">Nombre</th>';
        $html .= '<th class="text-center align-middle">Puesto</th>';

        foreach ($diasEntre as $dia) {
            $html .= '<th class="align-middle text-center">' . formatearFechaLarga($dia) . '</th>';
        }

        $html .= '<th class="align-middle text-center">Retardos</th>';
        $html .= '<th class="align-middle text-center">Faltas</th>';
        $html .= '<th class="text-center align-middle fw-bold">Dias Dobles</th>';
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody class="bg-light">';

        $rows = self::getAsistenciaData($idEstacion, $year, $semana);
        $num = 1;
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<th class="text-center align-middle">' . $num . '</th>';
            $html .= '<td class="text-start align-middle">' . htmlspecialchars($row['nombre_completo']) . '</td>';
            $html .= '<td class="text-center align-middle">' . htmlspecialchars($row['puesto_nombre']) . '</td>';

            foreach ($row['dias'] as $diaInfo) {
                $html .= '<td class="align-middle text-center ' . $diaInfo['color'] . '">' . htmlspecialchars($diaInfo['detalle']) . '</td>';
            }

            $html .= '<td class="align-middle text-center">' . $row['retardos'] . '</td>';
            $html .= '<td class="align-middle text-center">' . $row['faltas'] . '</td>';
            $html .= '<td class="align-middle text-center">' . $row['dia_doble'] . '</td>';
            $html .= '</tr>';
            $num++;
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    public static function getPdfStyles(): string
    {
        return '
        @page { margin: 0.8cm 1cm; }
        html { font-family: sans-serif; line-height: 1; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; -ms-overflow-style: scrollbar; -webkit-tap-highlight-color: transparent; }
        @-ms-viewport { width: device-width; }
        article, aside, dialog, figcaption, figure, footer, header, hgroup, main, nav, section { display: block; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; font-size: .8rem; font-weight: 400; line-height: 1; color: #212529; text-align: left; background-color: #fff; }
        .row { display: -webkit-box; display: -ms-flexbox; display: flex; -ms-flex-wrap: wrap; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
        .no-gutters { margin-right: 0; margin-left: 0; }
        .no-gutters > .col, .no-gutters > [class*="col-"] { padding-right: 0; padding-left: 0; }
        .col-5 { -webkit-box-flex: 0; -ms-flex: 0 0 41.666667%; flex: 0 0 41.666667%; max-width: 41.666667%; }
        .col-7 { -webkit-box-flex: 0; -ms-flex: 0 0 58.333333%; flex: 0 0 58.333333%; max-width: 58.333333%; }
        .mt-1 { margin-top: 0.25rem !important; }
        .mt-2, .my-2 { margin-top: 0.5rem !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .p-1 { padding: 0.25rem !important; }
        .p-3 { padding: 0.75rem !important; }
        .text-center { text-align: center !important; }
        .border { border: 1px solid #dee2e6 !important; }
        table { border-collapse: collapse; }
        th { text-align: inherit; }
        .table { width: 100%; max-width: 100%; margin-bottom: 1rem; background-color: transparent; border-collapse: collapse; font-size: 0.5rem; }
        .table, .table th, .table td { font-size: 0.5rem; }
        .table th, .table td { padding: 0.5rem; }
        .table thead th { vertical-align: bottom; border-bottom: 2px solid #dee2e6; }
        .table tbody + tbody { border-top: 2px solid #dee2e6; }
        .table .table { background-color: #fff; }
        .table-sm th, .table-sm td { padding: 0.3rem; }
        .table-bordered { border: 1px solid #dee2e6; }
        .table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
        .table-bordered thead th, .table-bordered thead td { border-bottom-width: 2px; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0, 0, 0, 0.05); }
        .pb-0, .py-0 { padding-bottom: 0 !important; }
        .mb-0, .my-0 { margin-bottom: 0 !important; }
        .align-middle { vertical-align: middle !important; }
        .text-right { text-align: right !important; }
        .border-0 { border: 0 !important; }
        .p-2 { padding: 0.5rem !important; }
        .text-end { text-align: right !important; }
        h1, .h1 { font-size: 1.25rem; }
        h6, .h6 { font-size: 1rem; }
        .text-danger { color: #dc3545 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-success { color: #28a745 !important; }
        .text-secondary { color: #6c757d !important; }
        h3 { font-size: 1rem; }
        .fw-bold { font-weight: 700 !important; }
        .text-black { color: #000 !important; }
        .table-responsive { width: 100%; overflow-x: auto; }
        ';
    }
}
