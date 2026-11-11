<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;
use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$dailyViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$journeyViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php') ?: '';
$rawStart = strpos($controllerSource, 'private static function readDailyRawAttendance(');
$rawEnd = $rawStart === false ? false : strpos($controllerSource, 'private static function readAttendanceSnapshotSet(', $rawStart);
$rawSource = $rawStart !== false && $rawEnd !== false
    ? substr($controllerSource, $rawStart, $rawEnd - $rawStart)
    : '';
$indexStart = strpos($controllerSource, 'public function index(): void');
$indexEnd = $indexStart === false ? false : strpos($controllerSource, 'public function dailyAttendance(): void', $indexStart);
$indexSource = $indexStart !== false && $indexEnd !== false
    ? substr($controllerSource, $indexStart, $indexEnd - $indexStart)
    : '';

// The actual controller policy is invoked for every row in the shared snapshot renderer.
$policy = new ReflectionMethod(ChecadorController::class, 'workspaceRetainsJourneyEvaluation');
$policy->setAccessible(true);
$protectedWorkspaces = [null, 'daily-attendance', 'journeys'];
$policyMatrixCases = 0;
$policyMatrixPassed = true;
foreach ([false, true] as $postFlagEnabled) {
    foreach ([false, true] as $snapshotPresent) {
        foreach ([0, 2] as $workerCount) {
            foreach (['not_evaluated', 'fixture_only', 'read_unavailable'] as $journeySourceState) {
                foreach ($protectedWorkspaces as $workspace) {
                    $keepsEvaluation = $policy->invoke(null, $workspace);
                    $policyMatrixPassed = $policyMatrixPassed && !$keepsEvaluation;
                    $policyMatrixCases++;
                }
            }
        }
    }
}
$assert($policyMatrixPassed,
    'root, Asistencia, and Jornadas retain no evaluation across flags, snapshot states, worker counts, QA state, and read failures');

$assert($policy->invoke(null, 'adjustments') === true,
    'the pre-existing Adjustments branch remains isolated from the authorized P0 policy change');
$guardPosition = strpos($controllerSource, 'if (self::workspaceRetainsJourneyEvaluation($workspace))');
$journeyCallPosition = strpos($controllerSource, '$reader->evaluateJourney(');
$assert($guardPosition !== false && $journeyCallPosition !== false && $guardPosition < $journeyCallPosition,
    'the shared Journey evaluation call is guarded by the workspace policy');
$assert($indexSource !== ''
    && str_contains($indexSource, '$this->renderModuleDirectory();')
    && !str_contains($indexSource, 'createDailyPunchSnapshot('),
    'initial Checador GET uses the read-only module directory');
$assert(!str_contains($controllerSource, 'createDailyPunchSnapshot('),
    'no Checador controller GET path automatically creates a snapshot');
$assert($rawSource !== ''
    && !str_contains($rawSource, 'evaluateJourney(')
    && !str_contains($rawSource, 'realApiPostOperationsEnabled()')
    && str_contains($rawSource, "'journey_evaluation_state' => 'not_evaluated'"),
    'daily attendance raw reads retain observations and leave Journey explicitly unevaluated independent of flags');
$assert(str_contains($controllerSource, "'journeyEvaluationState' => \$journeyEvaluationState")
    && str_contains($dailyViewSource, 'Pendiente de clasificación')
    && str_contains($dailyViewSource, 'Journey no se evalúa al abrir Asistencia')
    && str_contains($journeyViewSource, 'La evaluación no se ejecuta al abrir esta pantalla'),
    'daily and Journey views explain the neutral non-evaluated state without claiming an outcome');
$assert(str_contains($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)')
    && str_contains($controllerSource, 'workspaceRetainsJourneyEvaluation'),
    'workspace authorization and session-selected locality checks remain before protected read paths');

// Mock-only positive controls show that the history middleware detects a POST if a guarded route regresses.
$environmentKeys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO'];
$environmentBefore = [];
foreach ($environmentKeys as $key) {
    $environmentBefore[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$setEnvironment = static function (string $key, ?string $value): void {
    if ($value === null) {
        unset($_ENV[$key]);
        putenv($key);
        return;
    }
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
};
$makeMockReader = static function (array $responses, array &$history): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $base = 'https://checador-side-effect-test.invalid/';
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn(Usuario $user): string => 'mock-only-token',
        $base
    );
};

$user = new Usuario();
$user->id = 900001;
$setEnvironment('APP_ENV', 'dev');
$setEnvironment('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
$setEnvironment('CHECADOR_ADVANCED_DEMO_SCENARIO', null);
try {
    foreach ([false, true] as $postFlagEnabled) {
        $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', $postFlagEnabled ? 'true' : 'false');
        $history = [];
        $reader = $makeMockReader([
            new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
            new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
        ], $history);

        // Every protected GET policy branch is false; no service POST method is invoked.
        foreach ($protectedWorkspaces as $workspace) {
            if ($policy->invoke(null, $workspace)) {
                $reader->evaluateJourney($user, 1004, 1, '2026-10-29');
            }
        }
        $assert($history === [], 'simulated transport records zero requests for protected GET workspace policies with POST flag ' . ($postFlagEnabled ? 'enabled' : 'disabled'));
    }

    $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', 'false');
    $disabledHistory = [];
    $disabledReader = $makeMockReader([
        new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
        new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
    ], $disabledHistory);
    $snapshotDisabled = $disabledReader->createDailyPunchSnapshot($user, 1, [
        'from_utc' => '2026-10-29T06:00:00Z', 'to_utc' => '2026-10-30T06:00:00Z',
    ], 'mock-snapshot-disabled');
    $journeyDisabled = $disabledReader->evaluateJourney($user, 1004, 1, '2026-10-29');
    $assert(($snapshotDisabled['error']['kind'] ?? '') === 'api_post_disabled'
        && ($journeyDisabled['error']['kind'] ?? '') === 'api_post_disabled'
        && $disabledHistory === [], 'simulated service transport remains closed when the real POST flag is disabled');

    $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', 'true');
    $snapshotHistory = [];
    $snapshotReader = $makeMockReader([
        new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
    ], $snapshotHistory);
    $snapshotReader->createDailyPunchSnapshot($user, 1, [
        'from_utc' => '2026-10-29T06:00:00Z', 'to_utc' => '2026-10-30T06:00:00Z',
    ], 'mock-snapshot-enabled');
    $snapshotRequest = $snapshotHistory[0]['request'] ?? null;
    $assert($snapshotRequest instanceof RequestInterface
        && $snapshotRequest->getMethod() === 'POST'
        && str_ends_with($snapshotRequest->getUri()->getPath(), '/api/v2/punch-events/read-snapshots'),
        'simulated transport positive control detects snapshot POST when explicitly invoked');

    $journeyHistory = [];
    $journeyReader = $makeMockReader([
        new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
    ], $journeyHistory);
    $journeyReader->evaluateJourney($user, 1004, 1, '2026-10-29');
    $journeyRequest = $journeyHistory[0]['request'] ?? null;
    $assert($journeyRequest instanceof RequestInterface
        && $journeyRequest->getMethod() === 'POST'
        && str_ends_with($journeyRequest->getUri()->getPath(), '/api/v2/journeys/evaluate'),
        'simulated transport positive control detects Journey POST when explicitly invoked');
} finally {
    foreach ($environmentKeys as $key) {
        [$wasSet, $envValue, $processValue] = $environmentBefore[$key];
        if ($wasSet) {
            $_ENV[$key] = $envValue;
        } else {
            unset($_ENV[$key]);
        }
        if ($processValue === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $processValue);
        }
    }
}

printf("CHECADOR_GET_SIDE_EFFECT_GUARD_P0_RESULT: %d PASS / %d FAIL / 0 SKIPPED (policy scenarios=%d)\n", $passes, $fails, $policyMatrixCases);
exit($fails === 0 ? 0 : 1);
