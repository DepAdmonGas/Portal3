<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\TelegramWebhookController;
use App\Services\TelegramService;
use App\Services\TelegramWebhookReplayGuard;

final class RecordingTelegramService extends TelegramService
{
    public int $processed = 0;
    public array $updates = [];

    public function __construct()
    {
    }

    public function processUpdate(array $update): bool
    {
        $this->processed++;
        $this->updates[] = $update;
        return true;
    }
}

function webhook_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$replayDirectory = sys_get_temp_dir() . '/portal3-telegram-webhook-' . bin2hex(random_bytes(8));
putenv('TELEGRAM_WEBHOOK_SECRET=test-webhook-secret');
putenv('TELEGRAM_WEBHOOK_REPLAY_DIR=' . $replayDirectory);

$service = new RecordingTelegramService();
$controller = new TelegramWebhookController($service, new TelegramWebhookReplayGuard());
$first = json_encode(['update_id' => 7001, 'message' => ['chat' => ['id' => 12345], 'text' => '/start']], JSON_THROW_ON_ERROR);
$next = json_encode(['update_id' => 7002, 'message' => ['chat' => ['id' => 12345], 'text' => '123456']], JSON_THROW_ON_ERROR);

$tests = [
    'rejects a missing Telegram secret' => static fn (): bool => $controller->handlePayload($first, null) === 403 && $service->processed === 0,
    'rejects an invalid Telegram secret' => static fn (): bool => $controller->handlePayload($first, 'wrong-secret') === 403 && $service->processed === 0,
    'accepts a valid authenticated Telegram event once' => static fn (): bool => $controller->handlePayload($first, 'test-webhook-secret') === 200 && $service->processed === 1,
    'accepts replay idempotently without repeating the side effect' => static fn (): bool => $controller->handlePayload($first, 'test-webhook-secret') === 200 && $service->processed === 1,
    'processes a new update after a replay' => static fn (): bool => $controller->handlePayload($next, 'test-webhook-secret') === 200 && $service->processed === 2,
    'rejects invalid JSON without a side effect' => static fn (): bool => $controller->handlePayload('{', 'test-webhook-secret') === 400 && $service->processed === 2,
    'rejects incomplete updates without a side effect' => static fn (): bool => $controller->handlePayload('{"update_id":7003}', 'test-webhook-secret') === 400 && $service->processed === 2,
];

$passed = 0;
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        webhook_assert($test(), $name);
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

echo "RESULT passed={$passed} failed={$failed} skipped=0\n";
exit($failed === 0 ? 0 : 1);
