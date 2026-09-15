<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\RateLimiter;

function rate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/portal3-rate-limiter-' . bin2hex(random_bytes(8));
RateLimiter::useStorageDirectoryForTesting($directory);
$start = 1_790_000_000;

$tests = [
    'permits requests under the login limit' => static function () use ($start): void {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $result = RateLimiter::consume('login', 'alice', '198.51.100.10', $start);
            rate_assert($result['allowed'], 'Expected attempt ' . ($attempt + 1) . ' to be allowed.');
        }
    },
    'rejects the request over the login limit' => static function () use ($start): void {
        $result = RateLimiter::consume('login', 'alice', '198.51.100.10', $start);
        rate_assert(!$result['allowed'] && $result['retry_after'] === 300, 'Expected the eleventh attempt to be rejected.');
    },
    'isolates account and IP keys' => static function () use ($start): void {
        rate_assert(RateLimiter::consume('login', 'bob', '198.51.100.10', $start)['allowed'], 'Expected a different account to remain allowed.');
        rate_assert(RateLimiter::consume('login', 'alice', '198.51.100.11', $start)['allowed'], 'Expected a different IP to remain allowed.');
    },
    'resets the fixed window without sleeping' => static function () use ($start): void {
        $result = RateLimiter::consume('login', 'alice', '198.51.100.10', $start + 300);
        rate_assert($result['allowed'], 'Expected a request at the next window to be allowed.');
    },
    'clears the login counter after a successful operation' => static function () use ($start): void {
        RateLimiter::clear('login', 'alice', '198.51.100.10');
        $result = RateLimiter::consume('login', 'alice', '198.51.100.10', $start + 1);
        rate_assert($result['allowed'], 'Expected a cleared counter to allow a new attempt.');
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

RateLimiter::useStorageDirectoryForTesting(null);
echo "RESULT passed={$passed} failed={$failed} skipped=0\n";
exit($failed === 0 ? 0 : 1);
