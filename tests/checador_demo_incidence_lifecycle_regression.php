<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$original = [];
foreach (['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO', 'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] as $key) {
    $original[$key] = $_ENV[$key] ?? null;
}

$networkCalls = 0;
$tokenCalls = 0;
$http = new Client([
    'base_uri' => 'https://must-not-connect.invalid/',
    'handler' => static function () use (&$networkCalls): never {
        $networkCalls++;
        throw new RuntimeException('Demo lifecycle attempted outbound HTTP.');
    },
    'http_errors' => false,
]);
$make = static function (string $scenario) use ($http, &$tokenCalls): ChecadorDailyReadService {
    $_ENV['APP_ENV'] = 'dev';
    $_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'true';
    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = $scenario;
    $_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] = ChecadorDailyReadService::DEMO_REFERENCE_DATE;
    return new ChecadorDailyReadService(
        $http,
        static function (Usuario $user) use (&$tokenCalls): string {
            $tokenCalls++;
            return 'must-not-create-demo-jwt';
        },
        'https://must-not-connect.invalid/'
    );
};
$newUser = static function (): Usuario {
    $user = new Usuario();
    $user->id = random_int(2000000000, 2099999999);
    return $user;
};
$uuid = 'DEMO-INCIDENCE-PENDING-20261019';
$fields = ['resolution_notes' => 'Revisión sintética', 'evidence_reference' => ''];
$resolveActor = $newUser();
$resolveService = $make('incidence_pending_review');
$resolve = $resolveService->lifecycleAction($resolveActor, $uuid, 'resolve', 1, 'demo-resolve-unique-1', $fields, 14);
$assert(($resolve['ok'] ?? false) === true
    && ($resolve['data']['resolution_status'] ?? '') === 'RESOLVED_PENDING_AUTHORIZATION',
    'resolve creates the contract status in the local synthetic provider');

// A new service instance represents the GET following POST + redirect.
$afterRedirectReader = $make('incidence_pending_review');
$list = $afterRedirectReader->readApplicableIncidences($resolveActor, 900001, 14, '2026-10-19', '2026-10-19');
$summary = $list['data']['incidences'][0] ?? [];
$detail = $afterRedirectReader->readIncidence($resolveActor, $uuid);
$detailData = $detail['data'] ?? [];
$assert(($list['ok'] ?? false) === true
    && ($summary['current_status'] ?? '') === 'RESOLVED_PENDING_AUTHORIZATION'
    && ($summary['current_revision'] ?? 0) === 2
    && ($detailData['current_revision'] ?? 0) === 2
    && count($detailData['revisions'] ?? []) === 2
    && ($detailData['revisions'][0]['status'] ?? '') === 'PENDING_REVIEW'
    && ($detailData['revisions'][1]['status'] ?? '') === 'RESOLVED_PENDING_AUTHORIZATION',
    'state and append-only history survive a new service instance and canonical P3-G-A read');

$replay = $make('incidence_pending_review')->lifecycleAction($resolveActor, $uuid, 'resolve', 1, 'demo-resolve-unique-1', $fields, 14);
$readAfterReplay = $make('incidence_pending_review')->readIncidence($resolveActor, $uuid);
$assert(($replay['ok'] ?? false) === true
    && ($replay['data']['current_revision'] ?? 0) === 2
    && ($readAfterReplay['data']['current_revision'] ?? 0) === 2
    && count($readAfterReplay['data']['revisions'] ?? []) === 2,
    'an exact repeated Idempotency-Key returns the original result without a second revision');

$idempotencyConflict = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    $uuid,
    'resolve',
    1,
    'demo-resolve-unique-1',
    ['resolution_notes' => 'Payload distinto', 'evidence_reference' => ''],
    14
);
$assert(($idempotencyConflict['error']['kind'] ?? '') === 'idempotency_conflict', 'reusing a key with a different payload fails closed');

$stale = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    $uuid,
    'authorize',
    1,
    'demo-stale-unique-1',
    [],
    14
);
$assert(($stale['error']['kind'] ?? '') === 'stale_revision_conflict', 'stale expected_current_revision returns the existing conflict');

$authorized = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    $uuid,
    'authorize',
    2,
    'demo-authorize-unique-1',
    ['authorization_notes' => 'Autorización sintética'],
    14
);
$authorizedRead = $make('incidence_pending_review')->readIncidence($resolveActor, $uuid);
$assert(($authorized['ok'] ?? false) === true
    && ($authorizedRead['data']['current_status'] ?? '') === 'ACTIVE'
    && ($authorizedRead['data']['current_revision'] ?? 0) === 3
    && count($authorizedRead['data']['revisions'] ?? []) === 3
    && ($authorizedRead['data']['revisions'][2]['approved_by_user_id'] ?? null) === 900099,
    'authorize appends a synthetic approval revision and subsequent read shows ACTIVE');

$revoked = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    $uuid,
    'revoke',
    3,
    'demo-revoke-unique-1',
    ['revocation_reason' => 'Revocación sintética'],
    14
);
$revokedRead = $make('incidence_pending_review')->readIncidence($resolveActor, $uuid);
$assert(($revoked['ok'] ?? false) === true
    && ($revoked['data']['status'] ?? '') === 'CANCELLED'
    && ($revokedRead['data']['current_status'] ?? '') === 'CANCELLED'
    && ($revokedRead['data']['current_revision'] ?? 0) === 4
    && count($revokedRead['data']['revisions'] ?? []) === 4,
    'revoke appends a cancellation revision and readback preserves the full history');

$invalidTransition = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    $uuid,
    'authorize',
    4,
    'demo-invalid-unique-1',
    [],
    14
);
$unchangedAfterInvalid = $make('incidence_pending_review')->readIncidence($resolveActor, $uuid);
$assert(($invalidTransition['error']['kind'] ?? '') === 'invalid_lifecycle_transition'
    && ($unchangedAfterInvalid['data']['current_revision'] ?? 0) === 4,
    'terminal-state transition is rejected without changing synthetic history');

$missing = $make('incidence_pending_review')->lifecycleAction(
    $resolveActor,
    'DEMO-INCIDENCE-NOT-FOUND',
    'resolve',
    1,
    'demo-missing-unique-1',
    $fields,
    14
);
$assert(($missing['error']['kind'] ?? '') === 'incidence_not_found', 'an unknown synthetic incidence returns not found');

$wrongLocality = $make('incidence_pending_review')->lifecycleAction(
    $newUser(),
    $uuid,
    'resolve',
    1,
    'demo-wrong-locality-1',
    $fields,
    1
);
$assert(($wrongLocality['error']['kind'] ?? '') === 'api_authorization', 'a station-like ID that is not the fixture locality cannot mutate the incidence');

$rejectActor = $newUser();
$rejected = $make('incidence_pending_review')->lifecycleAction(
    $rejectActor,
    $uuid,
    'reject',
    1,
    'demo-reject-unique-1',
    ['rejection_reason' => 'Rechazo sintético'],
    14
);
$rejectedRead = $make('incidence_pending_review')->readIncidence($rejectActor, $uuid);
$assert(($rejected['ok'] ?? false) === true
    && ($rejectedRead['data']['current_status'] ?? '') === 'REJECTED'
    && count($rejectedRead['data']['revisions'] ?? []) === 2,
    'reject appends its synthetic revision and is visible on readback');

$unavailableActor = $newUser();
$unavailable = $make('incidence_service_unavailable')->lifecycleAction(
    $unavailableActor,
    'DEMO-INCIDENCE-ACTIVE-20261019',
    'revoke',
    1,
    'demo-unavailable-unique-1',
    ['revocation_reason' => 'No debe persistir'],
    14
);
$assert(($unavailable['error']['kind'] ?? '') === 'api_unavailable', 'simulated service outage fails without applying an action');

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$formatterSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorPresentationFormatter.php');
$authGuardAt = strpos($controllerSource, 'ChecadorAccessService::authorizeLifecycle($workplaceId, $action)');
$serviceCallAt = strpos($controllerSource, '$reader->lifecycleAction(');
$readbackAt = is_int($serviceCallAt)
    ? strpos($controllerSource, '$reader->readIncidence($user, $uuidIncidencia)', $serviceCallAt)
    : false;
$assert(is_int($authGuardAt) && is_int($serviceCallAt) && $authGuardAt < $serviceCallAt
    && str_contains($viewSource, "!empty(\$lifecyclePermissions['resolve'])")
    && str_contains($viewSource, 'Demostración sintética: estas acciones sólo cambian el historial local')
    && str_contains($viewSource, 'ChecadorPresentationFormatter::incidenceStatusLabel')
    && str_contains($formatterSource, "'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta; pendiente de autorización'")
    && str_contains($controllerSource, 'Acción confirmada en el historial sintético')
    && is_int($readbackAt) && $serviceCallAt < $readbackAt,
    'real per-action ACL remains before the provider, successful actions require canonical readback, and demo outcomes are labelled in Spanish');
$assert(str_contains(file_get_contents(__DIR__ . '/../app/Core/Route.php'), "['auth', 'csrf']")
    && str_contains(file_get_contents(__DIR__ . '/../app/Middleware/CsrfMiddleware.php'), "\$_POST['_csrf_token']"), 'existing CSRF middleware and form-token handling remain in the established route boundary');
$assert(str_contains($serviceSource, "\$headers['Idempotency-Key']")
    && str_contains($serviceSource, "'expected_current_revision' => \$expectedCurrentRevision")
    && str_contains($controllerSource, 'confirm_action'), 'the existing CSRF confirmation, expected revision and idempotency contract remain intact');
$assert($networkCalls === 0 && $tokenCalls === 0, 'all demo reads and lifecycle actions remain local with no API calls or JWT generation');

// Remove only the isolated state files created above by random test actors.
$runtimeUid = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'current-runtime';
$demoStateDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'portal3-checador-demo-v4b-' . $runtimeUid;
$applicationRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
foreach ([[$resolveActor, 'incidence_pending_review'], [$rejectActor, 'incidence_pending_review']] as [$actor, $scenario]) {
    $key = hash('sha256', $applicationRoot . "\0" . (string) ((int) $actor->id) . "\0" . $scenario);
    foreach ([$key . '.json', $key . '.lock'] as $filename) {
        $path = $demoStateDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }
}

foreach ($original as $key => $value) {
    if ($value === null) unset($_ENV[$key]); else $_ENV[$key] = $value;
}

printf("CHECADOR_DEMO_INCIDENCE_LIFECYCLE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
