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
 (string) file_get_contents('php://input'),
$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null
);

http_response_code($status);
exit;
}

public function handleDemo(): void
{
    $this->handleProfile('DEMO');
}

public function handleDev(): void
{
    $this->handleProfile('DEV');
}

public function handlePayload(string $rawPayload, ?string $secretHeader): int
{
$expectedSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';
return $this->processPayload($rawPayload, $secretHeader, $expectedSecret, $this->telegram, $this->replayGuard);
}

private function handleProfile(string $profile): void
{
    $tokenKey = 'TELEGRAM_BOT_TOKEN_' . $profile;
    $secretKey = 'TELEGRAM_WEBHOOK_SECRET_' . $profile;
    $token = (string) ($_ENV[$tokenKey] ?? getenv($tokenKey) ?: '');
    $secret = (string) ($_ENV[$secretKey] ?? getenv($secretKey) ?: '');
    $replayDir = dirname(__DIR__, 2) . '/storage/private/telegram-webhook-replay/' . strtolower($profile);
    $profileTelegram = new TelegramService($token);
    $profileReplayGuard = new TelegramWebhookReplayGuard($replayDir);

    $status = $token === ''
        ? 503
        : $this->processPayload(
            (string) file_get_contents('php://input'),
            $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null,
            $secret,
            $profileTelegram,
            $profileReplayGuard
        );

    http_response_code($status);
    exit;
}

private function processPayload(
    string $rawPayload,
    ?string $secretHeader,
    string $expectedSecret,
    TelegramService $telegram,
    TelegramWebhookReplayGuard $replayGuard
): int {
if ($expectedSecret === '' || !is_string($secretHeader) || !hash_equals($expectedSecret, $secretHeader)) {
return 403;
}

try {
$update = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException) {
return 400;
}

if (!is_array($update) || !isset($update['update_id']) || !is_int($update['update_id']) || $update['update_id'] < 0) {
return 400;
}

// Telegram can send updates outside the message flow (for example, edited
// messages or channel events). Acknowledge unsupported updates so they are
// not retried indefinitely; this bot only processes text sent to its chat.
if (!isset($update['message']['chat']['id']) || !is_int($update['message']['chat']['id'])
|| !isset($update['message']['text']) || !is_string($update['message']['text'])) {
return 200;
}

$updateId = $update['update_id'];
try {
if (!$replayGuard->claim($updateId)) {
return 200;
}

if (!$telegram->processUpdate($update)) {
$replayGuard->release($updateId);
return 422;
}
} catch (\Throwable $exception) {
$replayGuard->release($updateId);
error_log('[Telegram webhook] Update processing failed (' . get_class($exception) . ').');
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
