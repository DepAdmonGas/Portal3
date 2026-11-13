<?php

declare(strict_types=1);

function csrf_post_login_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function csrf_post_login_request(
    string $method,
    string $path,
    string $sessionPath,
    array $jar,
    ?string $csrfToken = null,
    array $postFields = []
): array
{
    $body = $postFields === [] ? '' : http_build_query($postFields);
    $environment = [
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => __DIR__ . '/csrf_post_login_router.php',
        'SCRIPT_NAME' => '/tests/csrf_post_login_router.php',
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'CONTENT_LENGTH' => (string) strlen($body),
        'CSRF_POST_LOGIN_SESSION_TEST_PATH' => $sessionPath,
    ];
    if ($postFields !== []) {
        $environment['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $environment['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
    }
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
    fwrite($pipes[0], $body);
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
    $location = null;
    foreach (preg_split('/\r?\n/', $rawHeaders) as $header) {
        if (preg_match('/^Status:\s+(\d{3})/i', $header, $match)) {
            $status = (int) $match[1];
        }
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
        }
        if (str_starts_with($header, 'Set-Cookie:')) {
            $pair = trim(explode(';', substr($header, strlen('Set-Cookie:')), 2)[0]);
            $jar[explode('=', $pair, 2)[0]] = $pair;
        }
    }

    return ['status' => $status, 'body' => $body, 'jar' => $jar, 'location' => $location];
}

$sessionPath = sys_get_temp_dir() . '/portal3-csrf-post-login-' . bin2hex(random_bytes(8));
mkdir($sessionPath, 0700, true);
$anonymousForm = csrf_post_login_request('GET', '/anonymous-form', $sessionPath, []);
$anonymousToken = json_decode($anonymousForm['body'], true, 512, JSON_THROW_ON_ERROR)['csrf_token'];
$login = csrf_post_login_request('POST', '/login', $sessionPath, $anonymousForm['jar']);
$authenticatedForm = csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $login['jar']);
$authenticatedToken = json_decode($authenticatedForm['body'], true, 512, JSON_THROW_ON_ERROR)['csrf_token'];
$validMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $authenticatedToken);
$secondValidMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $authenticatedToken);
$missingMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar']);
$invalidMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], str_repeat('0', 64));
$anonymousMutation = csrf_post_login_request('POST', '/mutation', $sessionPath, $authenticatedForm['jar'], $anonymousToken);

$incidenceCreatePath = '/departamento-operativo/recursos-humanos/checador/incidencias/crear';
$currentFormToken = json_decode(
    csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
)['csrf_token'];
$incidenceFormFields = [
    '_csrf_token' => $currentFormToken,
    'workplace_id' => '1',
    'worker_id' => '1004',
    'return_date' => '2026-10-28',
    'idempotency_key' => 'csrf-incidence-test-key',
    'category' => 'JUSTIFICATION',
    'start_date' => '2026-10-28',
    'end_date' => '2026-10-28',
    'reason' => 'QA form retained after CSRF rejection.',
    'evidence_reference' => 'QA-CSRF-01',
    'confirm_create' => '1',
];
csrf_post_login_request('GET', '/reset-csrf-marker', $sessionPath, $authenticatedForm['jar']);
$formTokenWithStaleHeader = csrf_post_login_request(
    'POST',
    $incidenceCreatePath,
    $sessionPath,
    $authenticatedForm['jar'],
    'stale-header-token',
    $incidenceFormFields
);
$staleHeaderHandlerState = json_decode(
    csrf_post_login_request('GET', '/csrf-handler-state', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);

csrf_post_login_request('GET', '/reset-csrf-marker', $sessionPath, $authenticatedForm['jar']);
$invalidFormFields = array_merge($incidenceFormFields, ['_csrf_token' => 'invalid-form-token']);
$invalidFormWithValidHeader = csrf_post_login_request(
    'POST',
    $incidenceCreatePath,
    $sessionPath,
    $authenticatedForm['jar'],
    $currentFormToken,
    $invalidFormFields
);
$invalidFormHandlerState = json_decode(
    csrf_post_login_request('GET', '/csrf-handler-state', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);
$invalidFormFeedback = json_decode(
    csrf_post_login_request('GET', '/incidence-create-feedback', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);

$tokenBeforeExpiredForm = json_decode(
    csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
)['csrf_token'];
csrf_post_login_request('GET', '/expire-csrf-token', $sessionPath, $authenticatedForm['jar']);
csrf_post_login_request('GET', '/reset-csrf-marker', $sessionPath, $authenticatedForm['jar']);
$expiredFormPost = csrf_post_login_request(
    'POST',
    $incidenceCreatePath,
    $sessionPath,
    $authenticatedForm['jar'],
    null,
    array_merge($incidenceFormFields, ['_csrf_token' => $tokenBeforeExpiredForm])
);
$expiredFormHandlerState = json_decode(
    csrf_post_login_request('GET', '/csrf-handler-state', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);
$expiredFormFeedback = json_decode(
    csrf_post_login_request('GET', '/incidence-create-feedback', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);
$recoveredForm = json_decode(
    csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);
$recoveredFormPost = csrf_post_login_request(
    'POST',
    $incidenceCreatePath,
    $sessionPath,
    $authenticatedForm['jar'],
    null,
    array_merge($incidenceFormFields, ['_csrf_token' => $recoveredForm['csrf_token']])
);

$oldFreshPageToken = json_decode(
    csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
)['csrf_token'];
csrf_post_login_request('GET', '/expire-csrf-token', $sessionPath, $authenticatedForm['jar']);
$newFreshPageForm = json_decode(
    csrf_post_login_request('GET', '/authenticated-form', $sessionPath, $authenticatedForm['jar'])['body'],
    true,
    512,
    JSON_THROW_ON_ERROR
);
$newFreshPagePost = csrf_post_login_request(
    'POST',
    $incidenceCreatePath,
    $sessionPath,
    $authenticatedForm['jar'],
    null,
    array_merge($incidenceFormFields, ['_csrf_token' => $newFreshPageForm['csrf_token']])
);

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
    'valid form token is authoritative over a stale global CSRF header' => static function () use ($formTokenWithStaleHeader, $staleHeaderHandlerState): void {
        csrf_post_login_assert($formTokenWithStaleHeader['status'] === 200, 'Expected a valid posted form token to pass despite a stale header.');
        csrf_post_login_assert($staleHeaderHandlerState['reached'] === true, 'Expected the handler to run only after validating the form token.');
    },
    'invalid form token is rejected even when a valid header token is present' => static function () use ($invalidFormWithValidHeader, $invalidFormHandlerState, $invalidFormFeedback): void {
        csrf_post_login_assert($invalidFormWithValidHeader['status'] === 303, 'Expected the invalid native form request to be rejected and returned to the form.');
        csrf_post_login_assert(str_starts_with((string) $invalidFormWithValidHeader['location'], '/departamento-operativo/recursos-humanos/checador/incidencias?'), 'Expected recovery to use the existing GET list route.');
        csrf_post_login_assert($invalidFormHandlerState['reached'] === false, 'Expected invalid form token not to reach the mutation handler.');
        csrf_post_login_assert($invalidFormFeedback['type'] === 'error' && $invalidFormFeedback['reason'] === 'QA form retained after CSRF rejection.' && $invalidFormFeedback['confirmed'] === true, 'Expected a readable error, safe form-value recovery, and retained explicit confirmation.');
    },
    'expired native incidence form is rejected, retained, and gets a fresh token for explicit confirmation' => static function () use ($expiredFormPost, $expiredFormHandlerState, $expiredFormFeedback, $tokenBeforeExpiredForm, $recoveredForm, $recoveredFormPost): void {
        csrf_post_login_assert($expiredFormPost['status'] === 303, 'Expected expired CSRF to reject the POST and redirect to the existing form route.');
        csrf_post_login_assert($expiredFormHandlerState['reached'] === false, 'Expected expired CSRF not to reach the mutation handler.');
        csrf_post_login_assert($expiredFormFeedback['type'] === 'error' && $expiredFormFeedback['reason'] === 'QA form retained after CSRF rejection.' && $expiredFormFeedback['confirmed'] === true, 'Expected submitted values, explicit confirmation, and an operational error message to be retained.');
        csrf_post_login_assert($recoveredForm['csrf_token'] !== $tokenBeforeExpiredForm, 'Expected the recovered GET form to issue a new CSRF token.');
        csrf_post_login_assert($recoveredFormPost['status'] === 200, 'Expected an explicit new POST with the refreshed form token to pass.');
    },
    'a freshly loaded form rotates an expired session token before submitting' => static function () use ($oldFreshPageToken, $newFreshPageForm, $newFreshPagePost): void {
        csrf_post_login_assert($newFreshPageForm['csrf_token'] !== $oldFreshPageToken, 'Expected a fresh GET not to render an expired token.');
        csrf_post_login_assert($newFreshPagePost['status'] === 200, 'Expected the fresh form token to validate.');
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
