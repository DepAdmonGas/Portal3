<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as DB;

/** Distribuye recordatorios del calendario de Refacciones por estación y puesto. */
final class RefaccionesCalendarioRecordatorioService
{
    private const TIPOS_ESTACION = ['compra', 'mantenimiento_externo'];
    private const TIPOS_CORPORATIVO = ['compra', 'mantenimiento_externo', 'prueba', 'calibracion'];

    /** Envío manual: eventos pendientes del mes actual para las ubicaciones autorizadas. */
    public static function enviarManuales(): array
    {
        $user = Auth::user();
        if (!$user || (int) ($user->id_puesto ?? 0) !== 13
            || !MultiestacionService::isEnabled()
            || !ModuleStationService::isAvailable(RefaccionesService::MODULE_KEY)
            || !RefaccionesService::getPermisos()['editar']) {
            return self::result(false, 'No tienes permiso para enviar recordatorios del calendario.', 403);
        }

        $today = new \DateTimeImmutable('today');
        $start = $today->modify('first day of this month')->format('Y-m-d');
        $end = $today->modify('last day of this month')->format('Y-m-d');
        $locations = RefaccionesService::getUbicacionesDisponibles();
        if ($locations === []) return self::result(false, 'No hay ubicaciones autorizadas para procesar recordatorios.', 403);

        try {
            $eventQuery = DB::table('op_refacciones_calendario')->where('estado', 'pendiente')
                ->whereBetween('fecha', [$start, $end])
                ->whereIn('tipo_evento', self::TIPOS_CORPORATIVO);
            self::filtrarPorUbicaciones($eventQuery, array_map('intval', array_keys($locations)));
            $events = self::adjuntarUbicaciones($eventQuery->orderBy('fecha')->orderBy('hora')->get()->map(static fn ($row): array => (array) $row)->all());
            if ($events === []) return self::result(true, 'No hay eventos pendientes este mes para recordar.', 200, ['enviados' => 0, 'fallidos' => 0]);

            $recipients = self::buildRecipientEvents($events);
            $emailService = new EmailService();
            $telegram = new TelegramService();
            $sent = 0;
            $failed = 0;
            foreach ($recipients as $recipient) {
                $body = self::buildMessages($recipient['eventos'], $today);
                if ($recipient['email'] !== '' && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                    if ($emailService->sendHtml($recipient['email'], 'Recordatorios de eventos pendientes — Calendario de Refacciones', $body['html'], $body['text'])) $sent++;
                    else $failed++;
                } else {
                    $failed++;
                }
                if ($telegram->sendToken((int) $recipient['id'], $body['telegram'])) $sent++;
                else $failed++;
            }
            if ($sent === 0) return self::result(false, 'No se pudo enviar el recordatorio por correo ni por Telegram.', 502, ['enviados' => 0, 'fallidos' => $failed]);
            return self::result(true, $failed > 0
                ? "Se enviaron {$sent} recordatorios; {$failed} envíos no se pudieron completar."
                : "Se enviaron {$sent} recordatorios por correo y Telegram.", 200, ['enviados' => $sent, 'fallidos' => $failed]);
        } catch (\Throwable $error) {
            error_log('[RefaccionesCalendarioRecordatorio] manual: ' . $error->getMessage());
            return self::result(false, 'No fue posible enviar los recordatorios del calendario.', 500);
        }
    }

    /** Envía sólo eventos pendientes que ocurren dentro de dos días; la tabla evita duplicados diarios. */
    public static function enviarAutomaticos(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $sendDate = $today->format('Y-m-d');
        $eventDate = $today->modify('+2 days')->format('Y-m-d');
        try {
            $configuredLocations = array_map('intval', RefaccionesService::getUbicacionesConfiguradas());
            if ($configuredLocations === []) return self::result(true, 'No hay ubicaciones configuradas para el calendario.', 200, ['eventos' => 0, 'enviados' => 0, 'fallidos' => 0]);
            $eventQuery = DB::table('op_refacciones_calendario')->where('estado', 'pendiente')
                ->whereDate('fecha', $eventDate)->whereIn('tipo_evento', self::TIPOS_CORPORATIVO);
            self::filtrarPorUbicaciones($eventQuery, $configuredLocations);
            $events = self::adjuntarUbicaciones($eventQuery->orderBy('fecha')->orderBy('hora')->get()->map(static fn ($row): array => (array) $row)->all());
            $sent = 0;
            $failed = 0;
            $recipients = self::buildRecipientEvents($events);
            foreach ($recipients as $recipient) {
                foreach ($recipient['eventos'] as $event) {
                    $body = self::buildMessages([$event], $today);
                    if ($recipient['email'] !== '' && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                        $result = self::sendOnce($event, (int) $recipient['id'], 'correo', $sendDate, static fn (): bool => (new EmailService())->sendHtml(
                            $recipient['email'],
                            'Recordatorio de evento pendiente — Calendario de Refacciones',
                            $body['html'],
                            $body['text']
                        ));
                        if ($result === true) $sent++; elseif ($result === false) $failed++;
                    }
                    $result = self::sendOnce($event, (int) $recipient['id'], 'telegram', $sendDate, static fn (): bool => (new TelegramService())->sendToken(
                        (int) $recipient['id'],
                        $body['telegram']
                    ));
                    if ($result === true) $sent++; elseif ($result === false) $failed++;
                }
            }
            return self::result($failed === 0, $events === []
                ? 'No hay eventos pendientes para recordar dentro de dos días.'
                : "Proceso automático terminado: {$sent} avisos enviados y {$failed} fallidos.", 200, ['eventos' => count($events), 'enviados' => $sent, 'fallidos' => $failed]);
        } catch (\Throwable $error) {
            error_log('[RefaccionesCalendarioRecordatorio] automático: ' . $error->getMessage());
            return self::result(false, 'No fue posible procesar los recordatorios automáticos.', 500);
        }
    }

    /** @return array<int, array{id:int,email:string,eventos:array<int,array<string,mixed>>}> */
    private static function buildRecipientEvents(array $events): array
    {
        if ($events === []) return [];
        $locationIds = array_values(array_unique(array_merge(...array_map(static fn (array $event): array => $event['id_estaciones'] ?? [], $events))));
        $stationIds = array_values(array_unique(array_map(static fn (int $id): int => $id === 9 ? 2 : $id, $locationIds)));
        $users = DB::table('tb_usuarios')->where('estatus', 0)
            ->where(static function ($query) use ($stationIds): void {
                $query->where('id_puesto', 13)->orWhere(static function ($stationUsers) use ($stationIds): void {
                    $stationUsers->where('id_puesto', 6)->whereIn('id_gas', $stationIds);
                });
            })->get(['id', 'nombre', 'email', 'id_gas', 'id_puesto']);
        $byId = [];
        foreach ($users as $row) $byId[(int) $row->id] = $row;
        $result = [];
        foreach ($events as $event) {
            $type = (string) ($event['tipo_evento'] ?? '');
            if (!in_array($type, self::TIPOS_CORPORATIVO, true)) continue;
            $eventLocationIds = array_map(static fn (int $locationId): int => $locationId === 9 ? 2 : $locationId, $event['id_estaciones'] ?? []);
            foreach ($byId as $userId => $user) {
                $position = (int) $user->id_puesto;
                $eligible = $position === 13
                    || (in_array($type, self::TIPOS_ESTACION, true) && $position === 6 && in_array((int) $user->id_gas, $eventLocationIds, true));
                if (!$eligible) continue;
                if (!isset($result[$userId])) $result[$userId] = ['id' => $userId, 'email' => trim((string) ($user->email ?? '')), 'eventos' => []];
                $result[$userId]['eventos'][] = $event;
            }
        }
        return array_values($result);
    }

    private static function calendarioTieneUbicacionesTabla(): bool
    {
        try {
            return DB::schema()->hasTable('op_refacciones_calendario_ubicaciones');
        } catch (\Throwable $error) {
            return false;
        }
    }

    private static function filtrarPorUbicaciones($query, array $ids): void
    {
        $query->where(static function ($scope) use ($ids): void {
            $scope->whereIn('id_estacion', $ids);
            if (self::calendarioTieneUbicacionesTabla()) {
                $scope->orWhereExists(static function ($related) use ($ids): void {
                    $related->select(DB::raw('1'))->from('op_refacciones_calendario_ubicaciones as cu')
                        ->whereColumn('cu.id_evento', 'op_refacciones_calendario.id')
                        ->whereIn('cu.id_estacion', $ids);
                });
            }
        });
    }

    /** Attach all selected locations to calendar rows while retaining legacy single-location events. */
    private static function adjuntarUbicaciones(array $events): array
    {
        if ($events === []) return [];
        $eventIds = array_map(static fn (array $event): int => (int) ($event['id'] ?? 0), $events);
        $byEvent = [];
        if (self::calendarioTieneUbicacionesTabla()) {
            foreach (DB::table('op_refacciones_calendario_ubicaciones')->whereIn('id_evento', $eventIds)->get(['id_evento', 'id_estacion']) as $row) {
                $byEvent[(int) $row->id_evento][] = (int) $row->id_estacion;
            }
        }
        foreach ($events as &$event) {
            $id = (int) ($event['id'] ?? 0);
            $legacy = (int) ($event['id_estacion'] ?? 0);
            $ids = array_values(array_unique($byEvent[$id] ?? ($legacy > 0 ? [$legacy] : [])));
            $event['id_estaciones'] = $ids;
        }
        unset($event);
        return $events;
    }

    private static function sendOnce(array $event, int $userId, string $channel, string $sendDate, callable $send): ?bool
    {
        $eventId = (int) ($event['id'] ?? 0);
        // Reservar antes de llamar al proveedor externo: una ejecución concurrente no duplica el envío.
        $inserted = DB::table('op_refacciones_calendario_recordatorios')->insertOrIgnore([
            'id_evento' => $eventId, 'id_usuario' => $userId, 'canal' => $channel,
            'fecha_envio' => $sendDate, 'estado' => 'procesando', 'creado_en' => date('Y-m-d H:i:s'),
        ]);
        if ($inserted === 0) return null;
        $logId = (int) DB::table('op_refacciones_calendario_recordatorios')->where('id_evento', $eventId)
            ->where('id_usuario', $userId)->where('canal', $channel)->where('fecha_envio', $sendDate)->value('id');
        $ok = false;
        try { $ok = (bool) $send(); } catch (\Throwable $error) {
            error_log('[RefaccionesCalendarioRecordatorio] ' . $channel . ': ' . $error->getMessage());
        }
        DB::table('op_refacciones_calendario_recordatorios')->where('id', $logId)->update([
            'estado' => $ok ? 'enviado' : 'fallido', 'procesado_en' => date('Y-m-d H:i:s'),
        ]);
        return $ok;
    }

    private static function buildMessages(array $events, \DateTimeImmutable $today): array
    {
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $types = ['compra' => 'Compras programadas', 'mantenimiento_externo' => 'Mantenimiento por proveedor externo', 'prueba' => 'Registro, preparación y pruebas', 'calibracion' => 'Calibración'];
        $locationIds = array_values(array_unique(array_merge(...array_map(static fn (array $event): array => $event['id_estaciones'] ?? [], $events))));
        $locationNames = $locationIds === [] ? [] : DB::table('op_rh_localidades')
            ->whereIn('id', $locationIds)->pluck('localidad', 'id')->all();
        $htmlRows = '';
        $textRows = [];
        $telegramRows = [];
        foreach ($events as $event) {
            $date = new \DateTimeImmutable((string) ($event['fecha'] ?? 'now'));
            $overdue = $date < $today;
            $title = trim((string) ($event['titulo'] ?? 'Evento'));
            $eventLocations = array_map(static fn (int $id): string => (string) ($locationNames[$id] ?? 'Ubicación'), $event['id_estaciones'] ?? []);
            $location = $eventLocations === [] ? 'Ubicación' : implode(', ', $eventLocations);
            $dateLabel = $date->format('d/m/Y');
            $typeLabel = $types[(string) ($event['tipo_evento'] ?? '')] ?? 'Evento';
            $stateText = $overdue ? 'El evento sigue pendiente.' : 'Evento pendiente.';
            $htmlRows .= '<li style="margin:0 0 12px"><strong>' . $escape($title) . '</strong><br>'
                . $escape($typeLabel) . ' · ' . $escape($location) . '<br>'
                . 'Fecha: ' . $escape($dateLabel) . ($event['hora'] ? ' · Hora: ' . $escape(substr((string) $event['hora'], 0, 5)) : '')
                . '<br><strong>' . $escape($stateText) . '</strong></li>';
            $textRows[] = $title . ' — ' . $typeLabel . ' — ' . $location . ' — ' . $dateLabel . ': ' . $stateText;
            $telegramRows[] = '📌 <b>' . $escape($title) . '</b>'
                . "\n🗂 <b>Tipo:</b> " . $escape($typeLabel)
                . "\n📍 <b>Estación/departamento:</b> " . $escape($location)
                . "\n📅 <b>Fecha:</b> " . $escape($dateLabel)
                . (!empty($event['hora']) ? "\n🕒 <b>Hora:</b> " . $escape(substr((string) $event['hora'], 0, 5)) : '')
                . "\n" . ($overdue ? '⚠️ <b>El evento sigue pendiente.</b>' : '🔔 <b>Evento pendiente.</b>');
        }
        $html = '<p>Hola,</p><p>Estos son los recordatorios de eventos pendientes del <strong>Calendario de Refacciones</strong>:</p><ul>' . $htmlRows . '</ul><p>Almacén AdmonGas</p>';
        $text = "Hola,\n\nRecordatorios de eventos pendientes del Calendario de Refacciones:\n\n" . implode("\n", $textRows) . "\n\nAlmacén AdmonGas";
        return [
            'html' => $html,
            'text' => $text,
            'telegram' => "🔔 <b>RECORDATORIOS DEL CALENDARIO DE REFACCIONES</b>\n📋 Eventos pendientes\n\n" . implode("\n\n──────────────\n\n", $telegramRows),
        ];
    }

    private static function result(bool $success, string $message, int $code, array $extra = []): array
    {
        return array_merge(['success' => $success, 'message' => $message, 'code' => $code], $extra);
    }
}
