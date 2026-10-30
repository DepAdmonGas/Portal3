<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Operativo\TokenTelegram;
use App\Services\TelegramService;

class TokenTelegramController
{
public function status()
{
header('Content-Type: application/json; charset=utf-8');

$idUsuario = $this->authenticatedUserId();

$record = TokenTelegram::where('id_usuario', $idUsuario)
->orderBy('id', 'desc')
->first();

if (!$record) {
echo json_encode([
'success' => true,
'data' => [
'registered' => false,
'verified' => false,
'has_chat_id' => false,
],
]);
exit;
}

$expired = $record->isExpired();
$remainingSeconds = 0;

if (!$expired && $record->estatus == 0 && $record->fecha_creacion) {
$expiry = \Carbon\Carbon::parse($record->fecha_creacion)->addMinutes(2);
$remainingSeconds = max(0, \Carbon\Carbon::now()->diffInSeconds($expiry, false));
}

echo json_encode([
'success' => true,
'data' => [
'registered' => true,
'verified' => $record->isVerified(),
'has_chat_id' => !empty($record->chat_id),
'estatus' => $record->estatus,
'token' => $record->estatus == 0 ? $record->token : null,
'expired' => $expired,
'remaining_seconds' => $remainingSeconds,
'fecha_creacion' => $record->fecha_creacion,
],
]);
exit;
}

public function generate()
{
header('Content-Type: application/json; charset=utf-8');

$idUsuario = $this->authenticatedUserId();

$record = TokenTelegram::generateToken($idUsuario);

$expiry = \Carbon\Carbon::parse($record->fecha_creacion)->addMinutes(2);
$remainingSeconds = max(0, \Carbon\Carbon::now()->diffInSeconds($expiry, false));

echo json_encode([
'success' => true,
'data' => [
'token' => $record->token,
'fecha_creacion' => $record->fecha_creacion,
'estatus' => $record->estatus,
'remaining_seconds' => $remainingSeconds,
'digits' => str_split($record->token),
],
]);
exit;
}

public function revoke()
{
header('Content-Type: application/json; charset=utf-8');

$idUsuario = $this->authenticatedUserId();

TokenTelegram::revokeAccess($idUsuario);

echo json_encode(['success' => true, 'message' => 'Acceso revocado correctamente']);
exit;
}

public function testNotification()
{
header('Content-Type: application/json; charset=utf-8');

$idUsuario = $this->authenticatedUserId();

$record = TokenTelegram::where('id_usuario', $idUsuario)
    ->where('estatus', 1)
    ->whereNotNull('chat_id')
    ->where('chat_id', '>', 0)
    ->orderBy('fecha_creacion', 'desc')
    ->first();

if (!$record) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Tu cuenta de Telegram aún no está vinculada. Vincúlala desde tu perfil y vuelve a intentarlo.',
    ]);
    exit;
}

$telegram = new TelegramService();
$sent = $telegram->sendMessage(
    (int) $record->chat_id,
    "¡PRUEBA DE TELEGRAM EXITOSA! ✅👤\n\nEste chat está listo para recibir las notificaciones internas del Portal de Admongas y los códigos de autorización."
);

if ($sent) {
echo json_encode(['success' => true, 'message' => 'Prueba enviada. Revisa el chat: ahí recibirás las notificaciones internas de Portal3 y los códigos de autorización.']);
} else {
http_response_code(502);
$detail = $telegram->getLastError() ?? '';
$message = match (true) {
    $detail === 'token_missing' => 'No hay un bot de Telegram configurado para este entorno. Revisa su variable TELEGRAM_BOT_TOKEN en el archivo .env.',
    $detail === 'network_error' => 'No se pudo conectar con Telegram. Inténtalo de nuevo en unos momentos.',
    str_starts_with($detail, '401:') => 'Telegram rechazó las credenciales del bot configurado. Revisa el token de este entorno en el archivo .env.',
    str_starts_with($detail, '403:') => 'Telegram no permitió enviar el mensaje. Abre el bot de este entorno, pulsa Iniciar y vincula tu cuenta con ese mismo bot.',
    str_starts_with($detail, '400:') => 'Telegram no encontró un chat disponible para este bot. Abre el bot de este entorno, pulsa Iniciar y vuelve a vincular tu cuenta.',
    default => 'No pudimos entregar el mensaje. Confirma que tu cuenta esté vinculada con el bot de este entorno e inténtalo de nuevo.',
};
echo json_encode([
    'success' => false,
    'message' => $message,
]);
}
exit;
}

private function authenticatedUserId(): int
{
$idUsuario = Auth::id();
if ($idUsuario !== null) return (int) $idUsuario;

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
exit;
}
}
