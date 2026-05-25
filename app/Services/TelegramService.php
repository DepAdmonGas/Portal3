<?php

namespace App\Services;

use App\Models\Operativo\TokenTelegram;
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
}
