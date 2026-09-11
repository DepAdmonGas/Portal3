<?php

declare(strict_types=1);

function post_login_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function post_login_request(string $method, string $path, string $sessionPath, string $appEnv, bool $https, array $jar, bool $initializeBeforeEnvironment = false): array
{
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/session_post_login_router.php',
        'SCRIPT_NAME' => '/tests/session_post_login_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'POST_LOGIN_SESSION_TEST_PATH' => $sessionPath,
        'POST_LOGIN_APP_ENV' => $appEnv,
        'POST_LOGIN_HTTPS' => $https ? '1' : '0',
        'POST_LOGIN_INIT_BEFORE_ENV' => $initializeBeforeEnvironment ? '1' : '0',
    ];
    if ($jar !== []) {
        $environment['HTTP_COOKIE'] = implode('; ', array_map(static fn (array $cookie): string => $cookie['pair'], $jar));
    }

    $process = proc_open(['php-cgi'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start post-login fixture.');
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Post-login fixture failed: ' . trim($stderr));
    }

    [$rawHeaders, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $headers = preg_split('/\r?\n/', $rawHeaders);
    $status = 200;
    $location = null;
    foreach ($headers as $header) {
        if (preg_match('/^Status:\s+(\d{3})/i', $header, $match)) $status = (int) $match[1];
        if (str_starts_with(strtolower($header), 'location:')) $location = trim(substr($header, 9));
        if (str_starts_with($header, 'Set-Cookie:')) {
            $parts = array_map('trim', explode(';', substr($header, strlen('Set-Cookie:'))));
            $pair = array_shift($parts);
            $name = explode('=', $pair, 2)[0];
            $secure = in_array('secure', array_map('strtolower', $parts), true);
            if (!$secure || $https) $jar[$name] = ['pair' => $pair, 'secure' => $secure];
        }
    }

    return ['status' => $status, 'location' => $location, 'body' => $body, 'headers' => $headers, 'jar' => $jar];
}

function post_login_flow(string $appEnv, bool $https, bool $initializeBeforeEnvironment = false): array
{
    $sessionPath = sys_get_temp_dir() . '/portal3-post-login-' . bin2hex(random_bytes(8));
    mkdir($sessionPath, 0700, true);
    $loginPage = post_login_request('GET', '/login', $sessionPath, $appEnv, $https, [], $initializeBeforeEnvironment);
    $loginPost = post_login_request('POST', '/login/acceso', $sessionPath, $appEnv, $https, $loginPage['jar'], $initializeBeforeEnvironment);
    $home = post_login_request('GET', '/home', $sessionPath, $appEnv, $https, $loginPost['jar'], $initializeBeforeEnvironment);
    return ['login_page' => $loginPage, 'login_post' => $loginPost, 'home' => $home];
}

$httpFlow = post_login_flow('dev', false);
$httpsFlow = post_login_flow('prod', true);
$historicalHttpFlow = post_login_flow('dev', false, true);

$tests = [
    'local HTTP browser keeps non-Secure authentication cookies after login' => static function () use ($httpFlow): void {
        post_login_assert($httpFlow['login_post']['status'] === 200, 'Expected a successful login response.');
        post_login_assert($httpFlow['home']['status'] === 200 && $httpFlow['home']['location'] === null, 'Expected the browser cookie jar to reach authenticated home over local HTTP.');
        post_login_assert(json_decode($httpFlow['home']['body'], true, 512, JSON_THROW_ON_ERROR)['authenticated'] === true, 'Expected authenticated home state.');
    },
    'production HTTPS browser receives and returns Secure authentication cookies' => static function () use ($httpsFlow): void {
        $headers = implode("\n", $httpsFlow['login_post']['headers']);
        post_login_assert(str_contains($headers, 'secure'), 'Expected Secure cookies over HTTPS.');
        post_login_assert($httpsFlow['home']['status'] === 200 && $httpsFlow['home']['location'] === null, 'Expected authenticated home over HTTPS.');
    },
    'historical bootstrap order reproduces the local post-login redirect' => static function () use ($historicalHttpFlow): void {
        post_login_assert($historicalHttpFlow['login_post']['status'] === 200, 'Expected credentials to be accepted before the redirect failure.');
        post_login_assert($historicalHttpFlow['home']['status'] === 302 && $historicalHttpFlow['home']['location'] === '/login', 'Expected the old bootstrap order to lose the Secure session cookie on HTTP.');
    },
    'environment loads before session initialization in the application bootstrap' => static function (): void {
        $bootstrap = file_get_contents(dirname(__DIR__) . '/public/index.php');
        post_login_assert(strpos($bootstrap, '$dotenv->safeLoad()') < strpos($bootstrap, 'Session::init()'), 'Expected environment loading before session initialization.');
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
