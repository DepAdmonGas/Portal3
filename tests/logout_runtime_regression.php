<?php

declare(strict_types=1);

function logout_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function logout_request(string $method, string $path, string $sessionPath, ?string $cookie = null, ?string $csrf = null, ?string $errorLog = null): array
{
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/logout_runtime_router.php',
        'SCRIPT_NAME' => '/tests/logout_runtime_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'PORTAL3_LOGOUT_SESSION_TEST_PATH' => $sessionPath,
        'PORTAL3_LOGOUT_LOG_TEST' => '/proc/portal3-logout-runtime/app.log',
    ];
    if ($errorLog !== null) {
        $environment['PORTAL3_LOGOUT_ERROR_LOG_TEST'] = $errorLog;
    }
    if ($cookie !== null) {
        $environment['HTTP_COOKIE'] = $cookie;
    }
    if ($csrf !== null) {
        $environment['HTTP_X_CSRF_TOKEN'] = $csrf;
    }

    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start local PHP-CGI logout fixture.');
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('PHP-CGI logout fixture failed: ' . trim($stderr));
    }

    [$rawHeaders, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $status = 200;
    $headers = preg_split('/\r?\n/', $rawHeaders);
    foreach ($headers as $header) {
        if (preg_match('/^Status:\s+(\d{3})/i', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return ['status' => $status, 'body' => $body, 'headers' => $headers];
}

function logout_cookie_pairs(array $headers): array
{
    $pairs = [];
    foreach ($headers as $header) {
        if (str_starts_with($header, 'Set-Cookie:')) {
            $pair = explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2)[0];
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[$name] = $value;
        }
    }
    return $pairs;
}

$sessionPath = sys_get_temp_dir() . '/portal3-logout-runtime-' . bin2hex(random_bytes(8));
mkdir($sessionPath, 0700, true);
$errorLog = tempnam(sys_get_temp_dir(), 'portal3-logout-error-');

try {
    $login = logout_request('POST', '/__test/login', $sessionPath, errorLog: $errorLog);
    $loginData = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR);
    $loginCookies = logout_cookie_pairs($login['headers']);
    $cookieHeader = implode('; ', array_map(static fn (string $name, string $value): string => $name . '=' . $value, array_keys($loginCookies), array_values($loginCookies)));

    $missingCsrf = logout_request('POST', '/logout', $sessionPath, $cookieHeader, errorLog: $errorLog);
    $csrfRetry = json_decode($missingCsrf['body'], true, 512, JSON_THROW_ON_ERROR);
    $logout = logout_request('POST', '/logout', $sessionPath, $cookieHeader, $csrfRetry['new_token'], $errorLog);
    $logoutData = json_decode($logout['body'], true, 512, JSON_THROW_ON_ERROR);
    $protected = logout_request('GET', '/__test/protected', $sessionPath, $cookieHeader, errorLog: $errorLog);
    $logContents = (string) file_get_contents($errorLog);

    $tests = [
        'valid authenticated POST logout succeeds with an unwritable configured log path' => static function () use ($login, $logout, $logoutData): void {
            logout_assert($login['status'] === 200, 'Expected the test session to authenticate.');
            logout_assert($logout['status'] === 200 && ($logoutData['type'] ?? null) === 'success', 'Expected logout to return success instead of HTTP 500.');
        },
        'CSRF remains required for logout' => static function () use ($missingCsrf, $csrfRetry): void {
            logout_assert($missingCsrf['status'] === 419 && isset($csrfRetry['new_token']), 'Expected logout without CSRF to be rejected and issue a retry token.');
        },
        'logout expires the session and both authentication cookies' => static function () use ($logout): void {
            $expired = [];
            foreach ($logout['headers'] as $header) {
                if (str_starts_with($header, 'Set-Cookie:')) {
                    $pair = explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2)[0];
                    [$name] = explode('=', $pair, 2);
                    if (preg_match('/expires=([^;]+)/i', $header, $match) && strtotime($match[1]) < time()) {
                        $expired[] = $name;
                    }
                }
            }
            logout_assert(count(array_diff(['PHPSESSID', 'token', 'refresh_token'], $expired)) === 0, 'Expected expired PHPSESSID, token, and refresh_token cookies.');
        },
        'protected route rejects the logged-out session' => static function () use ($protected): void {
            $location = array_values(array_filter($protected['headers'], static fn (string $header): bool => str_starts_with(strtolower($header), 'location:')))[0] ?? '';
            logout_assert($protected['status'] === 302 && trim(substr($location, strlen('Location:'))) === '/login', 'Expected a protected route to redirect the unauthenticated session to login.');
        },
        'logger falls back to PHP error log when its configured path is unwritable' => static function () use ($logContents): void {
            logout_assert(str_contains($logContents, 'Logout ejecutado'), 'Expected the logout audit event in PHP error log fallback.');
        },
        'logout failure handlers do not redirect as if the session ended' => static function (): void {
            $actions = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/core/actions.alpine.js');
            $global = substr($actions, strpos($actions, 'window.performLogout'), strpos($actions, 'window.formatNum') - strpos($actions, 'window.performLogout'));
            $alpine = substr($actions, strpos($actions, 'async logout()'), strpos($actions, '// Función de utilidad para verificar si hay token') - strpos($actions, 'async logout()'));
            $globalCatch = substr($global, strpos($global, '} catch (err) {'));
            $alpineCatch = substr($alpine, strpos($alpine, '} catch (err) {'));
            logout_assert(!str_contains($globalCatch, "window.location.href = '/login'"), 'Unexpected failure redirect in the global logout handler.');
            logout_assert(!str_contains($alpineCatch, "window.location.href = '/login'"), 'Unexpected failure redirect in the Alpine logout handler.');

            $profile = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/perfil/index.actions.init.js');
            $helperStart = strpos($profile, 'async cerrarSesionYRedirigir()');
            $helper = substr($profile, $helperStart, strpos($profile, "\n    }));", $helperStart) - $helperStart);
            logout_assert(strpos($helper, "window.location.assign('/login')") > strpos($helper, 'response.data?.type'), 'Profile logout must redirect only after a successful server response.');
            logout_assert(!str_contains($helper, 'finally'), 'Profile logout must not redirect from finally after a failed request.');

            $passwordChange = substr($profile, strpos($profile, "const logoutResponse = await axios.post('/logout'"));
            logout_assert(strpos($passwordChange, "window.location.assign('/login')") > strpos($passwordChange, 'logoutResponse.data?.type'), 'Password-change flow must redirect only after successful logout.');
            logout_assert(!str_contains(substr($passwordChange, 0, strpos($passwordChange, 'catch (error)')), 'finally'), 'Password-change flow must not force redirect after logout failure.');
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
} finally {
    @unlink($errorLog);
    foreach (glob($sessionPath . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($sessionPath);
}
