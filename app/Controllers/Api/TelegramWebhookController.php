<?php

namespace App\Controllers\Api;

use App\Models\Operativo\TokenTelegram;

class TelegramWebhookController
{
public function handle()
{
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['message'])) {
http_response_code(200);
exit;
}

$chatId = $data['message']['chat']['id'] ?? 0;
$userMessage = trim($data['message']['text'] ?? '');

if ($chatId === 0) {
http_response_code(200);
exit;
}

if ($userMessage === '/start') {
$response = "Bienvenid@ al sistema de Recepcion de token.\n\nDeberas de ingresar el token generado en el portal para poder llevar a cabo la recepción de token.";
$this->sendTelegramMessage($chatId, $response);
exit;
}

$this->verifyToken($userMessage, $chatId);
exit;
}

private function verifyToken(string $token, int $chatId): void
{
$existing = TokenTelegram::where('chat_id', $chatId)
->where('estatus', 1)
->orderBy('id', 'desc')
->first();

if ($existing) {
$response = "¡TU USUARIO YA SE ENCUENTRA VERIFICADO!\nNo es necesario responder la conversación a este chat.";
$this->sendTelegramMessage($chatId, $response);
return;
}

$record = TokenTelegram::where('token', $token)
->where('estatus', 0)
->orderBy('id', 'desc')
->first();

if (!$record) {
$response = "¡ERROR DE AUTENTICACIÓN!\nEl token proporcionado no es el correcto.";
$this->sendTelegramMessage($chatId, $response);
return;
}

$expiry = \Carbon\Carbon::parse($record->fecha_creacion)->addMinutes(2);
if (\Carbon\Carbon::now()->greaterThan($expiry)) {
$response = "¡TU CODIGO DE VERIFICACIÓN HA EXPIRADO!\nGenera un nuevo código para llevar a cabo la verificación.";
$this->sendTelegramMessage($chatId, $response);
return;
}

$record->update([
'chat_id' => $chatId,
'estatus' => 1,
]);

$response = "¡AUTENTICACIÓN EXITOSA!\nAhora puedes generar los token para firmar las solicitudes en el portal.";
$this->sendTelegramMessage($chatId, $response);
}

private function sendTelegramMessage(int $chatId, string $message): void
{
$token = telegramBotToken();
$url = "https://api.telegram.org/{$token}/sendMessage";

$params = [
'chat_id' => $chatId,
'text' => $message,
];

file_get_contents($url . '?' . http_build_query($params));
}
}
