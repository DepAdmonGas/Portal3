<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhRolComodines;
use App\Models\Operativo\RhComodinesDia;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;

class RolComodinesService
{
    public const MODULE_KEY = 'incidencias-nomina';

    private const DESCANSO_VALUE = 400;

    private const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

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

    public static function getNombreDia(int $dia): string
    {
        return self::DIAS[$dia] ?? '';
    }

    public static function getDias(): array
    {
        return self::DIAS;
    }

    public static function getList(): array
    {
        $roles = RhRolComodines::orderBy('id', 'DESC')->get();

        $rows = [];
        foreach ($roles as $rol) {
            $fechaInicio = self::formatFechaList($rol->fecha_inicio);
            $fechaFin = self::formatFechaList($rol->fecha_fin);

            $rows[] = [
                'id'            => (int)$rol->id,
                'fecha_inicio'  => $fechaInicio,
                'fecha_fin'     => $fechaFin,
                'status'        => (int)$rol->status,
                'status_label'  => $rol->status == 1 ? 'Finalizado' : 'Pendiente',
            ];
        }

        return $rows;
    }

    private static function formatFechaList($fecha): string
    {
        if ($fecha === null || $fecha === '' || $fecha === false) return 'S/I';

        $str = trim((string)$fecha);
        if ($str === '' || $str === '0000-00-00' || $str === '0000-00-00 00:00:00' || $str === 'null') return 'S/I';

        $formatted = formatearFecha($str);
        return $formatted !== '' ? $formatted : 'S/I';
    }

    public static function getDetail(int $idReporte): ?array
    {
        $rol = RhRolComodines::find($idReporte);
        if (!$rol) return null;

        $fechaInicio = self::normalizeDate($rol->fecha_inicio);
        $fechaFin = self::normalizeDate($rol->fecha_fin);

        $empleados = self::getEmpleados($idReporte);
        $estaciones = self::getEstaciones();

        $asignaciones = [];
        $diaRows = RhComodinesDia::where('id_reporte', $idReporte)->get();
        foreach ($diaRows as $row) {
            $asignaciones[$row->id_usuario][$row->dia] = (int)$row->id_estacion;
        }

        return [
            'id'                 => (int)$rol->id,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'fecha_inicio_label' => $fechaInicio ? formatearFecha($fechaInicio) : 'S/I',
            'fecha_fin_label'    => $fechaFin ? formatearFecha($fechaFin) : 'S/I',
            'status'             => (int)$rol->status,
            'empleados'          => $empleados,
            'estaciones'         => $estaciones,
            'asignaciones'       => $asignaciones,
        ];
    }

    public static function getEmpleados(?int $idReporte = null): array
    {
        $assignedIds = [];
        if ($idReporte) {
            $assignedIds = RhComodinesDia::where('id_reporte', $idReporte)
                ->pluck('id_usuario')
                ->toArray();
        }

        $users = Usuario::where(function ($q) use ($assignedIds) {
            $q->where(function ($q2) {
                $q2->where('id_gas', 8)->where('id_puesto', 6);
            })->orWhere('id', 321);
        })
        ->where(function ($q) use ($assignedIds) {
            $q->where('estatus', 0);
            if (!empty($assignedIds)) {
                $q->orWhereIn('id', $assignedIds);
            }
        })
        ->orderBy('id', 'ASC')
        ->get();

        $empleados = [];
        foreach ($users as $u) {
            $empleados[] = [
                'id'     => (int)$u->id,
                'nombre' => $u->nombre,
            ];
        }

        return $empleados;
    }

    public static function getEstaciones(): array
    {
        $estaciones = Estacion::where('numlista', '<=', 8)
            ->orderBy('numlista', 'ASC')
            ->get(['id', 'nombre']);

        $result = [];
        foreach ($estaciones as $e) {
            $result[] = [
                'id'     => (int)$e->id,
                'nombre' => $e->nombre,
            ];
        }

        return $result;
    }

    public static function getPdfStyles(): string
    {
        return '
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 10px;
                color: #333;
                margin: 0;
                padding: 0;
            }
            h2 {
                font-size: 16px;
                font-weight: bold;
                margin: 10px 0 5px 0;
            }
            .dates-row {
                width: 100%;
                margin-bottom: 10px;
                border-collapse: collapse;
            }
            .dates-row td {
                vertical-align: top;
                padding: 2px 8px;
            }
            .dates-row small {
                color: #666;
                font-weight: bold;
                display: block;
            }
            .custom-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            }
            .custom-table thead tr th {
                border: none;
                text-transform: uppercase;
                color: #fff;
                padding: 8px 4px;
                font-size: 8.5px;
                font-weight: bold;
            }
            .custom-table thead tr th:first-child {
                border-top-left-radius: 7px;
            }
            .custom-table thead tr th:last-child {
                border-top-right-radius: 7px;
            }
            .custom-table tbody tr td {
                padding: 8px 4px;
                font-size: 9px;
                border: none;
            }
            .custom-table tbody tr:nth-child(even) td {
                background-color: #f8f9fa;
            }
            .tables-bg {
                background: #215D98;
                color: white;
            }
            .text-start-custom {
                text-align: left;
            }
        ';
    }

    public static function buildRolComodinesPdfHtml(int $idReporte): string
    {
        $detail = self::getDetail($idReporte);
        if (!$detail) return '<p>Registro no encontrado.</p>';

        $fechaInicio = $detail['fecha_inicio'] ? formatearFecha($detail['fecha_inicio']) : 'Sin información';
        $fechaFin = $detail['fecha_fin'] ? formatearFecha($detail['fecha_fin']) : 'Sin información';

        $html = '<h2>Rol de comodines</h2>';

        $html .= '<table class="dates-row">';
        $html .= '<tr>';
        $html .= '<td width="50%"><small>FECHA DE INICIO:</small><div>' . $fechaInicio . '</div></td>';
        $html .= '<td width="50%"><small>FECHA DE TERMINO:</small><div>' . $fechaFin . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="custom-table">';
        $html .= '<thead><tr class="tables-bg">';
        $html .= '<th class="text-center" width="30">#</th>';
        $html .= '<th class="text-start-custom">Nombre completo</th>';
        $html .= '<th class="text-center">Lunes</th>';
        $html .= '<th class="text-center">Martes</th>';
        $html .= '<th class="text-center">Miércoles</th>';
        $html .= '<th class="text-center">Jueves</th>';
        $html .= '<th class="text-center">Viernes</th>';
        $html .= '<th class="text-center">Sábado</th>';
        $html .= '<th class="text-center">Domingo</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        $diasNombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $rowNum = 1;

        foreach ($detail['empleados'] as $emp) {
            $html .= '<tr>';
            $html .= '<td class="text-center fw-bold">' . $rowNum . '</td>';
            $html .= '<td class="text-start-custom">' . htmlspecialchars($emp['nombre']) . '</td>';

            foreach ($diasNombres as $dia) {
                $val = $detail['asignaciones'][$emp['id']][$dia] ?? 0;
                $estacion = self::resolverEstacion($val, $detail['estaciones']);
                $html .= '<td class="text-center">' . htmlspecialchars($estacion) . '</td>';
            }

            $html .= '</tr>';
            $rowNum++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private static function resolverEstacion(int $val, array $estaciones): string
    {
        if ($val == 400) return 'Descanso';
        if ($val <= 0) return 'S/I';

        foreach ($estaciones as $est) {
            if ((int)$est['id'] === $val) return $est['nombre'];
        }

        return 'S/I';
    }

    private static function normalizeDate($fecha): string
    {
        if ($fecha === null || $fecha === '' || $fecha === false) return '';

        $str = trim((string)$fecha);
        if ($str === '' || $str === '0000-00-00' || $str === '0000-00-00 00:00:00') return '';

        try {
            $date = \Carbon\Carbon::parse($str);
            return $date->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function getAsignacion(int $idReporte, int $idUsuario, int $dia): int
    {
        $nombreDia = self::getNombreDia($dia);
        $asignacion = RhComodinesDia::where('id_reporte', $idReporte)
            ->where('id_usuario', $idUsuario)
            ->where('dia', $nombreDia)
            ->first();

        return $asignacion ? (int)$asignacion->id_estacion : 0;
    }

    public static function agregar(): int
    {
        $ultimo = RhRolComodines::orderBy('id', 'DESC')->first();

        if ($ultimo && $ultimo->status == 0) {
            return (int)$ultimo->id;
        }

        $nuevo = RhRolComodines::create([
            'id_estacion'  => 0,
            'status'       => 0,
            'fecha_inicio' => '0000-00-00',
            'fecha_fin'    => '0000-00-00',
        ]);

        return (int)$nuevo->id;
    }

    public static function editarAsignacion(int $idReporte, int $idUsuario, int $idEstacion, int $dia): bool
    {
        $nombreDia = self::getNombreDia($dia);
        if (!$nombreDia) return false;

        $existe = RhComodinesDia::where('id_reporte', $idReporte)
            ->where('id_usuario', $idUsuario)
            ->where('dia', $nombreDia)
            ->first();

        if ($existe) {
            $existe->id_estacion = $idEstacion;
            return $existe->save();
        }

        RhComodinesDia::create([
            'id_reporte'  => $idReporte,
            'id_usuario'  => $idUsuario,
            'id_estacion' => $idEstacion,
            'dia'         => $nombreDia,
        ]);

        return true;
    }

    public static function guardarFechas(int $idReporte, string $fechaInicio, string $fechaFin): bool
    {
        $rol = RhRolComodines::find($idReporte);
        if (!$rol) return false;

        if ($fechaInicio !== '') $rol->fecha_inicio = $fechaInicio;
        if ($fechaFin !== '') $rol->fecha_fin = $fechaFin;

        return $rol->save();
    }

    public static function finalizar(int $idReporte, string $fechaInicio, string $fechaFin): bool
    {
        $rol = RhRolComodines::find($idReporte);
        if (!$rol) return false;

        $rol->fecha_inicio = $fechaInicio;
        $rol->fecha_fin = $fechaFin;
        $rol->status = 1;

        return $rol->save();
    }

    public static function eliminar(int $idReporte): bool
    {
        $rol = RhRolComodines::find($idReporte);
        if (!$rol) return false;

        RhComodinesDia::where('id_reporte', $idReporte)->delete();
        $rol->delete();

        return true;
    }

    public static function notificarCreacion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '➕ Se ha creado un nuevo <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Creado por: ' . $nombreUsuario
                . PHP_EOL . 'Estado: Borrador';

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarCreacion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarEdicion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '✏️ Se ha editado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Editado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarEdicion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarFinalizacion(int $idReporte, int $idUsuario, string $fechaInicio, string $fechaFin): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '✅ Se ha finalizado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Período: ' . formatearFecha($fechaInicio) . ' al ' . formatearFecha($fechaFin)
                . PHP_EOL . 'Finalizado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarFinalizacion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarEliminacion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '🗑️ Se ha eliminado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Eliminado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarEliminacion RolComodines: ' . $e->getMessage());
        }
    }
}
