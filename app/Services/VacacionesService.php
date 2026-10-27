<?php

namespace App\Services;

use App\Core\Session;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * Vista de seguimiento de vacaciones por personal y localidad.
 * Las solicitudes y su ciclo de firmas siguen siendo propiedad de FormatosService.
 */
class VacacionesService
{
    public const MODULE_KEY = 'vacaciones';
    public const FORMATO = 6;
    public const UPLOAD_DIR = 'public/uploads/archivos/vacaciones/';

    public static function getPermisos(): array
    {
        $formatos = FormatosService::getPermisos();

        return [
            'puede_leer' => !empty($formatos['puedeAcceso']),
            'puede_crear' => !empty($formatos['puedeCrear']),
            'puede_editar' => !empty($formatos['puedeEditar']),
            'puede_eliminar' => !empty($formatos['puedeEliminar']),
            'puede_descargar' => !empty($formatos['puedeDescargar']),
            'puede_firmar' => !empty($formatos['puedeFirmar']),
            'id_usuario' => (int)($formatos['id_usuario'] ?? 0),
        ];
    }

    /** Localidades permitidas por tb_modulos_config cruzadas con multiestación. */
    public static function getLocalidadesPermitidas(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $row) {
            $ids[] = (int)$row['id'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $row) {
            $ids[] = (int)$row['id'];
        }

        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];

        return Capsule::table('op_rh_localidades')
            ->whereIn('id', $ids)
            ->orderBy('numlista')
            ->get(['id', 'localidad as nombre', 'numlista'])
            ->map(static fn($row): array => [
                'id' => (int)$row->id,
                'nombre' => (string)$row->nombre,
                'numlista' => (int)$row->numlista,
            ])
            ->all();
    }

    public static function getContexto(): array
    {
        return ModuleStationService::getContext(self::MODULE_KEY);
    }

    /**
     * null significa todas las localidades autorizadas; [] representa un
     * contexto inválido o un usuario sin localidades disponibles.
     */
    public static function getAlcanceActual(): array
    {
        $permitidas = array_column(self::getLocalidadesPermitidas(), 'id');
        $contexto = self::getContexto();
        $seleccion = $contexto['id_depto'] ?? $contexto['id_estacion'] ?? null;

        if ($seleccion === null) return array_map('intval', $permitidas);
        $seleccion = (int)$seleccion;
        return in_array($seleccion, $permitidas, true) ? [$seleccion] : [];
    }

    public static function puedeLocalidad(int $idLocalidad): bool
    {
        return $idLocalidad > 0
            && in_array($idLocalidad, array_map('intval', array_column(self::getLocalidadesPermitidas(), 'id')), true);
    }

    public static function puedeRegistroEnContexto(int $idLocalidad): bool
    {
        return in_array($idLocalidad, self::getAlcanceActual(), true);
    }

    public static function mostrarColumnaEstacion(): bool
    {
        $contexto = self::getContexto();
        return ($contexto['id_estacion'] ?? null) === null
            && ($contexto['id_depto'] ?? null) === null
            && count(self::getLocalidadesPermitidas()) > 1;
    }

    /** Conteos por localidad para el selector y el total del contexto activo. */
    public static function getConteosPendientes(int $year): array
    {
        $localidades = self::getLocalidadesPermitidas();
        $counts = ['total' => 0];
        foreach ($localidades as $localidad) {
            $counts['localidad_' . $localidad['id']] = 0;
        }

        $ids = array_map('intval', array_column($localidades, 'id'));
        $statuses = self::estadosPendientesUsuario();
        if ($ids === [] || $statuses === []) return $counts;

        $rows = Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->where('f.formato', self::FORMATO)
            ->whereIn('f.status', $statuses)
            ->whereYear('f.fecha', $year)
            ->whereIn('f.id_localidad', $ids)
            ->groupBy('f.id_localidad')
            ->select('f.id_localidad')
            ->selectRaw('COUNT(DISTINCT f.id) as total')
            ->get();

        foreach ($rows as $row) {
            $id = (int)$row->id_localidad;
            $total = (int)$row->total;
            $counts['localidad_' . $id] = $total;
            $counts['total'] += $total;
        }

        $scope = self::getAlcanceActual();
        $counts['contexto'] = $scope === [] ? 0 : (int)array_sum(array_intersect_key(
            array_map('intval', array_filter($counts, static fn($key): bool => str_starts_with((string)$key, 'localidad_'), ARRAY_FILTER_USE_KEY)),
            array_fill_keys(array_map(static fn(int $id): string => 'localidad_' . $id, $scope), true)
        ));

        return $counts;
    }

    /** Estados con firma pendiente; status 0 es borrador y no es solicitud enviada. */
    private static function estadosPendientesUsuario(): array
    {
        return match ((int)(Session::get('usuario')['id'] ?? 0)) {
            19 => [1], // Visto Bueno
            2 => [2],  // Autorización
            354 => [3], // Verificación
            default => [1, 2, 3],
        };
    }

    public static function getLista(int $year): array
    {
        $scope = self::getAlcanceActual();
        if ($scope === []) return [];

        $personal = Capsule::table('op_rh_personal as p')
            ->leftJoin('op_rh_puestos as puesto', 'puesto.id', '=', 'p.puesto')
            ->leftJoin('op_rh_localidades as localidad', 'localidad.id', '=', 'p.id_estacion')
            ->where('p.estado', 1)
            ->whereIn('p.id_estacion', $scope)
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.id_estacion', 'p.fecha_ingreso', 'p.nombre_completo',
                'p.puesto as id_puesto', 'puesto.puesto as nombre_puesto',
                'localidad.localidad as nombre_localidad',
            ]);

        if ($personal->isEmpty()) return [];
        $personalIds = $personal->pluck('id')->map(static fn($id): int => (int)$id)->all();
        $inicioYear = sprintf('%04d-01-01', $year);
        $finYear = sprintf('%04d-12-31', $year);

        $solicitudes = Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->where('f.formato', self::FORMATO)
            ->whereIn('v.id_usuario', $personalIds)
            ->whereBetween('v.fecha_inicio', [$inicioYear, $finYear])
            ->orderByDesc('f.id')
            ->get([
                'f.id as id_formulario', 'f.status', 'f.fecha as fecha_solicitud',
                'v.id_usuario', 'v.num_dias', 'v.fecha_inicio', 'v.fecha_termino',
                'v.fecha_regreso', 'v.observaciones',
            ]);

        $solicitudPorPersonal = [];
        $diasPorPersonal = [];
        foreach ($solicitudes as $solicitud) {
            $idPersonal = (int)$solicitud->id_usuario;
            $diasPorPersonal[$idPersonal] = ($diasPorPersonal[$idPersonal] ?? 0) + (int)$solicitud->num_dias;
            if (!isset($solicitudPorPersonal[$idPersonal])) {
                $solicitudPorPersonal[$idPersonal] = $solicitud;
            }
        }

        $comentarios = Capsule::table('op_rh_formatos_vacaciones_comentarios')
            ->whereIn('id_usuario_vacaciones', $personalIds)
            ->where('year', $year)
            ->groupBy('id_usuario_vacaciones')
            ->select('id_usuario_vacaciones')
            ->selectRaw('COUNT(*) as total')
            ->get()
            ->mapWithKeys(static fn($row): array => [(int)$row->id_usuario_vacaciones => (int)$row->total])
            ->all();

        $primaVacacional = Capsule::table('op_recibo_nomina_v2')
            ->where('year', $year)
            ->whereIn('id_usuario', $personalIds)
            ->where('prima_vacacional', 2)
            ->whereNotNull('doc_nomina_firma')
            ->where('doc_nomina_firma', '<>', '')
            ->orderByDesc('id')
            ->get(['id_usuario', 'doc_nomina_firma'])
            ->unique('id_usuario')
            ->mapWithKeys(static fn($row): array => [(int)$row->id_usuario => basename((string)$row->doc_nomina_firma)])
            ->all();

        $result = [];
        foreach ($personal as $row) {
            $id = (int)$row->id;
            $solicitud = $solicitudPorPersonal[$id] ?? null;
            $result[] = [
                'id_personal' => $id,
                'id_localidad' => (int)$row->id_estacion,
                'nombre_localidad' => (string)($row->nombre_localidad ?? ''),
                'fecha_ingreso' => (string)($row->fecha_ingreso ?? ''),
                'fecha_ingreso_formateada' => !empty($row->fecha_ingreso) ? formatearFecha($row->fecha_ingreso) : 'S/I',
                'nombre_completo' => (string)$row->nombre_completo,
                'nombre_puesto' => (string)($row->nombre_puesto ?? ''),
                'mes_ingreso' => !empty($row->fecha_ingreso) ? (int)date('n', strtotime((string)$row->fecha_ingreso)) : null,
                'prima_vacacional' => (string)($primaVacacional[$id] ?? ''),
                'id_formulario' => $solicitud ? (int)$solicitud->id_formulario : null,
                'status' => $solicitud ? (int)$solicitud->status : null,
                'status_label' => $solicitud ? self::etiquetaEstado((int)$solicitud->status) : 'Sin solicitud',
                'num_dias' => $solicitud ? (int)$solicitud->num_dias : 0,
                'fecha_inicio' => $solicitud && $solicitud->fecha_inicio ? formatearFecha($solicitud->fecha_inicio) : '',
                'fecha_termino' => $solicitud && $solicitud->fecha_termino ? formatearFecha($solicitud->fecha_termino) : '',
                'fecha_regreso' => $solicitud && $solicitud->fecha_regreso ? formatearFecha($solicitud->fecha_regreso) : '',
                'total_dias_year' => (int)($diasPorPersonal[$id] ?? 0),
                'num_comentarios' => (int)($comentarios[$id] ?? 0),
                'observaciones' => (string)($solicitud->observaciones ?? ''),
            ];
        }

        return $result;
    }

    public static function etiquetaEstado(int $status): string
    {
        return match ($status) {
            0 => 'Borrador',
            1 => 'Pendiente de Visto Bueno',
            2 => 'Pendiente de autorización',
            3 => 'Pendiente de verificación',
            4 => 'Finalizado',
            default => 'Sin solicitud',
        };
    }

    public static function getSolicitud(int $idFormulario): ?array
    {
        $scope = self::getAlcanceActual();
        if ($idFormulario <= 0 || $scope === []) return null;

        $row = Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->join('op_rh_personal as p', 'p.id', '=', 'v.id_usuario')
            ->leftJoin('op_rh_puestos as puesto', 'puesto.id', '=', 'p.puesto')
            ->leftJoin('op_rh_localidades as localidad', 'localidad.id', '=', 'p.id_estacion')
            ->where('f.id', $idFormulario)
            ->where('f.formato', self::FORMATO)
            ->whereIn('f.id_localidad', $scope)
            ->first([
                'f.id', 'f.status', 'f.fecha as fecha_solicitud', 'f.id_localidad',
                'p.nombre_completo', 'p.fecha_ingreso', 'puesto.puesto as nombre_puesto',
                'localidad.localidad as nombre_localidad', 'v.num_dias', 'v.fecha_inicio',
                'v.fecha_termino', 'v.fecha_regreso', 'v.observaciones',
            ]);

        if (!$row) return null;
        return [
            'id' => (int)$row->id,
            'status' => (int)$row->status,
            'status_label' => self::etiquetaEstado((int)$row->status),
            'fecha_solicitud' => $row->fecha_solicitud ? formatearFecha($row->fecha_solicitud) : 'S/I',
            'nombre_completo' => (string)$row->nombre_completo,
            'nombre_puesto' => (string)($row->nombre_puesto ?? ''),
            'nombre_localidad' => (string)($row->nombre_localidad ?? ''),
            'fecha_ingreso' => $row->fecha_ingreso ? formatearFecha($row->fecha_ingreso) : 'S/I',
            'num_dias' => (int)$row->num_dias,
            'fecha_inicio' => $row->fecha_inicio ? formatearFecha($row->fecha_inicio) : 'S/I',
            'fecha_termino' => $row->fecha_termino ? formatearFecha($row->fecha_termino) : 'S/I',
            'fecha_regreso' => $row->fecha_regreso ? formatearFecha($row->fecha_regreso) : 'S/I',
            'observaciones' => (string)($row->observaciones ?? ''),
        ];
    }

    /** Solicitudes del personal para el año consultado, dentro del alcance activo. */
    public static function getHistorialPersonal(int $idPersonal, int $year): ?array
    {
        $scope = self::getAlcanceActual();
        if ($idPersonal <= 0 || $scope === [] || !self::puedePersonalEnContexto($idPersonal)) return null;

        $rows = Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->where('f.formato', self::FORMATO)
            ->where('v.id_usuario', $idPersonal)
            ->whereIn('f.id_localidad', $scope)
            ->whereBetween('v.fecha_inicio', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)])
            ->orderByDesc('f.id')
            ->get([
                'f.id', 'f.status', 'f.fecha as fecha_solicitud', 'v.num_dias',
                'v.fecha_inicio', 'v.fecha_termino', 'v.fecha_regreso',
            ]);

        return [
            'id_personal' => $idPersonal,
            'year' => $year,
            'solicitudes' => $rows->map(static fn($row): array => [
                'id' => (int)$row->id,
                'status' => (int)$row->status,
                'status_label' => self::etiquetaEstado((int)$row->status),
                'fecha_solicitud' => $row->fecha_solicitud ? formatearFecha($row->fecha_solicitud) : 'S/I',
                'num_dias' => (int)$row->num_dias,
                'fecha_inicio' => $row->fecha_inicio ? formatearFecha($row->fecha_inicio) : 'S/I',
                'fecha_termino' => $row->fecha_termino ? formatearFecha($row->fecha_termino) : 'S/I',
                'fecha_regreso' => $row->fecha_regreso ? formatearFecha($row->fecha_regreso) : 'S/I',
            ])->all(),
        ];
    }

    public static function getDetallePersonal(int $idPersonal, int $year): ?array
    {
        $scope = self::getAlcanceActual();
        if ($idPersonal <= 0 || $scope === []) return null;

        $personal = Capsule::table('op_rh_personal as p')
            ->leftJoin('op_rh_puestos as puesto', 'puesto.id', '=', 'p.puesto')
            ->leftJoin('op_rh_localidades as localidad', 'localidad.id', '=', 'p.id_estacion')
            ->where('p.id', $idPersonal)
            ->where('p.estado', 1)
            ->whereIn('p.id_estacion', $scope)
            ->first(['p.id', 'p.id_estacion', 'p.nombre_completo', 'p.fecha_ingreso', 'puesto.puesto as nombre_puesto', 'localidad.localidad as nombre_localidad']);

        if (!$personal) return null;

        $solicitud = Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->where('f.formato', self::FORMATO)
            ->where('v.id_usuario', $idPersonal)
            ->whereBetween('v.fecha_inicio', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)])
            ->orderByDesc('f.id')
            ->first(['f.id', 'f.status', 'v.num_dias']);

        $diasTotal = (int)Capsule::table('op_rh_formatos as f')
            ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
            ->where('f.formato', self::FORMATO)
            ->where('v.id_usuario', $idPersonal)
            ->whereBetween('v.fecha_inicio', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)])
            ->sum('v.num_dias');

        return [
            'id_personal' => (int)$personal->id,
            'nombre_completo' => (string)$personal->nombre_completo,
            'nombre_puesto' => (string)($personal->nombre_puesto ?? ''),
            'nombre_localidad' => (string)($personal->nombre_localidad ?? ''),
            'mes_ingreso' => $personal->fecha_ingreso ? (int)date('n', strtotime((string)$personal->fecha_ingreso)) : null,
            'id_formulario' => $solicitud ? (int)$solicitud->id : null,
            'status' => $solicitud ? (int)$solicitud->status : null,
            'status_label' => $solicitud ? self::etiquetaEstado((int)$solicitud->status) : 'Sin solicitud',
            'num_dias' => $solicitud ? (int)$solicitud->num_dias : 0,
            'total_dias_year' => $diasTotal,
        ];
    }

    public static function getComentarios(int $idPersonal, int $year): array
    {
        if (!self::puedePersonalEnContexto($idPersonal)) return [];

        return Capsule::table('op_rh_formatos_vacaciones_comentarios as c')
            ->leftJoin('tb_usuarios as u', 'u.id', '=', 'c.id_usuario')
            ->where('c.id_usuario_vacaciones', $idPersonal)
            ->where('c.year', $year)
            ->orderBy('c.id')
            ->get(['c.id', 'c.id_usuario', 'c.comentario', 'c.fecha_hora', 'u.nombre as nombre_usuario'])
            ->map(static fn($row): array => [
                'id' => (int)$row->id,
                'id_usuario' => (int)$row->id_usuario,
                'nombre_usuario' => (string)($row->nombre_usuario ?? 'Usuario'),
                'comentario' => (string)$row->comentario,
                'fecha_hora' => $row->fecha_hora ? formatearFecha($row->fecha_hora) . ', ' . date('g:i a', strtotime((string)$row->fecha_hora)) : '',
            ])
            ->all();
    }

    public static function puedePersonalEnContexto(int $idPersonal): bool
    {
        $scope = self::getAlcanceActual();
        if ($idPersonal <= 0 || $scope === []) return false;
        return Capsule::table('op_rh_personal')
            ->where('id', $idPersonal)
            ->where('estado', 1)
            ->whereIn('id_estacion', $scope)
            ->exists();
    }

    public static function guardarComentario(int $idPersonal, int $year, int $idUsuario, string $comentario): array
    {
        $comentario = trim($comentario);
        if ($comentario === '' || mb_strlen($comentario) > 5000) {
            return ['success' => false, 'message' => 'Escribe un comentario de hasta 5,000 caracteres.'];
        }
        if (!self::puedePersonalEnContexto($idPersonal)) {
            return ['success' => false, 'message' => 'El empleado no pertenece al contexto seleccionado.'];
        }

        try {
            Capsule::table('op_rh_formatos_vacaciones_comentarios')->insert([
                'id_usuario_vacaciones' => $idPersonal,
                'year' => $year,
                'id_usuario' => $idUsuario,
                'comentario' => $comentario,
            ]);
        } catch (Throwable $e) {
            error_log('Vacaciones comentario: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo guardar el comentario.'];
        }

        self::notificarComentario($idPersonal, $year, $idUsuario, $comentario);
        return ['success' => true, 'message' => 'Comentario guardado.'];
    }

    private static function notificarComentario(int $idPersonal, int $year, int $idAutor, string $comentario): void
    {
        try {
            $formatoIds = Capsule::table('op_rh_formatos as f')
                ->join('op_rh_formatos_vacaciones as v', 'v.id_formulario', '=', 'f.id')
                ->where('f.formato', self::FORMATO)
                ->where('v.id_usuario', $idPersonal)
                ->whereYear('v.fecha_inicio', $year)
                ->pluck('f.id')
                ->map(static fn($id): int => (int)$id)
                ->all();

            $destinos = Capsule::table('op_rh_formatos_vacaciones_comentarios')
                ->where('id_usuario_vacaciones', $idPersonal)
                ->where('year', $year)
                ->where('id_usuario', '<>', $idAutor)
                ->pluck('id_usuario')
                ->map(static fn($id): int => (int)$id)
                ->all();

            if ($formatoIds !== []) {
                foreach (['op_rh_formatos_comentarios', 'op_rh_formatos_firma', 'op_rh_formatos_token'] as $table) {
                    $destinos = array_merge($destinos, Capsule::table($table)
                        ->whereIn('id_formato', $formatoIds)
                        ->where('id_usuario', '<>', $idAutor)
                        ->pluck('id_usuario')
                        ->map(static fn($id): int => (int)$id)
                        ->all());
                }
            }

            $destinos = array_values(array_unique(array_filter($destinos, static fn(int $id): bool => $id > 0 && $id !== $idAutor)));
            if ($destinos === []) return;

            $personal = Capsule::table('op_rh_personal')->where('id', $idPersonal)->first(['nombre_completo']);
            $mensaje = '💬 Se agregó un comentario en el seguimiento de vacaciones.' . PHP_EOL . PHP_EOL
                . '👤 <b>Personal:</b> ' . htmlspecialchars((string)($personal->nombre_completo ?? 'Personal'), ENT_QUOTES, 'UTF-8') . PHP_EOL
                . '📅 <b>Año:</b> ' . $year . PHP_EOL
                . '📝 <b>Comentario:</b> ' . htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8');
            (new TelegramService())->sendMessageToMultiple($destinos, $mensaje);
        } catch (Throwable $e) {
            error_log('Vacaciones Telegram comentario: ' . $e->getMessage());
        }
    }
}
