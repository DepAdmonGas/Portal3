<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as DB;
use Throwable;

class RefaccionesSolicitudCalcomaniasService
{
    private const TABLA = 'op_refacciones_solicitudes_calcomanias';
    private const DETALLE = 'op_refacciones_solicitudes_calcomanias_detalle';
    private const TABLA_TOKEN = 'op_refacciones_solicitudes_calcomanias_token';

    public static function listar(): array
    {
        $ids = array_map('intval', array_keys(RefaccionesService::getUbicacionesDisponibles()));
        if ($ids === []) return [];
        $contexto = RefaccionesService::getContexto()['id'];
        $query = DB::table(self::TABLA)->whereIn('id_ubicacion', $ids);
        if ($contexto !== null) $query->where('id_ubicacion', (int) $contexto);
        return $query->orderByDesc('fecha_hora')->get()->map(static function ($row): array {
            return (array) $row;
        })->all();
    }

    /** Total pendiente para el usuario que corresponda en el sidebar de Refacciones. */
    public static function contarPendientesSidebar(): int
    {
        $ids = array_map('intval', array_keys(RefaccionesService::getUbicacionesDisponibles()));
        if ($ids === []) return 0;

        $estadoPendiente = (int) (Auth::user()->id ?? 0) === 19 ? 1 : 0;
        return (int) DB::table(self::TABLA)
            ->whereIn('id_ubicacion', $ids)
            ->where('estado', $estadoPendiente)
            ->count();
    }

    /** Solicitudes en proceso pendientes de autorización para el usuario 19. */
    public static function pendingSignatureItems(int $userId): array
    {
        if ($userId !== 19) return [];

        // Solicitud de calcomanías es una sección interna del menú de
        // Refacciones; no está registrada como submenú estándar de Almacén.
        // Validamos el permiso del módulo padre y dejamos que el alcance de
        // ModuleStationService limite las ubicaciones consultadas.
        $moduleAccess = ModuloDptoOperativoService::getPermiso($userId, RefaccionesService::PARENT_MODULE);
        if (empty($moduleAccess['leer'])) return [];

        $locations = RefaccionesService::getUbicacionesDisponibles();
        if ($locations === []) return [];

        return DB::table(self::TABLA)
            ->whereIn('id_ubicacion', array_map('intval', array_keys($locations)))
            ->where('estado', 1)
            ->where(function ($query): void {
                $query->whereNull('firma_autorizacion')->orWhere('firma_autorizacion', '');
            })
            ->orderBy('fecha_hora')
            ->get(['id', 'id_ubicacion', 'nombre_personal', 'fecha_hora'])
            ->map(static function ($row) use ($locations): array {
                $id = (int) $row->id;
                $ubicacion = (string) ($locations[(int) $row->id_ubicacion] ?? '');
                return [
                    'tipo' => 'SolicitudCalcomanias',
                    'tipo_label' => 'Solicitud de Calcomanías',
                    'id' => $id,
                    'fecha' => formatearFecha($row->fecha_hora ?? null),
                    'concepto' => 'Solicitud de calcomanías (#00' . $id . ') — ' . trim((string) $row->nombre_personal),
                    'beneficiario' => null,
                    'monto' => null,
                    'url' => '/departamento-operativo/almacen/refacciones/solicitud-calcomanias-firma/' . $id,
                    'disponible' => true,
                    'aviso' => null,
                    'ubicacion' => $ubicacion,
                ];
            })
            ->all();
    }

    public static function obtener(int $id): ?array
    {
        $disponibles = RefaccionesService::getUbicacionesDisponibles();
        $ids = array_map('intval', array_keys($disponibles));
        if ($ids === []) return null;
        $row = DB::table(self::TABLA)->where('id', $id)->whereIn('id_ubicacion', $ids)->first();
        if (!$row) return null;
        $data = (array) $row;
        $contexto = RefaccionesService::getContexto()['id'];
        $esAutorizacionPendiente = (int) (Auth::user()->id ?? 0) === 19
            && (int) ($data['estado'] ?? -1) === 1
            && trim((string) ($data['firma_autorizacion'] ?? '')) === '';
        if (!$esAutorizacionPendiente && MultiestacionService::isEnabled() && $contexto !== null && (int) $data['id_ubicacion'] !== (int) $contexto) {
            return null;
        }
        $idUsuarioAutorizacion = (int) ($data['id_usuario_autorizacion'] ?? 0);
        $usuarioAutorizacion = $idUsuarioAutorizacion > 0
            ? Usuario::query()->select(['id', 'nombre'])->find($idUsuarioAutorizacion)
            : null;
        $data['nombre_usuario_autorizacion'] = $usuarioAutorizacion?->nombre ?? 'Sin nombre registrado';
        $data['firma_autorizacion_descripcion'] = trim((string) ($data['firma_autorizacion'] ?? '')) !== ''
            ? 'La solicitud de calcomanías se firmó por un medio electrónico.'
            : '';
        $data['nombre_ubicacion'] = $disponibles[(int) $data['id_ubicacion']] ?? 'Ubicación';
        $data['detalle'] = DB::table(self::DETALLE . ' as d')
            ->where('d.id_solicitud', $id)
            ->orderBy('d.id')
            ->get(['d.id', 'd.id_calcomania', 'd.cantidad', 'd.nombre_calcomania', 'd.sku_calcomania', 'd.dimension', 'd.acabado', 'd.reproduccion'])
            ->map(static fn ($item): array => (array) $item)->all();
        return $data;
    }

    public static function eliminarSolicitud(int $id): array
    {
        $solicitud = self::obtener($id);
        if (!$solicitud) return ['success' => false, 'message' => 'La solicitud no está disponible.', 'code' => 404];

        $multiestacion = MultiestacionService::isEnabled();
        $estado = (int) ($solicitud['estado'] ?? -1);
        $userId = (int) (Auth::user()->id ?? 0);
        if ($multiestacion) {
            if (!RefaccionesService::getPermisos()['eliminar'] || !in_array($estado, [0, 1], true)) {
                return ['success' => false, 'message' => 'No tienes permiso para eliminar esta solicitud.', 'code' => 403];
            }
        } elseif ($estado !== 0 || (int) ($solicitud['id_usuario'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Sólo puedes eliminar tus solicitudes pendientes.', 'code' => 403];
        }

        try {
            DB::connection()->transaction(static function () use ($id, $estado): void {
                $query = DB::table(self::TABLA)->where('id', $id)->whereIn('estado', $estado === 1 ? [0, 1] : [0]);
                if ($query->lockForUpdate()->first() === null) throw new \RuntimeException('La solicitud cambió de estado y ya no se puede eliminar.');
                DB::table(self::DETALLE)->where('id_solicitud', $id)->delete();
                DB::table(self::TABLA)->where('id', $id)->delete();
            });
            return ['success' => true, 'message' => 'Solicitud eliminada correctamente.'];
        } catch (Throwable $e) {
            error_log('RefaccionesSolicitudCalcomaniasService::eliminarSolicitud: ' . $e->getMessage());
            return ['success' => false, 'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible eliminar la solicitud.', 'code' => 409];
        }
    }

    public static function crearBorrador(): array
    {
        $user = Auth::user();
        $idUsuario = (int) ($user->id ?? 0);
        $idUbicacion = RefaccionesService::getContexto()['id'];
        if ($idUsuario < 1 || $idUbicacion === false || $idUbicacion < 1
            || !RefaccionesService::puedeCrearSolicitudCalcomanias((int) $idUbicacion)) {
            return ['success' => false, 'message' => 'No tienes acceso para crear una solicitud en esa ubicación.', 'code' => 403];
        }

        $nombre = trim((string) ($user->nombre_completo ?? $user->nombre ?? ''));
        if ($nombre === '') $nombre = (string) ($user->name ?? 'Usuario');
        $puesto = (string) ($user->puesto->tipo_puesto ?? '');
        try {
            $id = (int) DB::table(self::TABLA)->insertGetId([
                    'id_ubicacion' => (int) $idUbicacion,
                    'id_usuario' => $idUsuario,
                    'nombre_personal' => mb_substr($nombre, 0, 180),
                    'puesto' => mb_substr($puesto, 0, 120),
                    'fecha_hora' => date('Y-m-d H:i:s'),
                    'estado' => 0,
                    'creado_en' => date('Y-m-d H:i:s'),
                    'actualizado_en' => date('Y-m-d H:i:s'),
                ]);
            return ['success' => true, 'message' => 'Solicitud creada.', 'id' => $id, 'url' => '/departamento-operativo/almacen/refacciones/solicitud-calcomanias/' . $id];
        } catch (Throwable $e) {
            error_log('RefaccionesSolicitudCalcomaniasService::crearBorrador: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No fue posible guardar la solicitud.', 'code' => 500];
        }
    }

    public static function agregarCalcomania(array $input): array
    {
        $idSolicitud = filter_var($input['id_solicitud'] ?? null, FILTER_VALIDATE_INT);
        $idCatalogo = filter_var($input['id_calcomania'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $userId = (int) (Auth::user()->id ?? 0);
        if ($idSolicitud === false || $idSolicitud < 1 || $idCatalogo === false || $idCatalogo < 1
            || $cantidad === false || $cantidad < 1 || $cantidad > 100000) {
            return ['success' => false, 'message' => 'Selecciona una calcomanía e indica una cantidad válida.', 'code' => 422];
        }
        $solicitud = self::obtener((int) $idSolicitud);
        if (!$solicitud || (int) $solicitud['id_usuario'] !== $userId || (int) $solicitud['estado'] !== 0) {
            return ['success' => false, 'message' => 'La solicitud ya no está disponible para edición.', 'code' => 403];
        }
        $producto = DB::table('op_refacciones_catalogo as c')
            ->join('op_refacciones_calcomanias as cal', 'cal.id_refaccion', '=', 'c.id')
            ->where('c.id', (int) $idCatalogo)->where('c.tipo_articulo', 'calcomania')->where('c.activo', 1)
            ->first(['c.id', 'c.sku', 'c.nombre', 'cal.dimension', 'cal.acabado', 'cal.reproduccion']);
        if (!$producto) return ['success' => false, 'message' => 'La calcomanía ya no está disponible.', 'code' => 404];
        try {
            $existing = DB::table(self::DETALLE)->where('id_solicitud', (int) $idSolicitud)->where('id_calcomania', (int) $idCatalogo)->first();
            if ($existing) {
                DB::table(self::DETALLE)->where('id', (int) $existing->id)->update(['cantidad' => (int) $existing->cantidad + (int) $cantidad]);
            } else {
                DB::table(self::DETALLE)->insert([
                    'id_solicitud' => (int) $idSolicitud, 'id_calcomania' => (int) $idCatalogo,
                    'sku_calcomania' => (string) $producto->sku, 'nombre_calcomania' => (string) $producto->nombre,
                    'dimension' => (string) ($producto->dimension ?? ''), 'acabado' => (string) ($producto->acabado ?? ''),
                    'reproduccion' => (string) ($producto->reproduccion ?? ''), 'cantidad' => (int) $cantidad,
                ]);
            }
            DB::table(self::TABLA)->where('id', (int) $idSolicitud)->where('estado', 0)->update(['actualizado_en' => date('Y-m-d H:i:s')]);
            return ['success' => true, 'message' => 'Calcomanía agregada.', 'solicitud' => self::obtener((int) $idSolicitud)];
        } catch (Throwable $e) {
            error_log('RefaccionesSolicitudCalcomaniasService::agregarCalcomania: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No fue posible agregar la calcomanía.', 'code' => 500];
        }
    }

    public static function eliminarCalcomania(array $input): array
    {
        $idSolicitud = filter_var($input['id_solicitud'] ?? null, FILTER_VALIDATE_INT);
        $idDetalle = filter_var($input['id_detalle'] ?? null, FILTER_VALIDATE_INT);
        $solicitud = $idSolicitud !== false ? self::obtener((int) $idSolicitud) : null;
        if (!$solicitud || $idDetalle === false || $idDetalle < 1
            || (int) $solicitud['id_usuario'] !== (int) (Auth::user()->id ?? 0) || (int) $solicitud['estado'] !== 0) {
            return ['success' => false, 'message' => 'La solicitud ya no está disponible para edición.', 'code' => 403];
        }
        $deleted = DB::table(self::DETALLE)->where('id', (int) $idDetalle)->where('id_solicitud', (int) $idSolicitud)->delete();
        if (!$deleted) return ['success' => false, 'message' => 'La calcomanía ya no está en la solicitud.', 'code' => 404];
        return ['success' => true, 'message' => 'Calcomanía eliminada.'];
    }

    public static function actualizarObservaciones(array $input): array
    {
        $id = filter_var($input['id_solicitud'] ?? null, FILTER_VALIDATE_INT);
        $observaciones = trim((string) ($input['observaciones'] ?? ''));
        $solicitud = $id !== false ? self::obtener((int) $id) : null;
        if (!$solicitud || (int) $solicitud['id_usuario'] !== (int) (Auth::user()->id ?? 0) || (int) $solicitud['estado'] !== 0) {
            return ['success' => false, 'message' => 'La solicitud ya no está disponible para edición.', 'code' => 403];
        }
        if (mb_strlen($observaciones) > 5000) return ['success' => false, 'message' => 'Las observaciones exceden el máximo permitido.', 'code' => 422];
        DB::table(self::TABLA)->where('id', (int) $id)->where('estado', 0)->update([
            'observaciones' => $observaciones !== '' ? $observaciones : null, 'actualizado_en' => date('Y-m-d H:i:s'),
        ]);
        return ['success' => true, 'message' => 'Observaciones guardadas.'];
    }

    public static function firmarEncargado(array $input): array
    {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $userId = (int) (Auth::user()->id ?? 0);
        $firma = self::validarFirma((string) ($input['firma'] ?? ''));
        if ($id === false || !$firma) return ['success' => false, 'message' => 'La firma es obligatoria y debe ser válida.', 'code' => 422];
        $solicitud = self::obtener((int) $id);
        if (!$solicitud || (int) $solicitud['id_usuario'] !== $userId || (int) $solicitud['estado'] !== 0) {
            return ['success' => false, 'message' => 'No tienes permiso para firmar esta solicitud.', 'code' => 403];
        }
        if (($solicitud['detalle'] ?? []) === []) {
            return ['success' => false, 'message' => 'Agrega al menos una calcomanía antes de finalizar.', 'code' => 422];
        }
        $updated = DB::table(self::TABLA)->where('id', (int) $id)->where('estado', 0)->update([
            'firma_encargado' => $firma, 'firmado_encargado_en' => date('Y-m-d H:i:s'),
            'estado' => 1, 'actualizado_en' => date('Y-m-d H:i:s'),
        ]);
        if (!$updated) return ['success' => false, 'message' => 'La solicitud ya cambió de estado; actualiza la página.', 'code' => 409];
        return ['success' => true, 'message' => 'La solicitud quedó en proceso de autorización.'];
    }

    public static function crearTokenAutorizacion(array $input): array
    {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $via = strtolower(trim((string) ($input['via'] ?? 'telegram')));
        if ((int) (Auth::user()->id ?? 0) !== 19) return ['success' => false, 'message' => 'Solo el usuario autorizado puede solicitar el token.', 'code' => 403];
        if (!in_array($via, ['telegram', 'email'], true)) return ['success' => false, 'message' => 'Selecciona un medio válido para recibir el token.', 'code' => 422];
        $solicitud = $id !== false && $id > 0 ? self::obtener((int) $id) : null;
        if (!$solicitud || (int) $solicitud['estado'] !== 1) return ['success' => false, 'message' => 'La solicitud no está pendiente de autorización.', 'code' => 409];

        $usuario = Usuario::find(19);
        $email = trim((string) ($usuario->email ?? ''));
        if ($via === 'email' && ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return ['success' => false, 'message' => 'El usuario no tiene un correo electrónico válido registrado.', 'code' => 422];
        }

        $token = (string) random_int(100000, 999999);
        try {
            DB::table(self::TABLA_TOKEN)->where('id_solicitud', (int) $id)->where('id_usuario', 19)->delete();
            DB::table(self::TABLA_TOKEN)->insert([
                'id_solicitud' => (int) $id, 'id_usuario' => 19, 'token' => $token,
                'fecha_creacion' => date('Y-m-d H:i:s'),
            ]);
            if ($via === 'email') {
                $enviado = (new EmailService())->sendToken($email, $token, 'Solicitud de Calcomanías (#00' . (int) $id . ')');
                $mensajeError = 'No fue posible enviar el correo electrónico.';
            } else {
                $fechaSolicitud = strtotime((string) ($solicitud['fecha_hora'] ?? ''));
                $fechaCompleta = $fechaSolicitud === false
                    ? ''
                    : formatearFecha(date('Y-m-d', $fechaSolicitud)) . ', ' . date('g:i a', $fechaSolicitud);
                $mensaje = '📲 Usa el token <b>' . $token . '</b> para firmar la autorización de la Solicitud de Calcomanías de No. ' . (int) $id . ' correspondiente al día: ' . $fechaCompleta . '.'
                    . PHP_EOL . PHP_EOL . '⛽ Estación: ' . htmlspecialchars((string) $solicitud['nombre_ubicacion'], ENT_QUOTES, 'UTF-8') . '.';
                $enviado = (new TelegramService())->sendToken(19, $mensaje);
                $mensajeError = 'No fue posible enviar el token por Telegram. Verifica que la cuenta esté vinculada.';
            }
            if (!$enviado) {
                DB::table(self::TABLA_TOKEN)->where('id_solicitud', (int) $id)->where('id_usuario', 19)->delete();
                return ['success' => false, 'message' => $mensajeError, 'code' => 502];
            }
            return ['success' => true, 'message' => $via === 'email' ? 'Token enviado por correo electrónico.' : 'Token enviado por Telegram.'];
        } catch (Throwable $e) {
            error_log('RefaccionesSolicitudCalcomaniasService::crearTokenAutorizacion: ' . $e->getMessage());
            try {
                DB::table(self::TABLA_TOKEN)
                    ->where('id_solicitud', (int) $id)
                    ->where('id_usuario', 19)
                    ->where('token', $token)
                    ->delete();
            } catch (Throwable $cleanupError) {
                error_log('RefaccionesSolicitudCalcomaniasService::crearTokenAutorizacion cleanup: ' . $cleanupError->getMessage());
            }
            $message = $via === 'email'
                ? 'No fue posible enviar el token por correo electrónico.'
                : 'No fue posible enviar el token por Telegram. Verifica que la cuenta esté vinculada.';
            return ['success' => false, 'message' => $message, 'code' => 502];
        }
    }

    public static function firmarAutorizacion(array $input): array
    {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $token = trim((string) ($input['token'] ?? ''));
        if ((int) (Auth::user()->id ?? 0) !== 19) return ['success' => false, 'message' => 'Solo el usuario autorizado puede finalizar esta solicitud.', 'code' => 403];
        if ($id === false || $id < 1 || !preg_match('/^\d{6}$/', $token)) return ['success' => false, 'message' => 'Ingresa el token de seguridad de seis dígitos recibido por Telegram o correo electrónico.', 'code' => 422];
        $solicitud = self::obtener((int) $id);
        if (!$solicitud || (int) $solicitud['estado'] !== 1) return ['success' => false, 'message' => 'La solicitud ya no está pendiente de autorización.', 'code' => 409];
        if (trim((string) ($solicitud['firma_autorizacion'] ?? '')) !== '') return ['success' => false, 'message' => 'La solicitud ya fue autorizada.', 'code' => 409];
        $tokenRow = DB::table(self::TABLA_TOKEN)->where('id_solicitud', (int) $id)->where('id_usuario', 19)->where('token', $token)->first();
        if (!$tokenRow) return ['success' => false, 'message' => 'El token no es válido.', 'code' => 422];
        if (strtotime((string) $tokenRow->fecha_creacion) < time() - 300) {
            DB::table(self::TABLA_TOKEN)->where('id', (int) $tokenRow->id)->delete();
            return ['success' => false, 'message' => 'El token venció. Genera uno nuevo por Telegram o correo electrónico.', 'code' => 422];
        }
        try {
            DB::connection()->transaction(static function () use ($id, $tokenRow): void {
                $updated = DB::table(self::TABLA)->where('id', (int) $id)->where('estado', 1)->update([
                    'firma_autorizacion' => 'Autorización electrónica mediante token de seguridad',
                    'id_usuario_autorizacion' => 19, 'firmado_autorizacion_en' => date('Y-m-d H:i:s'),
                    'estado' => 2, 'actualizado_en' => date('Y-m-d H:i:s'),
                ]);
                if (!$updated) throw new \RuntimeException('La solicitud ya fue autorizada o cambió de estado.');
                DB::table(self::TABLA_TOKEN)->where('id', (int) $tokenRow->id)->delete();
            });
            return ['success' => true, 'message' => 'La solicitud fue autorizada y finalizada.'];
        } catch (Throwable $e) {
            error_log('RefaccionesSolicitudCalcomaniasService::firmarAutorizacion: ' . $e->getMessage());
            return ['success' => false, 'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible finalizar la solicitud.', 'code' => 409];
        }
    }

    private static function validarFirma(string $firma): ?string
    {
        $firma = trim($firma);
        if (strlen($firma) > 2000000
            || !preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $firma, $matches)) {
            return null;
        }
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '' || @getimagesizefromstring($bytes) === false) return null;
        return $firma;
    }
}
