<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Logger;

function privacy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$logPath = sys_get_temp_dir() . '/portal3-privacy-log-' . bin2hex(random_bytes(8)) . '.log';
$_ENV['LOG_PATH'] = $logPath;

Logger::warning("Authentication event\nforged line", [
    'password' => 'dummy-password-not-for-logs',
    'access_token' => 'dummy-access-token-not-for-logs',
    'cookie' => 'dummy-cookie-not-for-logs',
    'telegram_webhook_secret' => 'dummy-webhook-secret-not-for-logs',
    'usuario' => 'alice@example.test',
    'ip' => '198.51.100.9',
    'user_id' => 42,
    'outcome' => 'invalid_credentials',
]);

$contents = (string) file_get_contents($logPath);
$tests = [
    'redacts secrets and PII from logging context' => static function () use ($contents): void {
        foreach (['dummy-password-not-for-logs', 'dummy-access-token-not-for-logs', 'dummy-cookie-not-for-logs', 'dummy-webhook-secret-not-for-logs', 'alice@example.test', '198.51.100.9'] as $sensitiveValue) {
            privacy_assert(!str_contains($contents, $sensitiveValue), 'Sensitive value was written to the log.');
        }
        privacy_assert(substr_count($contents, '[REDACTED]') === 6, 'Expected every sensitive context value to be redacted.');
    },
    'preserves useful event context without log injection' => static function () use ($contents): void {
        privacy_assert(str_contains($contents, 'user_id') && str_contains($contents, '42') && str_contains($contents, 'invalid_credentials'), 'Expected retained audit context.');
        privacy_assert(!str_contains($contents, "Authentication event\nforged line"), 'Expected log message newlines to be neutralized.');
    },
];

$passed = 0;
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

echo "RESULT passed={$passed} failed={$failed} skipped=0\n";
exit($failed === 0 ? 0 : 1);
