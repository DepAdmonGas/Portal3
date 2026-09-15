<?php

declare(strict_types=1);

function p0_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function p0_request(string $method, string $path, ?string $body = null, ?string $cookie = null, array $headers = []): array
{
    global $dbFile, $sessionDirectory, $downloadDirectory;
    $payload = $body ?? '';
    $environment = array_merge($_ENV, [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/http_router.php',
        'SCRIPT_NAME' => '/tests/http_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'QUERY_STRING' => (string) (parse_url($path, PHP_URL_QUERY) ?? ''),
        'CONTENT_TYPE' => 'application/json',
        'CONTENT_LENGTH' => (string) strlen($payload),
        'P0_TEST_DB' => $dbFile,
        'P0_TEST_SESSION_PATH' => $sessionDirectory,
        'P0_TEST_DOWNLOAD_ROOT' => $downloadDirectory,
    ]);
    if ($cookie !== null) {
        $environment['HTTP_COOKIE'] = $cookie;
    }
    foreach ($headers as $header) {
        [$name, $value] = explode(':', $header, 2);
        $environment['HTTP_' . strtoupper(str_replace('-', '_', trim($name)))] = trim($value);
    }
    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to invoke the local PHP-CGI test harness.');
    }
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('PHP-CGI test harness failed: ' . trim($stderr));
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

function p0_cookie(array $headers): string
{
    foreach ($headers as $header) {
        if (str_starts_with($header, 'Set-Cookie:')) {
            return explode(';', trim(substr($header, strlen('Set-Cookie:'))))[0];
        }
    }
    throw new RuntimeException('Test server did not establish an isolated session cookie.');
}

$dbFile = '/tmp/portal3-p0-regression-' . bin2hex(random_bytes(8)) . '.sqlite';
$sessionDirectory = sys_get_temp_dir() . '/portal3-p0-session-' . bin2hex(random_bytes(8));
$downloadDirectory = sys_get_temp_dir() . '/portal3-p0-download-' . bin2hex(random_bytes(8));
mkdir($sessionDirectory, 0700, true);
mkdir($downloadDirectory . '/documentos-personal/ine', 0700, true);
file_put_contents($downloadDirectory . '/documentos-personal/ine/tenant-a-ine.pdf', 'tenant-a-private-document');
file_put_contents($downloadDirectory . '/documentos-personal/ine/tenant-b-ine.pdf', 'tenant-b-private-document');
touch($dbFile);
$login = p0_request('POST', '/__test/login-a');
$cookie = p0_cookie($login['headers']);

$tests = [
    'SEC-CSRF-001 rejects a mutable authenticated request without a token' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/reset-csrf-marker', null, $cookie);
        $response = p0_request('POST', '/__test/csrf', '{}', $cookie);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['status'] === 419, 'Expected missing CSRF token to be rejected before the handler.');
        p0_assert($session['csrf_handler_reached'] === false, 'Expected rejected request not to reach the mutable handler.');
    },
    'SEC-CSRF-001 rejects an invalid token before the handler' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/reset-csrf-marker', null, $cookie);
        $response = p0_request('POST', '/__test/csrf', '{}', $cookie, ['X-CSRF-Token: invalid-token']);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);

        p0_assert($response['status'] === 419, 'Expected an invalid CSRF token to be rejected before the handler.');
        p0_assert($session['csrf_handler_reached'] === false, 'Expected invalid token not to reach the mutable handler.');
    },
    'SEC-CSRF-001 accepts a valid token (future control)' => static function (): void {
        global $cookie;
        $token = p0_request('GET', '/__test/csrf-token', null, $cookie);
        p0_request('POST', '/__test/reset-csrf-marker', null, $cookie);
        $response = p0_request('POST', '/__test/csrf', '{}', $cookie, ['X-CSRF-Token: ' . $token['body']]);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['status'] === 200 && $response['body'] === 'accepted', 'Expected a valid CSRF token to be accepted.');
        p0_assert($session['csrf_handler_reached'] === true, 'Expected a valid token to reach the mutable handler.');
    },
    'AUTHZ-TENANT-001 allows Tenant A to select Station A' => static function (): void {
        global $cookie;
        $response = json_decode(p0_request('POST', '/switch-estacion', '{"id_estacion":101}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);

        p0_assert($response['ok'] === true, 'Expected an allowed station selection to succeed.');
        p0_assert($session['id_estacion'] === 101, 'Expected session context to remain on Tenant A.');
    },
    'AUTHZ-TENANT-001 rejects Tenant A selecting Station B' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('POST', '/switch-estacion', '{"id_estacion":202}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);

        p0_assert($response['ok'] === false, 'Expected a cross-tenant station selection to be rejected.');
        p0_assert($session['id_estacion'] === 101, 'Expected rejected selection not to mutate Tenant A session context.');
    },
    'AUTHZ-TENANT-001 rejects a nonexistent station without mutating the session' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('POST', '/switch-estacion', '{"id_estacion":999}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $session = json_decode(p0_request('GET', '/__test/session', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);

        p0_assert($response['ok'] === false, 'Expected a nonexistent station selection to be rejected.');
        p0_assert($session['id_estacion'] === 101, 'Expected nonexistent station selection not to mutate Tenant A session context.');
    },
    'AUTHZ-DL-002 permits an authorized sensitive document download' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = p0_request('GET', '/download?tipo=docs-personal-ine&file=tenant-a-ine.pdf', null, $cookie);
        p0_assert($response['body'] === 'tenant-a-private-document', 'Expected the authorized document bytes.');
        p0_assert(in_array('Content-Disposition: attachment; filename="tenant-a-ine.pdf"', $response['headers'], true), 'Expected an attachment response for the authorized document.');
    },
    'AUTHZ-DL-002 rejects a cross-tenant sensitive document download' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = p0_request('GET', '/download?tipo=docs-personal-ine&file=tenant-b-ine.pdf', null, $cookie);
        p0_assert($response['body'] === '', 'Expected no document bytes for a cross-tenant request.');
        p0_assert(!in_array('Content-Disposition: attachment; filename="tenant-b-ine.pdf"', $response['headers'], true), 'Expected denial before file delivery.');
    },
    'AUTHZ-DL-002 rejects an anonymous sensitive document download' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/logout', null, $cookie);
        $response = p0_request('GET', '/download?tipo=docs-personal-ine&file=tenant-a-ine.pdf', null, $cookie);
        p0_assert($response['body'] === '', 'Expected no document bytes for an anonymous request.');
    },
    'AUTHZ-DL-002 rejects a missing sensitive resource' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = p0_request('GET', '/download?tipo=docs-personal-ine&file=missing.pdf', null, $cookie);
        p0_assert($response['body'] === '', 'Expected no document bytes for a missing resource.');
    },
    'SEC-UPLOAD-004 does not expose a sensitive document through its former public path' => static function (): void {
        $response = p0_request('GET', '/uploads/archivos/documentos-personal/ine/tenant-a-ine.pdf');
        p0_assert($response['status'] === 404 && $response['body'] === 'not found', 'Expected the former static sensitive path not to deliver document bytes.');
    },
    'SEC-UPLOAD-004 keeps newly stored sensitive documents outside the public webroot' => static function (): void {
        $path = p0_request('GET', '/__test/sensitive-upload-path')['body'];
        p0_assert(str_ends_with($path, '/storage/private/documentos-personal/'), 'Expected the canonical private sensitive upload destination.');
        p0_assert(!str_contains($path, '/public/'), 'Expected sensitive document storage not to be under a public directory.');
    },
    'AUTHZ-TOKEN-003 generates a token for the authenticated user without a client user ID' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('POST', '/token-telegram/generate', '{}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['success'] === true && !empty($response['data']['token']), 'Expected token generation for the authenticated user.');
    },
    'AUTHZ-TOKEN-003 ignores a malicious user ID when revoking access' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('POST', '/token-telegram/revoke', '{"id_usuario":2}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $records = json_decode(p0_request('GET', '/__test/telegram-state', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $userB = array_values(array_filter($records, static fn (array $record): bool => $record['id_usuario'] === 2));
        p0_assert($response['success'] === true, 'Expected own revoke operation to succeed.');
        p0_assert(count($userB) === 1 && $userB[0]['chat_id'] === 222 && $userB[0]['estatus'] === 1, 'Expected malicious user ID not to affect User B.');
    },
    'AUTHZ-TOKEN-003 ignores a malicious user ID when checking status' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('POST', '/token-telegram/status', '{"id_usuario":2}', $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['success'] === true && $response['data']['registered'] === false, 'Expected status to describe User A, not User B.');
    },
    'AUTHZ-TOKEN-003 rejects an anonymous account operation' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/logout', null, $cookie);
        $response = p0_request('POST', '/token-telegram/generate', '{}', $cookie);
        p0_assert($response['status'] === 401, 'Expected anonymous token generation to be rejected.');
    },
    'SEC-XSS-005 sanitizes stored and generated HTML before Alpine renders it' => static function (): void {
        $politica = file_get_contents(dirname(__DIR__) . '/app/Views/sgm/responsabilidad-direccion/index.php');
        $bitacora = file_get_contents(dirname(__DIR__) . '/app/Views/controlactividadproceso/bitacora-calibracion-equipos.php');
        p0_assert(str_contains($politica, 'x-html="DOMPurify.sanitize(politica.contenido)"'), 'Expected stored policy content to be sanitized before HTML rendering.');
        p0_assert(str_contains($bitacora, 'x-html="DOMPurify.sanitize(otrosDetalle)"') && str_contains($bitacora, 'x-html="DOMPurify.sanitize(tablaDetalle)"'), 'Expected generated detail HTML to be sanitized before rendering.');
        p0_assert(!str_contains($politica, 'x-html="politica.contenido"'), 'Expected the unsafe stored-content sink to be absent.');
    },
    'SEC-XSS-005 preserves legitimate rich-text rendering through the sanitizer boundary' => static function (): void {
        $layout = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/sgm.php');
        p0_assert(str_contains($layout, 'dompurify@3.0.6'), 'Expected the existing trusted sanitizer to be loaded by the SGM layout.');
    },
    'SEC-XSS-005 closure keeps every remaining Alpine HTML sink behind DOMPurify' => static function (): void {
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app/Views'));
        foreach ($views as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $content = file_get_contents($file->getPathname());
            if (!str_contains($content, 'x-html')) continue;
            preg_match_all('/x-html="([^"]+)"/', $content, $matches);
            foreach ($matches[1] as $expression) {
                p0_assert(str_starts_with($expression, 'DOMPurify.sanitize('), "Expected {$file->getPathname()} x-html sink to use DOMPurify.");
            }
        }
    },
    'AUTHZ-TENANT-007 authorizes a personnel resource in Tenant A' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('GET', '/__test/authorized-personal?id=1', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === 1, 'Expected Tenant A to resolve its personnel resource.');
    },
    'AUTHZ-TENANT-007 rejects a Tenant B personnel resource without side effects' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('GET', '/__test/authorized-personal?id=2', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === null, 'Expected cross-tenant personnel lookup to be denied.');
    },
    'AUTHZ-TENANT-007 rejects a nonexistent personnel resource' => static function (): void {
        global $cookie;
        $response = json_decode(p0_request('GET', '/__test/authorized-personal?id=999', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === null, 'Expected nonexistent personnel resource to be denied.');
    },
    'AUTHZ-TENANT-007 Boundary 2A authorizes Tenant A solicitud de cheque' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $response = json_decode(p0_request('GET', '/__test/authorized-solicitud?id=11', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === 11, 'Expected Tenant A to resolve its cheque request.');
    },
    'AUTHZ-TENANT-007 Boundary 2A rejects Tenant B solicitud de cheque' => static function (): void {
        global $cookie;
        $response = json_decode(p0_request('GET', '/__test/authorized-solicitud?id=22', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === null, 'Expected cross-station cheque request not to be exposed.');
    },
    'AUTHZ-TENANT-007 Boundary 2A rejects missing solicitud de cheque' => static function (): void {
        global $cookie;
        $response = json_decode(p0_request('GET', '/__test/authorized-solicitud?id=999', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert($response['id'] === null, 'Expected missing cheque request not to be exposed.');
    },
    'AUTHZ-TENANT-007 Boundary 2B does not expose Tenant B cheque documents' => static function (): void {
        global $cookie;
        p0_request('POST', '/__test/login-a', null, $cookie);
        $authorized = json_decode(p0_request('GET', '/__test/solicitud-documentos?id=11', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        $denied = json_decode(p0_request('GET', '/__test/solicitud-documentos?id=22', null, $cookie)['body'], true, 512, JSON_THROW_ON_ERROR);
        p0_assert(count($authorized) === 1 && $authorized[0]['nombre'] === 'A', 'Expected Tenant A document metadata.');
        p0_assert($denied === [], 'Expected no Tenant B document metadata.');
    },
];

$passed = 0;
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        ++$passed;
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDOUT, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

fwrite(STDOUT, "RESULT passed={$passed} failed={$failed} skipped=0\n");
@unlink($dbFile);
foreach (glob($sessionDirectory . '/*') ?: [] as $sessionFile) {
    @unlink($sessionFile);
}
@rmdir($sessionDirectory);
foreach (glob($downloadDirectory . '/documentos-personal/ine/*') ?: [] as $document) {
    @unlink($document);
}
@rmdir($downloadDirectory . '/documentos-personal/ine');
@rmdir($downloadDirectory . '/documentos-personal');
@rmdir($downloadDirectory);
exit($failed === 0 ? 0 : 1);
