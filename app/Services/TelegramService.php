<?php

namespace App\Services;

use App\Models\Operativo\TokenTelegram;
use App\Models\Estacion;
use App\Models\Usuario;

class TelegramService
{
private string $apiUrl;

public function __construct()
{
$token = telegramBotToken();
$this->apiUrl = "https://api.telegram.org/{$token}";
}

public function sendMessage(int $chatId, string $mensaje, string $parseMode = 'HTML'): bool
{
if (empty($this->apiUrl)) return false;

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

curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

return $httpCode === 200;
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

return $record ? (int) $record->chat_id : 0;
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
if ($userMessage === '/start') {
return $this->sendMessage($chatId, "Bienvenid@ al sistema de Recepcion de token.\n\nDeberas de ingresar el token generado en el portal para poder llevar a cabo la recepción de token.");
}

$existing = TokenTelegram::where('chat_id', $chatId)
->where('estatus', 1)
->orderBy('id', 'desc')
->first();

if ($existing) {
return $this->sendMessage($chatId, "¡TU USUARIO YA SE ENCUENTRA VERIFICADO!\nNo es necesario responder la conversación a este chat.");
}

$record = TokenTelegram::where('token', $userMessage)
->where('estatus', 0)
->orderBy('id', 'desc')
->first();

if (!$record) {
return $this->sendMessage($chatId, "¡ERROR DE AUTENTICACIÓN!\nEl token proporcionado no es el correcto.");
}

$expiry = \Carbon\Carbon::parse($record->fecha_creacion)->addMinutes(2);
if (\Carbon\Carbon::now()->greaterThan($expiry)) {
return $this->sendMessage($chatId, "¡TU CODIGO DE VERIFICACIÓN HA EXPIRADO!\nGenera un nuevo código para llevar a cabo la verificación.");
}

$record->update([
'chat_id' => $chatId,
'estatus' => 1,
]);

return $this->sendMessage($chatId, "¡AUTENTICACIÓN EXITOSA!\nAhora puedes generar los token para firmar las solicitudes en el portal.");
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
curl_close($ch);

return $httpCode === 200;
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
