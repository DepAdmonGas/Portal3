<?php

declare(strict_types=1);

function layout_csrf_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function layout_csrf_request(string $method, string $path, string $sessionPath, ?string $cookie = null, ?string $csrfToken = null): array
{
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/layout_csrf_logout_router.php',
        'SCRIPT_NAME' => '/tests/layout_csrf_logout_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'LAYOUT_CSRF_LOGOUT_SESSION_TEST_PATH' => $sessionPath,
    ];
    if ($cookie !== null) {
        $environment['HTTP_COOKIE'] = $cookie;
    }
    if ($csrfToken !== null) {
        $environment['HTTP_X_CSRF_TOKEN'] = $csrfToken;
    }

    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start layout CSRF logout fixture.');
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Layout CSRF logout fixture failed: ' . trim($stderr));
    }

    [$rawHeaders, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $status = 200;
    $issuedCookie = null;
    foreach (preg_split('/\r?\n/', $rawHeaders) as $header) {
        if (preg_match('/^Status:\s+(\d{3})/i', $header, $match)) {
            $status = (int) $match[1];
        }
        if (str_starts_with($header, 'Set-Cookie:')) {
            $issuedCookie = explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2)[0];
        }
    }

    return ['status' => $status, 'body' => $body, 'cookie' => $issuedCookie];
}

function layout_has_csrf_bootstrap(string $layout): bool
{
    $contents = (string) file_get_contents(dirname(__DIR__) . '/app/Views/layouts/' . $layout . '.php');

    return str_contains($contents, '<meta name="csrf-token" content="<?= \\App\\Core\\CsrfToken::token() ?>">')
        && str_contains($contents, "axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken")
        && str_contains($contents, "config.headers['X-CSRF-TOKEN'] = getCsrfToken()");
}

function layout_has_no_get_logout_anchor(string $layout): bool
{
    $contents = (string) file_get_contents(dirname(__DIR__) . '/app/Views/layouts/' . $layout . '.php');

    return !str_contains($contents, 'href="/logout"')
        && substr_count($contents, 'onclick="performLogout()"') === 2;
}

function layout_csrf_logout_flow(): array
{
    $sessionPath = sys_get_temp_dir() . '/portal3-layout-csrf-logout-' . bin2hex(random_bytes(8));
    mkdir($sessionPath, 0700, true);
    $login = layout_csrf_request('POST', '/login', $sessionPath);
    $data = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR);

    return [
        'login' => $login,
        'valid' => layout_csrf_request('POST', '/logout', $sessionPath, $login['cookie'], $data['csrf']),
        'missing' => layout_csrf_request('POST', '/logout', $sessionPath, $login['cookie']),
        'invalid' => layout_csrf_request('POST', '/logout', $sessionPath, $login['cookie'], str_repeat('0', 64)),
    ];
}

$sgmFlow = layout_csrf_logout_flow();
$sasisopaFlow = layout_csrf_logout_flow();
$configuracionFlow = layout_csrf_logout_flow();

$tests = [
    'SGM layout exposes the current CSRF meta and Axios request bootstrap' => static fn (): bool => layout_has_csrf_bootstrap('sgm'),
    'SASISOPA layout exposes the current CSRF meta and Axios request bootstrap' => static fn (): bool => layout_has_csrf_bootstrap('sasisopa'),
    'Configuracion layout exposes the current CSRF meta and Axios request bootstrap' => static fn (): bool => layout_has_csrf_bootstrap('configuracion'),
    'Configuracion layout no longer exposes a GET logout anchor' => static fn (): bool => layout_has_no_get_logout_anchor('configuracion'),
    'SGM current CSRF contract authorizes POST logout' => static fn (): bool => $sgmFlow['login']['status'] === 200 && $sgmFlow['valid']['status'] === 200,
    'SASISOPA current CSRF contract authorizes POST logout' => static fn (): bool => $sasisopaFlow['login']['status'] === 200 && $sasisopaFlow['valid']['status'] === 200,
    'Configuracion current CSRF contract authorizes POST logout' => static fn (): bool => $configuracionFlow['login']['status'] === 200 && $configuracionFlow['valid']['status'] === 200,
    'logout without or with an invalid CSRF token remains rejected' => static fn (): bool => $sgmFlow['missing']['status'] === 419 && $sasisopaFlow['invalid']['status'] === 419,
];

$passed = 0;
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        layout_csrf_assert($test(), $name);
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

echo "RESULT passed={$passed} failed={$failed} skipped=0\n";
exit($failed === 0 ? 0 : 1);
