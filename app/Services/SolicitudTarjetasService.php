<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\Estacion;
use App\Models\SolicitudTarjetas;

/** Reglas de acceso y estado para Solicitud de Tarjetas. */
final class SolicitudTarjetasService
{
    public static function puesto(): int
    {
        return (int) (Auth::user()->id_puesto ?? 0);
    }

    public static function esCaptura(): bool
    {
        return in_array(self::puesto(), [6, 7], true);
    }

    public static function esFinalizacion(): bool
    {
        return in_array(self::puesto(), [1, 13], true);
    }

    private static function moduloPermite(string $accion): bool
    {
        return ModuloService::validaPermiso('solicitud-tarjetas', $accion);
    }

    public static function puedeLeer(): bool
    {
        return (self::esCaptura() || self::esFinalizacion()) && self::moduloPermite('leer');
    }

    public static function puedeAgregar(): bool
    {
        return self::esCaptura() && self::moduloPermite('crear');
    }

    public static function puedeEditar(int $estatus): bool
    {
        return self::esCaptura() && $estatus === 0 && self::moduloPermite('editar');
    }

    public static function puedeEliminar(int $estatus): bool
    {
        return ((self::esCaptura() && $estatus === 0) || (self::esFinalizacion() && $estatus === 1))
            && self::moduloPermite('eliminar');
    }

    public static function siguientePaso(int $estatus): ?int
    {
        if (self::esCaptura() && $estatus === 0 && self::moduloPermite('editar')) return 1;
        if (self::esFinalizacion() && $estatus === 1 && self::moduloPermite('editar')) return 2;
        if (self::esCaptura() && in_array($estatus, [2, 3], true) && self::moduloPermite('editar')) return 3;
        return null;
    }

    public static function puedeDescargar(): bool
    {
        return (self::esCaptura() || self::esFinalizacion()) && self::moduloPermite('descargar');
    }

    public static function contarPendientesPorEstacion(): array
    {
        $stations = ModuleStationService::getAvailableStations('solicitud-tarjetas');
        $result = ['total' => 0];
        foreach ($stations as $station) $result['estacion_' . (int)$station['id']] = 0;
        $names = array_column($stations, 'nombre');
        if (!$names) return $result;
        $idByName = array_column($stations, 'id', 'nombre');
        $groups = SolicitudTarjetas::query()->select(['id_estacion', 'no_solicitud'])
            ->whereIn('id_estacion', $names)->where('estatus', 0)
            ->groupBy('id_estacion', 'no_solicitud')->get();
        foreach ($groups as $group) {
            $id = $idByName[$group->id_estacion] ?? null;
            if ($id !== null) { $result['estacion_' . (int)$id]++; $result['total']++; }
        }
        return $result;
    }

    /** Cuenta solicitudes únicas cuyo último estatus está entre los indicados. */
    public static function contarSolicitudesPorEstatus(array $estatuses, ?int $year = null): int
    {
        $names = array_column(ModuleStationService::getAvailableStations('solicitud-tarjetas'), 'nombre');
        if ($names === [] || $estatuses === []) return 0;
        $year ??= (int) date('Y');

        $solicitudes = SolicitudTarjetas::query()->selectRaw('id_estacion, no_solicitud, MAX(estatus) AS estatus_actual')
            ->whereIn('id_estacion', $names)
            ->whereYear('fecha', $year)
            ->groupBy('id_estacion', 'no_solicitud');

        return (int) SolicitudTarjetas::query()->fromSub($solicitudes, 'solicitudes_estatus')
            ->whereIn('estatus_actual', array_map('intval', $estatuses))
            ->count();
    }

    /** Resuelve estación URL/selector y valida contra la configuración del módulo. */
    public static function estacion(int $id): ?Estacion
    {
        if ($id < 1) return null;
        $allowed = ModuleStationService::getAvailableStations('solicitud-tarjetas');
        if (!in_array($id, array_map('intval', array_column($allowed, 'id')), true)) return null;
        return Estacion::find($id);
    }

    /** null significa todas las estaciones, permitido solo si la configuración lo autoriza. */
    public static function contexto(): array
    {
        $cfg = ModuleStationService::getConfig('solicitud-tarjetas');
        $ctx = ModuleStationService::getContext('solicitud-tarjetas');
        if (!$cfg || !empty($ctx['id_depto'])) return ['allowed' => false, 'all' => false, 'station' => null];

        $available = ModuleStationService::getAvailableStations('solicitud-tarjetas');
        $ids = array_map('intval', array_column($available, 'id'));
        if (!empty($ctx['id_estacion'])) {
            $station = self::estacion((int)$ctx['id_estacion']);
            return ['allowed' => $station !== null, 'all' => false, 'station' => $station];
        }

        $user = Auth::user();
        $multi = MultiestacionService::isEnabled($user);
        if (!empty($cfg['allow_all']) && $multi) {
            return ['allowed' => true, 'all' => true, 'station' => null];
        }

        // Los usuarios con una sola estación conservan el contexto predeterminado del módulo.
        $idGas = (int) (self::idGas() ?? 0);
        $station = $idGas ? self::estacion($idGas) : null;
        return ['allowed' => $station !== null, 'all' => false, 'station' => $station];
    }

    public static function idGas(): ?int
    {
        $user = Auth::user();
        return isset($user->id_gas) ? (int)$user->id_gas : null;
    }

    public static function solicitud(int $idEstacion, int $noSolicitud): ?SolicitudTarjetas
    {
        $context = self::contexto();
        if (!$context['allowed'] || (!$context['all'] && (int)($context['station']->id ?? 0) !== $idEstacion)) return null;
        $station = self::estacion($idEstacion);
        if (!$station || $noSolicitud < 1) return null;
        return SolicitudTarjetas::where('id_estacion', $station->nombre)
            ->where('no_solicitud', $noSolicitud)->orderByDesc('estatus')->orderByDesc('id')->first();
    }

    public static function notificar(string $accion, int $idEstacion, int $noSolicitud, string $detalle = ''): void
    {
        try {
            if (trim((string)telegramBotToken()) === '') return;
            $station = Estacion::find($idEstacion);
            $stationName = htmlspecialchars((string)($station->nombre ?? 'Estación'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $userName = htmlspecialchars((string)(Auth::user()->nombre ?? 'Usuario'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $verbo = [
                'agregar' => 'creado una solicitud',
                'editar' => 'actualizado una solicitud',
                'eliminar' => 'eliminado una solicitud',
                'estatus' => 'actualizado el estatus de la solicitud',
                'finalizar' => 'finalizado la revisión de la solicitud',
            ][$accion] ?? 'actualizado la solicitud';
            $detalle = trim($detalle);
            $safeDetail = $detalle === '' ? '' : PHP_EOL . '📝 <b>Detalle:</b> ' . htmlspecialchars($detalle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message = '🎫 Se ha ' . $verbo . ' en el apartado de <b>Solicitud de Tarjetas</b>:' . PHP_EOL . PHP_EOL
                . '🔢 <b>Folio:</b> #' . $noSolicitud . $safeDetail
                . PHP_EOL . PHP_EOL . '👤 <b>Responsable:</b> ' . $userName
                . PHP_EOL . '⛽ <b>Estación:</b> ' . $stationName;
            $telegram = new TelegramService();
            $telegram->sendMessageToMultiple($telegram->getUserIdsByStation($idEstacion, (int)(Auth::id() ?? 0)), $message);
        } catch (\Throwable $error) {
            error_log('SolicitudTarjetasService::notificar - ' . $error->getMessage());
        }
    }
}
