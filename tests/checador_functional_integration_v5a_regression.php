<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$message}\n";
        $passes++;
    } else {
        echo "FAIL {$message}\n";
        $fails++;
    }
};

$previousEnvironment = [
    'APP_ENV' => $_ENV['APP_ENV'] ?? null,
    'CHECADOR_ADVANCED_DEMO_ENABLED' => $_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] ?? null,
    'CHECADOR_ADVANCED_DEMO_SCENARIO' => $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] ?? null,
    'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE' => $_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] ?? null,
];
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'true';
$_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] = ChecadorDailyReadService::DEMO_REFERENCE_DATE;

$httpRequests = [];
$tokenCalls = 0;
$makeService = static function (string $scenario) use (&$httpRequests, &$tokenCalls): ChecadorDailyReadService {
    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = $scenario;
    $stack = HandlerStack::create(new MockHandler([]));
    $stack->push(Middleware::history($httpRequests));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => 'https://not-used.invalid/', 'http_errors' => false]),
        static function (Usuario $user) use (&$tokenCalls): string {
            $tokenCalls++;
            return 'must-not-be-generated';
        },
        'https://not-used.invalid/'
    );
};

$user = new Usuario();
$user->id = random_int(2100000000, 2199999999);

$service = $makeService('worker_filter_dataset');
$timezone = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-19');
$snapshot = $service->createDailyPunchSnapshot($user, 14, $timezone['data'] ?? [], 'v5a-filter-snapshot');
$snapshotToken = (string) ($snapshot['data']['snapshot_token'] ?? '');
$unfilteredFirst = $service->readDailyPunchSnapshotPage($user, $snapshotToken, null, 1);
$filteredFirst = $service->readDailyPunchSnapshotPage($user, $snapshotToken, null, 1, 900001);
$filteredSecond = $service->readDailyPunchSnapshotPage(
    $user,
    $snapshotToken,
    (string) ($filteredFirst['pagination']['next_cursor'] ?? ''),
    1,
    900001
);
$wrongWorkerCursor = $service->readDailyPunchSnapshotPage(
    $user,
    $snapshotToken,
    (string) ($filteredFirst['pagination']['next_cursor'] ?? ''),
    1,
    900002
);
$assert(($timezone['data']['workplace_id'] ?? 0) === 14
    && ($snapshot['ok'] ?? false) === true
    && ($unfilteredFirst['events'][0]['id_personal_resuelto'] ?? 0) === 900002,
    'synthetic source context retains the canonical locality and proves the target worker is not in the first raw page');
$assert(($filteredFirst['pagination']['matched_records'] ?? 0) === 2
    && ($filteredFirst['events'][0]['id_personal_resuelto'] ?? 0) === 900001
    && ($filteredFirst['events'][0]['event_uuid'] ?? '') === 'DEMO-CHECADOR-V3A-FILTER-TARGET-THIRD'
    && ($filteredFirst['pagination']['has_more'] ?? false) === true
    && ($filteredSecond['events'][0]['event_uuid'] ?? '') === 'DEMO-CHECADOR-V3A-FILTER-TARGET-FIFTH'
    && ($filteredSecond['pagination']['has_more'] ?? true) === false,
    'canonical worker filter applies over the full synthetic dataset before pagination and each page stays worker-pure');
$assert(($wrongWorkerCursor['ok'] ?? true) === false
    && ($wrongWorkerCursor['error']['kind'] ?? '') === 'snapshot_invalid',
    'worker-specific cursor cannot be reused under a different worker filter');

$service = $makeService('incidence_conflict');
$timezone = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-19');
$snapshot = $service->createDailyPunchSnapshot($user, 14, $timezone['data'] ?? [], 'v5a-end-to-end-snapshot');
$page = $service->readDailyPunchSnapshotPage($user, (string) ($snapshot['data']['snapshot_token'] ?? ''));
$schedule = $service->getEffectiveSchedule($user, 900001, 14, '2026-10-19');
$journey = $service->evaluateJourney(
    $user,
    900001,
    14,
    '2026-10-19',
    (string) ($snapshot['data']['snapshot_audit_ref'] ?? '')
);
$incidenceList = $service->readApplicableIncidences($user, 900001, 14, '2026-10-19', '2026-10-19');
$incidenceDetails = [];
foreach (($incidenceList['data']['incidences'] ?? []) as $incidence) {
    $incidenceDetails[] = $service->readIncidence($user, (string) ($incidence['uuid_incidencia'] ?? ''));
}
$evaluation = $service->readAdministrativeEvaluation($user, 900001, 14, '2026-10-19');
$eventWorkers = array_values(array_unique(array_map(
    static fn(array $event): int => (int) ($event['id_personal_resuelto'] ?? 0),
    $page['events'] ?? []
)));
$assert(($page['ok'] ?? false) === true
    && count($page['events'] ?? []) === 2
    && $eventWorkers === [900001]
    && array_column($page['events'], 'id_estacion_resuelta') === [14, 14]
    && ($schedule['data']['id_personal'] ?? 0) === 900001
    && ($schedule['data']['id_estacion'] ?? 0) === 14
    && ($schedule['data']['fecha_local'] ?? '') === '2026-10-19'
    && ($journey['data']['context']['id_personal'] ?? 0) === 900001
    && ($journey['data']['context']['id_estacion'] ?? 0) === 14
    && ($journey['data']['context']['fecha_local'] ?? '') === '2026-10-19',
    'daily punches, schedule and P3-F/P3-H Journey share one worker, canonical locality and local date');
$assert(count($incidenceDetails) === 2
    && array_reduce($incidenceDetails, static fn(bool $same, array $result): bool => $same
        && ($result['data']['worker_id'] ?? 0) === 900001
        && ($result['data']['workplace_id'] ?? 0) === 14, true)
    && ($evaluation['data']['workplace_id'] ?? 0) === 14
    && ($evaluation['data']['work_date'] ?? '') === '2026-10-19'
    && ($evaluation['data']['evaluation']['administrative_status'] ?? '') === 'REVIEW_REQUIRED',
    'P3-G incidence details and evaluation retain the same canonical identity/date context and fixture references');
$assert($httpRequests === [] && $tokenCalls === 0,
    'the complete synthetic journey/incidence read path makes no real API request and generates no JWT');

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$accessSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$dailyViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$reportControllerSource = substr($controllerSource, strpos($controllerSource, 'public function punchReport(): void'));
$assert(str_contains($accessSource, 'mapStationIdsToUniqueLocalities')
    && str_contains($accessSource, "where('numlista',")
    && str_contains($accessSource, 'RhLocalidad::query()->where')
    && str_contains($reportControllerSource, "'source_workplace_id'")
    && str_contains($reportControllerSource, 'sourceWorkplaceCandidate, array_column($workplaces,')
    && str_contains($reportControllerSource, 'requestedWorkplace !== null'),
    'authorized source station is mapped to a canonical locality by unique numlista and source context is revalidated');
$assert(str_contains($dailyViewSource, "'source_work_date'")
    && str_contains($dailyViewSource, "'source_worker_id'")
    && str_contains($dailyViewSource, 'http_build_query')
    && str_contains($dailyViewSource, "'/departamento-operativo/recursos-humanos/checador/reporte'"),
    'daily-to-report navigation carries source locality, local date and resolved worker without converting the date to UTC');

$sourceWorkplaceId = 14;
$sourceWorkDate = '2026-10-19';
$sourceWorkerId = 900001;
$selectedWorkplaceId = 14;
$selectedWorkplaceName = 'Localidad sintética 14';
$fromDate = '';
$toDate = '';
$workerId = '900001';
$sort = 'asc';
$tableSearch = '';
$tableOrderColumn = 1;
$canDownload = true;
$state = 'idle';
$workplaces = [['id' => 14, 'nombre' => 'Localidad sintética 14']];
$links = [];
$scripts = [];
$pagination = null;
$stateMessage = '';
$events = [];
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php';
$reportHtml = (string) ob_get_clean();
$assert(str_contains($reportHtml, 'href="/departamento-operativo/recursos-humanos/checador?workplace_id=14&amp;date=2026-10-19&amp;worker_id=900001"')
    && str_contains($reportHtml, 'name="source_workplace_id" value="14"')
    && str_contains($reportHtml, 'name="source_work_date" value="2026-10-19"')
    && str_contains($reportHtml, 'name="source_worker_id" value="900001"')
    && !str_contains($reportHtml, 'id="from_date" name="from_date" type="date" value="2026-10-19"'),
    'report-to-daily return preserves origin context while keeping its independent UTC date filters empty');

$dailyViewVars = [
    'workplaces' => [['id' => 14, 'nombre' => 'Localidad sintética 14']],
    'selectedWorkplaceId' => 14,
    'selectedDate' => '2026-10-19',
    'selectedWorkerId' => 900001,
    'demoMode' => true,
    'demoScenario' => 'incidence_conflict',
    'state' => 'idle',
    'stateMessage' => '',
    'lifecycleFeedback' => null,
    'evaluationFeedback' => null,
];
extract($dailyViewVars, EXTR_OVERWRITE);
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
$dailyHtml = (string) ob_get_clean();
$assert(str_contains($dailyHtml, 'href="/departamento-operativo/recursos-humanos/checador/reporte?source_workplace_id=14&amp;source_work_date=2026-10-19&amp;source_worker_id=900001"'),
    'daily screen exposes one existing report route with station, worker and local-date origin context');

foreach ($previousEnvironment as $key => $value) {
    if ($value === null) {
        unset($_ENV[$key]);
    } else {
        $_ENV[$key] = $value;
    }
}

printf("CHECADOR_FUNCTIONAL_INTEGRATION_V5A_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
