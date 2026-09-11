<?php

declare(strict_types=1);

function csrf_post_login_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function csrf_post_login_request(string $method, string $path, string $sessionPath, array $jar, ?string $csrfToken = null): array
{
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/csrf_post_login_router.php',
        'SCRIPT_NAME' => '/tests/csrf_post_login_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'CSRF_POST_LOGIN_SESSION_TEST_PATH' => $sessionPath,
    ];
    if ($jar !== []) {
        $environment['HTTP_COOKIE'] = implode('; ', array_values($jar));
    }
    if ($csrfToken !== null) {
        $environment['HTTP_X_CSRF_TOKEN'] = $csrfToken;
    }

    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start CSRF post-login fixture.');
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('CSRF post-login fixture failed: ' . trim($stderr));
    }

    [$rawHeaders, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $status = 200;
    foreach (preg_split('/\r?\n/', $rawHeaders) as $header) {
        if (preg_match('/^Status:\s+(\d{3})/i', $header, $match)) {
            $status = (int) $match[1];
        }
        if (str_starts_with($header, 'Set-Cookie:')) {
            $pair = trim(explode(';', substr($header, strlen('Set-Cookie:')), 2)[0]);
            $jar[explode('=', $pair, 2)[0]] = $pair;
        }
    }

    return ['status' => $status, 'body' => $body, 'jar' => $jar];
}

$sessionPath = sys_get_temp_dir() . '/portal3-csrf-post-login-' . bin2hex(random_bytes(8));
mkdir($sessionPath, 0700, true);
$anonymousForm = csrf_post_login_request('GET', '/anonymous-form', $sessionPath, []);
$anonymousToken = json_decode($anonymousForm['body'], true, 512, JSON_THROW_ON_ERROR)['csrf_token'];
$login = csrf_post_login_request('POST', '/login', $sessionPath, $anonymousForm['jar']);
$authenticatedForm = csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $login['jar']);
$authenticatedToken = json_decode($authenticatedForm['body'], true, 512, JSON_THROW_ON_ERROR)['csrf_token'];
$validMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $authenticatedToken);
$missingMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar']);
$invalidMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], str_repeat('0', 64));
$anonymousMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $anonymousToken);
$secondValidMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $authenticatedToken);

$tests = [
    'anonymous and authenticated CSRF tokens are both issued and differ after login' => static function () use ($anonymousForm, $authenticatedForm, $anonymousToken, $authenticatedToken): void {
        csrf_post_login_assert($anonymousForm['status'] === 200 && $authenticatedForm['status'] === 200, 'Expected both GET form requests to succeed.');
        csrf_post_login_assert($anonymousToken !== '' && $authenticatedToken !== '', 'Expected a token on both forms.');
        csrf_post_login_assert(!hash_equals($anonymousToken, $authenticatedToken), 'Expected login to replace the anonymous CSRF token.');
    },
    'authenticated mutation accepts the current CSRF header and authenticated session' => static function () use ($validMutation): void {
        csrf_post_login_assert($validMutation['status'] === 200, 'Expected the current post-login token to be accepted.');
        csrf_post_login_assert(json_decode($validMutation['body'], true, 512, JSON_THROW_ON_ERROR)['authenticated'] === true, 'Expected the mutation to retain authenticated session state.');
    },
    'missing and invalid CSRF tokens are rejected after login' => static function () use ($missingMutation, $invalidMutation): void {
        csrf_post_login_assert($missingMutation['status'] === 419, 'Expected missing CSRF token rejection.');
        csrf_post_login_assert($invalidMutation['status'] === 419, 'Expected invalid CSRF token rejection.');
    },
    'pre-login CSRF token is rejected after login' => static function () use ($anonymousMutation): void {
        csrf_post_login_assert($anonymousMutation['status'] === 419, 'Expected the anonymous token to be invalid after login.');
    },
    'current post-login token permits multiple mutations' => static function () use ($secondValidMutation): void {
        csrf_post_login_assert($secondValidMutation['status'] === 200, 'Expected the current post-login token to remain usable.');
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
