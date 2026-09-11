<?php

namespace App\Controllers;

use App\Services\TelegramService;
use App\Services\TelegramWebhookReplayGuard;

class TelegramWebhookController
{
private TelegramService $telegram;
private TelegramWebhookReplayGuard $replayGuard;

public function __construct(?TelegramService $telegram = null, ?TelegramWebhookReplayGuard $replayGuard = null)
{
$this->telegram = $telegram ?? new TelegramService();
$this->replayGuard = $replayGuard ?? new TelegramWebhookReplayGuard();
}

public function handle()
{
$status = $this->handlePayload(
file_get_contents('php://input'),
$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null
);

http_response_code($status);
exit;
}

public function handlePayload(string $rawPayload, ?string $secretHeader): int
{
$expectedSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';
if ($expectedSecret === '' || !is_string($secretHeader) || !hash_equals($expectedSecret, $secretHeader)) {
return 403;
}

try {
$update = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException) {
return 400;
}

if (!is_array($update) || !isset($update['update_id']) || !is_int($update['update_id']) || $update['update_id'] < 0
|| !isset($update['message']['chat']['id']) || !is_int($update['message']['chat']['id'])) {
return 400;
}

$updateId = $update['update_id'];
try {
if (!$this->replayGuard->claim($updateId)) {
return 200;
}

if (!$this->telegram->processUpdate($update)) {
$this->replayGuard->release($updateId);
return 422;
}
} catch (\Throwable) {
$this->replayGuard->release($updateId);
return 503;
}

return 200;
}

public function poll()
{
header('Content-Type: application/json; charset=utf-8');

$offset = (int) ($_GET['offset'] ?? 0);
$updates = $this->telegram->pollUpdates($offset);

echo json_encode(['success' => true, 'data' => $updates]);
exit;
}
}
