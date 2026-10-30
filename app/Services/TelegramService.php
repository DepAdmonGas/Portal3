<?php

namespace App\Services;

use App\Models\Operativo\TokenTelegram;
use App\Models\Estacion;
use App\Models\Usuario;

class TelegramService
{
private string $apiUrl;
private ?string $lastError = null;

public function __construct(?string $botToken = null)
{
$token = $botToken ?? telegramBotToken();
        $this->apiUrl = $token !== '' ? "https://api.telegram.org/bot{$token}" : '';
}

public function sendMessage(int $chatId, string $mensaje, string $parseMode = 'HTML'): bool
{
    $this->lastError = null;
    if (empty($this->apiUrl)) {
        $this->lastError = 'token_missing';
        return false;
    }

$data = [
'chat_id' => $chatId,
'text' => $mensaje,
'parse_mode' => $parseMode,
];

$url = $this->apiUrl . '/sendMessage';

$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $url,
CURLOPT_POST => 1,
CURLOPT_POSTFIELDS => http_build_query($data),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_errno($ch);
curl_close($ch);

if ($curlError !== 0 || !is_string($response)) {
    $this->lastError = 'network_error';
    error_log('[Telegram] sendMessage request failed (HTTP ' . (int) $httpCode . ', cURL ' . (int) $curlError . ').');
    return false;
}

$result = json_decode($response, true);
if (!is_array($result) || $httpCode !== 200 || ($result['ok'] ?? false) !== true) {
    $errorCode = is_array($result) ? (int) ($result['error_code'] ?? 0) : 0;
    $description = is_array($result) && is_string($result['description'] ?? null)
        ? preg_replace('/[\r\n]+/', ' ', $result['description'])
        : 'Respuesta inválida de la Bot API.';
    $this->lastError = (string) $errorCode . ':' . $description;
    error_log('[Telegram] sendMessage rejected (HTTP ' . (int) $httpCode . ', API ' . $errorCode . '): ' . $description);
    return false;
}

return true;
}

public function getLastError(): ?string
{
    return $this->lastError;
}

public function sendToken(int $idUsuario, string $mensaje): bool
{
$chatId = $this->getChatId($idUsuario);
if ($chatId === 0) return false;

return $this->sendMessage($chatId, $mensaje);
}

public function sendMessageToMultiple(array $userIds, string $mensaje): bool
{
if (empty($userIds)) return false;

$multi = curl_multi_init();
$handles = [];

foreach ($userIds as $idUsuario) {
$chatId = $this->getChatId($idUsuario);
if ($chatId === 0) continue;

$data = [
'chat_id' => $chatId,
'text' => $mensaje,
'parse_mode' => 'HTML',
];

$url = $this->apiUrl . '/sendMessage';

$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $url,
CURLOPT_POST => 1,
CURLOPT_POSTFIELDS => http_build_query($data),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => 10,
]);

curl_multi_add_handle($multi, $ch);
$handles[] = $ch;
}

if (empty($handles)) {
curl_multi_close($multi);
return false;
}

$running = null;
do {
curl_multi_exec($multi, $running);
curl_multi_select($multi);
} while ($running > 0);

foreach ($handles as $ch) {
curl_multi_remove_handle($multi, $ch);
curl_close($ch);
}

curl_multi_close($multi);
return true;
}

public function getChatId(int $idUsuario): int
{
$record = TokenTelegram::where('id_usuario', $idUsuario)
->where('estatus', 1)
->orderBy('fecha_creacion', 'desc')
->first();

if ($record && (int) $record->chat_id > 0) {
return (int) $record->chat_id;
}

// El bot externo guarda el chat_id cuando el usuario inicia la
// conversación, pero hay registros que quedaron sin marcar como
// verificados (estatus 0). Si el chat existe se usa igual para no
// perder la notificación.
$respaldo = TokenTelegram::where('id_usuario', $idUsuario)
->where('chat_id', '>', 0)
->orderBy('fecha_creacion', 'desc')
->first();

return $respaldo ? (int) $respaldo->chat_id : 0;
}

public function getUserIdsByStation(int $idEstacion, int $excludeUserId): array
{
return Usuario::where('id_gas', $idEstacion)
->where('estatus', 0)
->whereNotIn('id_puesto', [4, 5, 8, 12, 15])
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsByStationWithComodines(int $idEstacion, int $excludeUserId): array
{
$stationUsers = Usuario::where('id_gas', $idEstacion)
->where('estatus', 0)
->whereNotIn('id_puesto', [4, 5, 8, 12, 15])
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();

$comodines = Usuario::where('id_gas', 8)
->where('estatus', 0)
->whereIn('id_puesto', [6, 13, 14])
->pluck('id')
->toArray();

return array_unique(array_merge($stationUsers, $comodines));
}

public function getUserIdsByPuesto(int $idPuesto, int $excludeUserId): array
{
return Usuario::where('id_puesto', $idPuesto)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsDeptoOperativo(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 13)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsComercializadora(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 4)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsContabilidad(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 12)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsMantenimiento(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 8)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsJuridica(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 15)
->where('estatus', 0)
->where('id', '!=', $excludeUserId)
->pluck('id')
->toArray();
}

public function getUserIdsGestoria(int $excludeUserId): array
{
return Usuario::where('id_gas', 8)
->where('id_puesto', 5)
->where('estatus', 0)
->whereNotIn('id', [30, $excludeUserId])
->pluck('id')
->toArray();
}

public function handleWebhookMessage(string $userMessage, int $chatId): bool
{
$existing = TokenTelegram::where('chat_id', $chatId)
->where('estatus', 1)
->orderBy('id', 'desc')
->first();

if ($userMessage === '/start' || str_starts_with($userMessage, '/start ')) {
    if ($existing) {
        return $this->sendMessage($chatId, "¡TU USUARIO YA SE ENCUENTRA VERIFICADO! ✅👤\n\nNo es necesario responder a este chat. Aquí recibirás las notificaciones internas del Portal de Admongas, además de los códigos de autorización.");
    }

    return $this->sendMessage($chatId, "👋 <b>Vincula tu cuenta de Portal 3</b>\n\nEste chat aún no está vinculado. Entra a tu perfil de Portal 3, genera un token de Telegram y envíalo en este chat.");
}

if ($existing) {
return $this->sendMessage($chatId, "¡TU USUARIO YA SE ENCUENTRA VERIFICADO! ✅👤\n\nNo es necesario responder a este chat. Aquí recibirás las notificaciones internas del Portal de Admongas, además de los códigos de autorización.");
}

$record = TokenTelegram::where('token', $userMessage)
->where('estatus', 0)
->orderBy('id', 'desc')
->first();

if (!$record) {
return $this->sendMessage($chatId, "¡EL TOKEN NO ES VÁLIDO! 📲❌\n\nEl token proporcionado no es el correcto. ⚠\n\nGenera un token nuevo desde tu perfil de Portal 3 y vuelve a enviarlo en este chat.");
}

$expiry = \Carbon\Carbon::parse($record->fecha_creacion)->addMinutes(2);
if (\Carbon\Carbon::now()->greaterThan($expiry)) {
return $this->sendMessage($chatId, "⌛ <b>El token venció</b>\n\nGenera uno nuevo desde tu perfil de Portal 3 y envíalo aquí.");
}

$record->update([
'chat_id' => $chatId,
'estatus' => 1,
]);

return $this->sendMessage($chatId, "¡AUTENTICACIÓN EXITOSA! ✅👤\n\nTu cuenta quedó vinculada con el Portal de Admongas. Ya puedes recibir en este chat las notificaciones internas de Portal3 y los códigos de autorización.");
}

public function processUpdate(array $update): bool
{
if (!isset($update['message']['chat']['id'])) {
return false;
}

$chatId = (int) $update['message']['chat']['id'];
$text = trim($update['message']['text'] ?? '');

return $this->handleWebhookMessage($text, $chatId);
}

public function sendTokenAsync(int $idUsuario, string $mensaje): void
{
$chatId = $this->getChatId($idUsuario);
if ($chatId === 0) return;

$this->sendMessageAsync($chatId, $mensaje);
}

private function sendMessageAsync(int $chatId, string $mensaje): void
{
if (empty($this->apiUrl)) return;

$data = [
'chat_id' => $chatId,
'text' => $mensaje,
'parse_mode' => 'HTML',
];

$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $this->apiUrl . '/sendMessage',
CURLOPT_POST => true,
CURLOPT_POSTFIELDS => http_build_query($data),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT_MS => 3000,
CURLOPT_CONNECTTIMEOUT_MS => 2000,
CURLOPT_FORBID_REUSE => true,
]);
curl_exec($ch);
curl_close($ch);
}

public function pollUpdates(int $offset = 0, int $timeout = 10): array
{
$data = [
'offset' => $offset,
'timeout' => $timeout,
'allowed_updates' => json_encode(['message']),
];

$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $this->apiUrl . '/getUpdates',
CURLOPT_POST => true,
CURLOPT_POSTFIELDS => http_build_query($data),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => $timeout + 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
return [];
}

$result = json_decode($response, true);
return $result['result'] ?? [];
}

public function setWebhook(string $url): bool
{
$data = ['url' => $url];
$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';
if ($secret !== '') {
$data['secret_token'] = $secret;
}

$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $this->apiUrl . '/setWebhook',
CURLOPT_POST => true,
CURLOPT_POSTFIELDS => http_build_query($data),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_errno($ch);
curl_close($ch);

if ($curlError !== 0 || $httpCode !== 200 || !is_string($response)) {
    error_log('[Telegram] setWebhook request failed (HTTP ' . (int) $httpCode . ', cURL ' . (int) $curlError . ').');
    return false;
}

$result = json_decode($response, true);
if (!is_array($result) || ($result['ok'] ?? false) !== true) {
    error_log('[Telegram] setWebhook was rejected by the Bot API.');
    return false;
}

return true;
}

public function deleteWebhook(): bool
{
$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $this->apiUrl . '/deleteWebhook',
CURLOPT_POST => true,
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

return $httpCode === 200;
}

public function getWebhookInfo(): ?array
{
$ch = curl_init();
curl_setopt_array($ch, [
CURLOPT_URL => $this->apiUrl . '/getWebhookInfo',
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
return null;
}

$result = json_decode($response, true);
return $result['ok'] ? ($result['result'] ?? null) : null;
}

public static function notificar(int $idEstacion, int $excludeUserId, string $mensaje): void
{
try {
$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $excludeUserId);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

foreach ($userIds as $uid) {
$telegram->sendTokenAsync($uid, $mensaje);
}
} catch (\Throwable $e) {
error_log('Error en TelegramService::notificar: ' . $e->getMessage());
}
}
}
