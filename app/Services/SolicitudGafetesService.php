<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\SolicitudGafetes;
use App\Services\TelegramService;

final class SolicitudGafetesService
{
    public const MODULO = 'solicitud-gafetes';
    public const PUESTOS_CAPTURA = [6, 7];
    public const PUESTOS_FINALIZACION = [13, 1];
    public static function puestoActual(): int
    {
        return (int) (Auth::user()?->id_puesto ?? 0);
    }

    public static function puedeAgregar(): bool
    {
        return in_array(self::puestoActual(), self::PUESTOS_CAPTURA, true);
    }

    public static function puedeEditar(int $estatus): bool
    {
        return self::puedeAgregar() && $estatus === 0;
    }

    public static function puedeAgregarRegistro(int $estatus): bool
    {
        return self::puedeAgregar() && in_array($estatus, [0, 1], true);
    }

    public static function puedeEliminar(int $estatus): bool
    {
        return ($estatus === 0 && in_array(self::puestoActual(), self::PUESTOS_CAPTURA, true))
            || ($estatus === 1 && in_array(self::puestoActual(), self::PUESTOS_FINALIZACION, true));
    }

    public static function puedeRegistrarPaso(int $estatus, int $paso): bool
    {
        return match ($paso) {
            1 => $estatus === 0 && self::puedeAgregar(),
            2 => $estatus === 1 && in_array(self::puestoActual(), self::PUESTOS_FINALIZACION, true),
            3 => in_array($estatus, [2, 3], true) && self::puedeAgregar(),
            default => false,
        };
    }

    public static function estatusActual(string $nombreEstacion, int $noReporte): ?int
    {
        $estatus = SolicitudGafetes::where('id_estacion', $nombreEstacion)
            ->where('no_reporte', $noReporte)
            ->max('estatus');
        return $estatus === null ? null : (int) $estatus;
    }

    public static function siguientePaso(int $estatus): ?int
    {
        return match ($estatus) {
            0 => 1,
            1 => 2,
            2, 3 => 3,
            default => null,
        };
    }

    /** @return array<int, array{id:int,nombre:string}> */
    public static function estacionesAutorizadas(): array
    {
        return array_map(static fn(array $estacion): array => [
            'id' => (int) $estacion['id'],
            'nombre' => (string) $estacion['nombre'],
        ], ModuleStationService::getAvailableStations(self::MODULO));
    }

    /**
     * Resuelve el contexto actual usando únicamente las estaciones permitidas.
     * Un contexto vacío significa "todas" solo si el módulo habilita allow_all.
     *
     * @return array{ids:array<int>,nombres:array<string>,seleccionada:?int,todas:bool,permitido:bool}
     */
    public static function alcanceActual(): array
    {
        $estaciones = self::estacionesAutorizadas();
        $porId = [];
        foreach ($estaciones as $estacion) {
            $porId[$estacion['id']] = $estacion['nombre'];
        }

        $contexto = ModuleStationService::getContext(self::MODULO);
        $config = ModuleStationService::getConfig(self::MODULO) ?? [];
        $seleccionada = isset($contexto['id_estacion']) ? (int) $contexto['id_estacion'] : null;

        if ($contexto['id_depto'] !== null) {
            return ['ids' => [], 'nombres' => [], 'seleccionada' => null, 'todas' => false, 'permitido' => false];
        }

        if ($seleccionada !== null) {
            if (!isset($porId[$seleccionada])) {
                return ['ids' => [], 'nombres' => [], 'seleccionada' => $seleccionada, 'todas' => false, 'permitido' => false];
            }
            return [
                'ids' => [$seleccionada],
                'nombres' => [$porId[$seleccionada]],
                'seleccionada' => $seleccionada,
                'todas' => false,
                'permitido' => true,
            ];
        }

        if (!empty($config['allow_all'])) {
            return [
                'ids' => array_keys($porId),
                'nombres' => array_values($porId),
                'seleccionada' => null,
                'todas' => true,
                'permitido' => $porId !== [],
            ];
        }

        $usuarioSesion = Session::get('usuario') ?? [];
        $idUsuarioEstacion = (int) ($usuarioSesion['id_estacion'] ?? Auth::user()?->id_gas ?? 0);
        if (isset($porId[$idUsuarioEstacion])) {
            return [
                'ids' => [$idUsuarioEstacion],
                'nombres' => [$porId[$idUsuarioEstacion]],
                'seleccionada' => $idUsuarioEstacion,
                'todas' => false,
                'permitido' => true,
            ];
        }

        return ['ids' => [], 'nombres' => [], 'seleccionada' => null, 'todas' => false, 'permitido' => false];
    }

    /** @return array<string, int> */
    public static function contarPendientesPorEstacion(): array
    {
        $estaciones = self::estacionesAutorizadas();
        $nombres = array_column($estaciones, 'nombre');
        $conteos = ['total' => 0];
        foreach ($estaciones as $estacion) {
            $conteos['estacion_' . $estacion['id']] = 0;
        }
        if ($nombres === []) {
            return $conteos;
        }

        $idPorNombre = array_column($estaciones, 'id', 'nombre');
        $grupos = SolicitudGafetes::query()
            ->select(['id_estacion', 'no_reporte'])
            ->whereIn('id_estacion', $nombres)
            ->where('estatus', 0)
            ->groupBy('id_estacion', 'no_reporte')
            ->get();

        foreach ($grupos as $grupo) {
            $id = $idPorNombre[(string) $grupo->id_estacion] ?? null;
            if ($id !== null) {
                $conteos['estacion_' . $id]++;
                $conteos['total']++;
            }
        }

        return $conteos;
    }

    /** Cuenta solicitudes únicas cuyo último estatus está entre los indicados. */
    public static function contarSolicitudesPorEstatus(array $estatuses, ?int $year = null): int
    {
        $nombres = array_column(self::estacionesAutorizadas(), 'nombre');
        if ($nombres === [] || $estatuses === []) {
            return 0;
        }
        $year ??= (int) date('Y');

        $solicitudes = SolicitudGafetes::query()
            ->selectRaw('id_estacion, no_reporte, MAX(estatus) AS estatus_actual')
            ->whereIn('id_estacion', $nombres)
            ->whereYear('fecha', $year)
            ->groupBy('id_estacion', 'no_reporte');

        return (int) SolicitudGafetes::query()
            ->fromSub($solicitudes, 'solicitudes_estatus')
            ->whereIn('estatus_actual', array_map('intval', $estatuses))
            ->count();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarSolicitudes(array $alcance): array
    {
        $nombres = $alcance['nombres'] ?? [];
        if (!$alcance['permitido'] || $nombres === []) {
            return [];
        }

        $idPorNombre = Estacion::query()
            ->whereIn('nombre', $nombres)
            ->get(['id', 'nombre'])
            ->mapWithKeys(static fn(Estacion $estacion): array => [(string) $estacion->nombre => (int) $estacion->id]);

        $grupos = SolicitudGafetes::query()
            ->selectRaw('MIN(id) AS id_reporte, id_estacion, no_reporte, MAX(fecha) AS fecha_reporte, MAX(usuario) AS nombre_usuario, MAX(comentarios) AS comentarios, MAX(estatus) AS estatus_reporte')
            ->whereIn('id_estacion', $nombres)
            ->groupBy('id_estacion', 'no_reporte')
            ->orderByRaw('MAX(id) DESC')
            ->get();

        return $grupos->map(static function ($grupo) use ($idPorNombre): array {
            $estatus = (int) $grupo->estatus_reporte;
            $siguientePaso = self::siguientePaso($estatus);
            [$titulo, $color] = match ($estatus) {
                0 => ['Sin atender', 'bg-danger'],
                1 => ['En proceso', 'bg-warning'],
                2, 3 => ['Finalizado', 'bg-success'],
                4 => ['Entregada', 'bg-info'],
                default => ['Desconocido', 'bg-secondary'],
            };
            $fecha = $grupo->fecha_reporte ? (string) $grupo->fecha_reporte : '';
            $fechaValida = $fecha !== '' && !in_array($fecha, ['0000-00-00', '-0001-11-30'], true);

            return [
                'idReporte' => (int) $grupo->id_reporte,
                'idEstacionReal' => (int) ($idPorNombre[(string) $grupo->id_estacion] ?? 0),
                'no_reporte' => (int) $grupo->no_reporte,
                'fecha_reporte' => $fechaValida ? $fecha : 'S/I',
                'fecha_reporte_formateada' => $fechaValida ? formatearFecha($fecha) : 'S/I',
                'nombre_usuario' => (string) ($grupo->nombre_usuario ?? ''),
                'comentarios' => trim((string) ($grupo->comentarios ?? '')) ?: 'Sin comentarios',
                'nombre_estacion' => (string) $grupo->id_estacion,
                'estatus_reporte' => $estatus,
                'estatus' => ['titulo' => $titulo, 'color_badge' => $color],
                'permisos' => [
                    'disableDetail' => false,
                    'disabledEdit' => !self::puedeEditar($estatus),
                    'disabledDelete' => !self::puedeEliminar($estatus),
                    'disabledStatus' => $siguientePaso === null || !self::puedeRegistrarPaso($estatus, $siguientePaso),
                    'siguientePaso' => $siguientePaso,
                ],
            ];
        })->all();
    }

    public static function estacionPermitida(int $idEstacion): ?Estacion
    {
        if (!in_array($idEstacion, array_column(self::estacionesAutorizadas(), 'id'), true)) {
            return null;
        }
        return Estacion::find($idEstacion);
    }

    public static function directorioArchivos(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'solicitud-gafetes' . DIRECTORY_SEPARATOR;
    }

    public static function notificar(string $accion, int $idEstacion, int $noReporte, int $idUsuario, string $detalle = ''): void
    {
        try {
            if (trim((string) telegramBotToken()) === '') {
                return;
            }
            $estacion = Estacion::find($idEstacion);
            $nombreEstacion = htmlspecialchars((string) ($estacion?->nombre ?? 'Estación'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $usuario = htmlspecialchars((string) (Auth::name() ?? 'Usuario'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $detalle = trim($detalle);
            $textoDetalle = $detalle === '' ? '' : "\n📝 Detalle: " . htmlspecialchars($detalle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $verbo = match ($accion) {
                'agregar' => 'agregó información a',
                'editar' => 'actualizó',
                'comentario' => 'actualizó el seguimiento de',
                'eliminar' => 'eliminó',
                default => 'actualizó',
            };
            $mensaje = "🎫 <b>Solicitud de Gafetes</b>\n"
                . "👤 Responsable: {$usuario}\n"
                . "📍 Estación: {$nombreEstacion}\n"
                . "🔢 Solicitud: #{$noReporte}\n"
                . "✅ Acción: {$verbo} la solicitud" . $textoDetalle;
            $telegram = new TelegramService();
            $telegram->sendMessageToMultiple($telegram->getUserIdsByStation($idEstacion, $idUsuario), $mensaje);
        } catch (\Throwable $error) {
            error_log('SolicitudGafetesService::notificar - ' . $error->getMessage());
        }
    }
}
