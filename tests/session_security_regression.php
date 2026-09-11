<?php

declare(strict_types=1);

function session_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function session_request(string $method, string $path, string $sessionPath, ?string $cookie = null, ?string $body = null, array $headers = []): array
{
    $payload = $body ?? '';
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/session_security_router.php',
        'SCRIPT_NAME' => '/tests/session_security_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'CONTENT_LENGTH' => (string) strlen($payload),
        'SESSION_SECURITY_TEST_PATH' => $sessionPath,
    ];
    if ($cookie !== null) {
        $environment['HTTP_COOKIE'] = $cookie;
    }
    foreach ($headers as $header) {
        [$name, $value] = explode(':', $header, 2);
        $environment['HTTP_' . strtoupper(str_replace('-', '_', trim($name)))] = trim($value);
    }

    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PHP-CGI session fixture.');
    }
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Session fixture failed: ' . trim($stderr));
    }

    [$rawHeaders, $response] = array_pad(preg_split("/\\r?\\n\\r?\\n/", $raw, 2), 2, '');
    $responseHeaders = preg_split('/\\r?\\n/', $rawHeaders);
    $status = 200;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Status:\\s+(\\d{3})/i', $header, $match)) {
            $status = (int) $match[1];
        }
    }
    return ['status' => $status, 'body' => $response, 'headers' => $responseHeaders];
}

function session_cookie(array $headers): string
{
    foreach ($headers as $header) {
        if (str_starts_with($header, 'Set-Cookie:')) {
            return explode(';', trim(substr($header, strlen('Set-Cookie:'))))[0];
        }
    }
    throw new RuntimeException('Session fixture did not issue a cookie.');
}

$sessionPath = sys_get_temp_dir() . '/portal3-session-security-' . bin2hex(random_bytes(8));
mkdir($sessionPath, 0700, true);
$anonymous = session_request('GET', '/anonymous', $sessionPath);
$anonymousCookie = session_cookie($anonymous['headers']);
$login = session_request('POST', '/login', $sessionPath, $anonymousCookie);
$authenticatedCookie = session_cookie($login['headers']);
$loginData = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR);

$tests = [
    'sets hardened session-cookie attributes' => static function () use ($anonymous): void {
        $header = implode("\n", $anonymous['headers']);
        session_assert(str_contains($header, 'HttpOnly') && str_contains($header, 'SameSite=Lax') && str_contains($header, 'secure'), 'Expected Secure, HttpOnly, and SameSite=Lax session cookie attributes.');
    },
    'regenerates the session ID on login' => static function () use ($anonymousCookie, $authenticatedCookie, $loginData): void {
        session_assert($anonymousCookie !== $authenticatedCookie, 'Expected login to rotate the session cookie value.');
        session_assert($loginData['authenticated'] === true, 'Expected the new session to be authenticated.');
    },
    'does not authenticate the old session after login' => static function () use ($sessionPath, $anonymousCookie): void {
        $response = session_request('GET', '/session', $sessionPath, $anonymousCookie);
        $data = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        session_assert($data['authenticated'] === false, 'Expected the pre-login session not to be reusable.');
    },
    'keeps CSRF protected after session rotation' => static function () use ($sessionPath, $authenticatedCookie, $loginData): void {
        $accepted = session_request('POST', '/csrf', $sessionPath, $authenticatedCookie, '{}', ['X-CSRF-Token: ' . $loginData['csrf']]);
        $rejected = session_request('POST', '/csrf', $sessionPath, $authenticatedCookie, '{}');
        session_assert($accepted['status'] === 200 && $accepted['body'] === 'accepted', 'Expected a post-login CSRF token to work.');
        session_assert($rejected['status'] === 419, 'Expected a missing post-login CSRF token to be rejected.');
    },
    'logout destroys the authenticated session' => static function () use ($sessionPath, $authenticatedCookie): void {
        session_request('POST', '/logout', $sessionPath, $authenticatedCookie);
        $response = session_request('GET', '/session', $sessionPath, $authenticatedCookie);
        $data = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        session_assert($data['authenticated'] === false, 'Expected a logged-out session not to be reusable.');
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
