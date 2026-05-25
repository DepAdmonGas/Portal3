<?php

namespace App\Controllers\Api;

use App\Models\Operativo\TokenTelegram;
use App\Services\TelegramService;

class TokenTelegramController
{
public function status()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idUsuario = (int) ($input['id_usuario'] ?? 0);

if (!$idUsuario) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

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

$input = json_decode(file_get_contents('php://input'), true);
$idUsuario = (int) ($input['id_usuario'] ?? 0);

if (!$idUsuario) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

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

$input = json_decode(file_get_contents('php://input'), true);
$idUsuario = (int) ($input['id_usuario'] ?? 0);

if (!$idUsuario) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

TokenTelegram::revokeAccess($idUsuario);

echo json_encode(['success' => true, 'message' => 'Acceso revocado correctamente']);
exit;
}

public function testNotification()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idUsuario = (int) ($input['id_usuario'] ?? 0);

if (!$idUsuario) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$telegram = new TelegramService();
$sent = $telegram->sendToken($idUsuario, "Mensaje de prueba desde Portal3.\n\nSi recibes esto, tu Telegram está vinculado correctamente.");

if ($sent) {
echo json_encode(['success' => true, 'message' => 'Mensaje de prueba enviado correctamente']);
} else {
echo json_encode(['success' => false, 'message' => 'No se pudo enviar el mensaje. Verifica que tengas tu chat vinculado.']);
}
exit;
}
}
