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

$environmentKeys = ['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO', 'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'];
$originalEnvironment = [];
foreach ($environmentKeys as $key) {
    $originalEnvironment[$key] = $_ENV[$key] ?? null;
}
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'true';
$_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] = ChecadorDailyReadService::DEMO_REFERENCE_DATE;

$networkCalls = 0;
$tokenCalls = 0;
$user = new Usuario();
$user->id = random_int(2100000000, 2147480000);
$scenarios = [
    'evaluation_pending_review',
    'evaluation_data_incomplete',
    'evaluation_incidence_conflict',
    'evaluation_available',
    'evaluation_stale_calculation',
    'evaluation_unauthorized',
    'evaluation_duplicate',
    'evaluation_service_unavailable',
    'evaluation_falta_authorized',
];
$services = [];
$makeService = static function (string $scenario) use (&$networkCalls, &$tokenCalls, $user, &$services): ChecadorDailyReadService {
    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = $scenario;
    $http = new Client([
        'base_uri' => 'https://must-not-connect.invalid/',
        'handler' => static function () use (&$networkCalls): never {
            $networkCalls++;
            throw new RuntimeException('Unexpected outbound request from synthetic demo.');
        },
    ]);
    $service = new ChecadorDailyReadService(
        $http,
        static function (Usuario $user) use (&$tokenCalls): string {
            $tokenCalls++;
            return 'must-not-generate-a-jwt';
        },
        'https://must-not-connect.invalid/'
    );
    $services[$scenario] = $service;
    return $service;
};

$tuple = [900001, 14, '2026-10-19']; // worker, canonical locality, local date from prior V3 demo fixtures
$expectedStatuses = [
    'evaluation_pending_review' => ['REVIEW_REQUIRED', 'PENDING_REVIEW'],
    'evaluation_data_incomplete' => ['DATA_INCOMPLETE', 'PENDING_REVIEW'],
    'evaluation_incidence_conflict' => ['OVERLAP_CONFLICT', 'PENDING_REVIEW'],
    'evaluation_available' => ['VACATION_APPLIED', 'NOT_REQUIRED'],
];

foreach ($expectedStatuses as $scenario => [$expectedStatus, $expectedReview]) {
    $service = $makeService($scenario);
    $result = $service->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-' . $scenario, 900001);
    $readback = $service->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
    $evaluation = $readback['data']['evaluation'] ?? [];
    $assert(($result['ok'] ?? false) === true
        && ($result['data']['administrative_status'] ?? '') === $expectedStatus
        && ($readback['ok'] ?? false) === true
        && ($readback['data']['evaluation_exists'] ?? false) === true
        && ($evaluation['administrative_status'] ?? '') === $expectedStatus
        && ($evaluation['human_review_status'] ?? '') === $expectedReview
        && ($evaluation['associated_calculation_revision_id'] ?? 0) === 900001
        && ($evaluation['fingerprint'] ?? '') === 'DEMO-ONLY-NOT-A-CRYPTOGRAPHIC-FINGERPRINT',
        "{$scenario} returns only its fixed contract outcome and persists a synthetic readback");
}

$conflictReadback = $services['evaluation_incidence_conflict']->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
$conflictLinks = $conflictReadback['data']['evaluation']['linked_incidences'] ?? [];
$assert(count($conflictLinks) === 2
    && array_column($conflictLinks, 'uuid_incidencia') === [
        'DEMO-INCIDENCE-CONFLICT-VACATION-20261019',
        'DEMO-INCIDENCE-CONFLICT-PERMISSION-20261019',
    ], 'incidence conflict keeps both established synthetic references and canonical locality 14');

$staleService = $makeService('evaluation_stale_calculation');
$stale = $staleService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-stale', 900001);
$staleReadback = $staleService->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
$assert(($stale['error']['kind'] ?? '') === 'stale_revision_conflict'
    && ($staleReadback['ok'] ?? false) === true
    && ($staleReadback['data']['evaluation_exists'] ?? true) === false,
    'stale calculation reference is a conflict and does not persist a result');

$unauthorizedService = $makeService('evaluation_unauthorized');
$unauthorized = $unauthorizedService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-denied', 900001);
$assert(($unauthorized['error']['kind'] ?? '') === 'api_authorization', 'synthetic function-permission denial stays denied and cannot become a success');

$duplicateService = $makeService('evaluation_duplicate');
$duplicateFirst = $duplicateService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-same-key', 900001);
$duplicateReplay = $duplicateService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-same-key', 900001);
$duplicateConflict = $duplicateService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-same-key', 900002);
$duplicateReadback = $duplicateService->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
$assert(($duplicateFirst['ok'] ?? false) === true
    && ($duplicateReplay['data'] ?? null) === ($duplicateFirst['data'] ?? null)
    && ($duplicateConflict['error']['kind'] ?? '') === 'idempotency_conflict'
    && ($duplicateReadback['data']['evaluation']['revision_number'] ?? 0) === 1,
    'identical duplicate replays exact response, divergent reuse conflicts, and no extra revision is created');

$evaluationStateDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'portal3-checador-demo-v4b-' . (function_exists('posix_geteuid') ? (string) posix_geteuid() : 'current-runtime');
$applicationRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$duplicateStateKey = hash('sha256', $applicationRoot . "\0" . (string) $user->id . "\0evaluation_duplicate");
$duplicateStatePath = $evaluationStateDirectory . DIRECTORY_SEPARATOR . $duplicateStateKey . '.json';
$state = is_file($duplicateStatePath) ? json_decode((string) file_get_contents($duplicateStatePath), true) : null;
$evaluationIdentity = hash('sha256', $tuple[0] . "\0" . $tuple[1] . "\0" . $tuple[2]);
$assert(is_array($state)
    && count($state['evaluations'][$evaluationIdentity]['history'] ?? []) === 1
    && (($state['evaluations'][$evaluationIdentity]['evaluation']['administrative_status'] ?? '') === 'REVIEW_REQUIRED'),
    'private temporary demo state preserves the synthetic evaluation history across POST/readback');

$faltaService = $makeService('evaluation_falta_authorized');
$faltaWithoutHumanConfirmation = $faltaService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-falta-no-confirm', 900001, false);
$falta = $faltaService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-falta-confirmed', 900001, true);
$faltaReadback = $faltaService->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
$assert(($faltaWithoutHumanConfirmation['error']['kind'] ?? '') === 'api_authorization'
    && ($faltaWithoutHumanConfirmation['ok'] ?? false) === false
    && ($falta['data']['administrative_status'] ?? '') === 'FALTA'
    && ($falta['data']['human_review_status'] ?? '') === 'NOT_REQUIRED'
    && ($faltaReadback['data']['evaluation']['administrative_status'] ?? '') === 'FALTA',
    'FALTA is available only in its explicit synthetic authorization scenario');
$assert(($faltaReadback['data']['evaluation']['administrative_status'] ?? '') === 'FALTA', 'explicit synthetic FALTA response survives canonical readback');

$unavailableService = $makeService('evaluation_service_unavailable');
$unavailable = $unavailableService->executeAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2], 'v4c-unavailable', 900001);
$unavailableReadback = $unavailableService->readAdministrativeEvaluation($user, $tuple[0], $tuple[1], $tuple[2]);
$assert(($unavailable['error']['kind'] ?? '') === 'api_unavailable'
    && ($unavailable['error']['status'] ?? 0) === 503
    && ($unavailableReadback['error']['kind'] ?? '') === 'api_unavailable',
    'service unavailability remains an explicit 503 failure for command and readback');

$wrongTuple = $makeService('evaluation_pending_review')->executeAdministrativeEvaluation($user, 900001, 1, '2026-10-19', 'v4c-wrong-locality', 900001);
$assert(($wrongTuple['error']['kind'] ?? '') === 'api_authorization', 'station/locality identifiers are not equated; only canonical demo locality 14 is accepted');

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$routeSource = file_get_contents(__DIR__ . '/../routes/web.php');
$evaluationGuardPosition = strpos($controllerSource, 'ChecadorAccessService::authorizeEvaluation($workplaceId)');
$faltaGuardPosition = strpos($controllerSource, 'ChecadorAccessService::authorizeFalta($workplaceId)');
$commandPosition = strpos($controllerSource, '$reader->executeAdministrativeEvaluation(');
$assert($evaluationGuardPosition !== false && $faltaGuardPosition !== false
    && $commandPosition !== false && $evaluationGuardPosition < $commandPosition && $faltaGuardPosition < $commandPosition
    && str_contains($controllerSource, "'confirm_evaluation'")
    && str_contains($controllerSource, "!== '1'")
    && str_contains($controllerSource, 'authorizeFalta ?'),
    'real Portal3 action ACLs and explicit human confirmation remain ahead of the command and separate by action');
$assert(str_contains($viewSource, 'name="_csrf_token"')
    && str_contains($viewSource, 'name="confirm_evaluation" value="1" required')
    && str_contains($viewSource, "!empty(\$evaluationPermissions['evaluate'])")
    && str_contains($viewSource, "!empty(\$evaluationPermissions['authorize_falta'])")
    && str_contains($viewSource, 'Demostración sintética: la respuesta está predefinida'),
    'demo forms retain CSRF, separate real ACL gates, confirmation and Spanish synthetic disclosure');
$assert(str_contains($routeSource, "'/departamento-operativo/recursos-humanos/checador/evaluations/evaluate'")
    && str_contains($serviceSource, "'api/v2/administrative-evaluations/evaluate'")
    && !str_contains($serviceSource, 'evaluateCalculation'),
    'the existing P3-G-D route is reused without introducing a local evaluation engine');
$assert($networkCalls === 0 && $tokenCalls === 0, 'synthetic P3-G-D calls generate no JWT and make no outbound HTTP requests');

foreach ($scenarios as $scenario) {
    $stateKey = hash('sha256', $applicationRoot . "\0" . (string) $user->id . "\0" . $scenario);
    foreach ([$stateKey . '.json', $stateKey . '.lock'] as $filename) {
        $path = $evaluationStateDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }
}
foreach ($environmentKeys as $key) {
    if ($originalEnvironment[$key] === null) {
        unset($_ENV[$key]);
    } else {
        $_ENV[$key] = $originalEnvironment[$key];
    }
}

printf("CHECADOR_DEMO_ADMIN_EVALUATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
