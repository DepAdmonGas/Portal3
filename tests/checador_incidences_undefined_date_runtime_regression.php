<?php

declare(strict_types=1);

namespace App\Controllers {
    class BaseController
    {
        public function __construct() {}
    }
}

namespace App\Models {
    final class Usuario
    {
        public function __construct(public int $id, public int $id_puesto) {}
    }
}

namespace App\Core {
    final class Request
    {
        public static array $queryParams = [];
        public static function query(): array { return self::$queryParams; }
        public static function json(): array { return []; }
        public static function method(): string { return 'GET'; }
        public static function uri(): string { return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'); }
    }

    final class Breadcrumb
    {
        public static array $items = [];
        public static function add(string $label, string $url = ''): void { self::$items[] = [$label, $url]; }
    }

    final class Auth
    {
        public static ?object $currentUser = null;
        public static function user(): ?object { return self::$currentUser; }
    }

    final class Session
    {
        public static function pull(string $key, mixed $default = null): mixed { return $default; }
        public static function get(string $key, mixed $default = null): mixed { return $default; }
        public static function remove(string $key): void {}
    }

    final class CsrfToken
    {
        public static function token(): string { return 'runtime-test-token'; }
    }

    final class Logger
    {
        public static array $warnings = [];
        public static function warning(string $message, array $context = []): void { self::$warnings[] = [$message, $context]; }
    }

    final class View
    {
        public static string $root = '';
        public static string $lastView = '';
        public static string $lastHtml = '';
        public static array $lastData = [];
        public static ?string $partialView = null;

        public static function render(string $view, array $data = [], string $layout = 'main'): void
        {
            self::$lastView = $view;
            self::$lastData = $data;
            self::$partialView = null;
            $viewPath = self::$root . '/app/Views/' . $view . '.php';
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewPath;
            self::$lastHtml = (string) ob_get_clean();
        }

        public static function renderPartial(string $view, array $data = []): void
        {
            self::$partialView = $view;
            self::$lastData = $data;
            self::$lastHtml = '';
        }
    }
}

namespace App\Services {
    final class ChecadorAccessService
    {
        public const MODULE_KEY = 'checador';
        public static function authorize(?int $workplaceId = null): array
        {
            $positionId = (int) (\App\Core\Auth::$currentUser->id_puesto ?? 0);
            $allowed = in_array($positionId, [6, 13], true) && ($workplaceId === null || $workplaceId === 1);
            $workplaces = [['id' => 1, 'nombre' => 'Interlomas']];
            return [
                'allowed' => $allowed,
                'reason' => $allowed ? '' : 'access_denied',
                'position_id' => $positionId,
                'workplaces' => $workplaces,
                'station_workplaces' => $positionId === 6 ? $workplaces : [],
                'department_workplaces' => $positionId === 13 ? $workplaces : [],
            ];
        }
        public static function authorizeIncidenceRead(?int $workplaceId): array
        {
            return ['allowed' => in_array((int) (\App\Core\Auth::$currentUser->id_puesto ?? 0), [6, 13], true) && $workplaceId === 1];
        }
        public static function authorizeIncidenceCreate(?int $workplaceId): array { return ['allowed' => false]; }
        public static function authorizeTemporaryAccess(?int $workplaceId, string $action): array
        {
            return ['allowed' => (int) (\App\Core\Auth::$currentUser->id_puesto ?? 0) === 6 && $workplaceId === 1, 'permissions' => []];
        }
        public static function authorizeLifecycle(?int $workplaceId, string $action): array { return ['allowed' => false]; }
        public static function authorizeEvaluation(?int $workplaceId): array { return ['allowed' => false]; }
        public static function authorizeFalta(?int $workplaceId): array { return ['allowed' => false]; }
        public static function authorizeScheduling(int $workplaceId, string $action): array { return ['allowed' => false]; }
        public static function temporaryAccessRequestBelongsToLocality(?int $stationId, int $workplaceId): bool { return false; }
    }

    final class ModuleStationService
    {
        public static function getContext(string $moduleKey): array { return ['id_estacion' => 1, 'id_depto' => null]; }
        public static function setContext(string $moduleKey, ?int $stationId, ?int $departmentId): void {}
        public static function setChecadorWorkplaceContext(int $workplaceId): bool { return $workplaceId === 1; }
    }

    final class ChecadorDailyReadService
    {
        public static string $incidenceMode = 'disabled';
        public static int $incidenceReadCalls = 0;
        public static function demoModeEnabled(): bool { return false; }
        public static function realApiPostOperationsEnabled(): bool { return false; }
        public static function configuredDemoScenario(): ?string { return null; }
        public static function demoReferenceDate(): string { return '2026-10-28'; }
        public function resolveWorkplaceLocalDay(object $user, int $workplaceId, string $date): array
        {
            if (self::$incidenceMode !== 'disabled' && $date === '2026-10-29') {
                return ['ok' => true, 'data' => [
                    'workplace_id' => $workplaceId,
                    'local_date' => $date,
                    'timezone' => 'America/Mexico_City',
                    'from_utc' => '2026-10-29T06:00:00Z',
                    'to_utc' => '2026-10-30T06:00:00Z',
                ]];
            }
            if ($date === '2026-10-27') {
                return ['ok' => true, 'data' => [
                    'workplace_id' => $workplaceId,
                    'local_date' => $date,
                    'timezone' => 'America/Mexico_City',
                    'from_utc' => '2026-10-27T06:00:00Z',
                    'to_utc' => '2026-10-28T06:00:00Z',
                ]];
            }
            return ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }
        public function readApplicableIncidences(object $user, int $workerId, int $workplaceId, string $startDate, string $endDate): array
        {
            self::$incidenceReadCalls++;
            if (self::$incidenceMode === 'shared_failure') {
                return ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
            }
            if (self::$incidenceMode === 'partial' && $workerId !== 1004) {
                return ['ok' => false, 'error' => ['kind' => 'administrative_unavailable', 'status' => 503]];
            }
            if (self::$incidenceMode === 'partial') {
                return ['ok' => true, 'data' => ['incidences' => [['uuid_incidencia' => 'runtime-valid-incidence']]]];
            }
            return ['ok' => true, 'data' => ['incidences' => []]];
        }
        public function readIncidence(object $user, string $uuidIncidencia): array
        {
            return ['ok' => true, 'data' => [
                'uuid_incidencia' => $uuidIncidencia,
                'worker_id' => 1004,
                'workplace_id' => 1,
                'category' => 'JUSTIFICATION',
                'scope' => 'WORKER_DATE_RANGE',
                'date_range' => ['start_date' => '2026-10-29', 'end_date' => '2026-10-29'],
                'current_status' => 'PENDING_REVIEW',
                'current_revision' => 1,
                'revisions' => [],
            ]];
        }
    }

    final class ChecadorAttendanceRosterService
    {
        public function workersForAttendanceFilter(int $workplaceId, string $date, ?string $timezone = null, bool $demoMode = false): array
        {
            $workers = [
                ['worker_id' => 1004, 'full_name' => 'Alicia Montalvo', 'employee_number' => '990101'],
                ['worker_id' => 1007, 'full_name' => 'Diego Arriaga', 'employee_number' => '990104'],
            ];
            if (in_array(\App\Services\ChecadorDailyReadService::$incidenceMode, ['shared_failure', 'empty'], true)) {
                $workers = [];
                for ($workerId = 1004; $workerId < 1050; $workerId++) {
                    $workers[] = ['worker_id' => $workerId, 'full_name' => 'Persona de prueba ' . $workerId, 'employee_number' => (string) $workerId];
                }
            }
            return $date === '2026-10-27'
                ? ['status' => 'partial', 'coverage' => 'current_roster_historical_unverified', 'workers' => $workers, 'message' => 'La fecha es histórica y el padrón histórico canónico no está completo.']
                : ['status' => 'ready', 'coverage' => 'current_active_roster', 'workers' => $workers, 'message' => ''];
        }
    }

    final class ChecadorOperationalPeriod
    {
        public static function isIanaTimezone(string $timezone): bool
        {
            return in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true);
        }

        public static function currentLocalDate(string $timezone, \DateTimeImmutable $instant): ?string
        {
            try {
                return $instant->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    final class ChecadorPersistentQaFixtureService
    {
        public const WORK_DATE = '2026-10-28';
        public function shouldServe(?string $workspace, ?int $workplaceId, string $date, array $query): bool
        {
            return $workplaceId === 1
                && in_array($workspace, ['daily-attendance', 'journeys', 'incidences', 'adjustments'], true)
                && $date === self::WORK_DATE;
        }
        public function readIncidences(int $workplaceId, string $date, ?int $workerId): array
        {
            $workers = [
                1004 => ['worker_id' => 1004, 'full_name' => 'Alicia Montalvo', 'employee_number' => '990101'],
                1007 => ['worker_id' => 1007, 'full_name' => 'Diego Arriaga', 'employee_number' => '990104'],
            ];
            $incidences = [];
            if ($workerId === null || $workerId === 1004) {
                $incidences[1004] = [[
                    'uuid_incidencia' => 'de12bfa2-20db-4fee-9ba7-4c0bb8ff9da0',
                    'worker_id' => 1004,
                    'workplace_id' => 1,
                    'category' => 'JUSTIFICATION',
                    'scope' => 'WORKER_DATE_RANGE',
                    'date_range' => ['start_date' => self::WORK_DATE, 'end_date' => self::WORK_DATE],
                    'current_status' => 'PENDING_REVIEW',
                    'current_revision' => 1,
                    'summary_reason' => 'Registro controlado de prueba runtime.',
                    'revisions' => [[
                        'revision_number' => 1,
                        'status' => 'PENDING_REVIEW',
                        'reason' => 'Registro controlado de prueba runtime.',
                        'evidence_reference' => null,
                        'created_by_user_id' => 33,
                        'approved_by_user_id' => null,
                        'created_at_utc' => '2026-10-28T12:26:31Z',
                    ]],
                ]];
            } elseif ($workerId !== null) {
                $incidences[$workerId] = [];
            }
            return [
                'ok' => true,
                'timezone' => ['timezone' => 'America/Mexico_City'],
                'attendance_roster' => ['status' => 'ready', 'workers' => array_values($workers)],
                'worker_options' => array_values($workers),
                'worker_display_names' => array_column($workers, 'full_name', 'worker_id'),
                'worker_options_status' => 'ready',
                'worker_options_message' => '',
                'selected_worker_id' => $workerId,
                'administrative_incidences' => $incidences,
                'administrative_errors' => [],
            ];
        }
        public function read(int $workplaceId, string $date, ?int $workerId = null): array
        {
            $read = $this->readIncidences($workplaceId, $date, $workerId);
            return array_merge($read, [
                'events' => [], 'attendance_events' => [],
                'attendance_coverage' => ['status' => 'complete', 'complete' => true, 'message' => ''],
                'attendance_rows' => [], 'effective_schedules' => [], 'effective_schedule_errors' => [],
                'journeys' => [], 'journey_errors' => [], 'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
            ]);
        }
    }

    final class ChecadorPersistentQaIncidenceStore
    {
        public function enabled(): bool { return false; }
    }

    final class ChecadorPayrollComparisonReadService
    {
        public function authorization(?int $workplaceId): array { return ['allowed' => false]; }
    }

    final class ChecadorPresentationFormatter
    {
        public static function incidenceStatusLabel(mixed $status): string { return 'Pendiente de revisión'; }
    }

    final class ChecadorWeeklyScheduleService
    {
        public static function monday(string $date): ?string { return '2026-10-26'; }
    }
}

namespace {
    // Keep controller status-code changes working throughout the suite. The
    // harness prints assertions only after all routed requests have completed.
    ob_start();

    $root = dirname(__DIR__);
    require_once $root . '/vendor/autoload.php';
    \App\Core\View::$root = $root;
    require_once $root . '/app/Controllers/ChecadorController.php';

    $passes = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$passes, &$failures): void {
        echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
        $condition ? $passes++ : $failures++;
    };

    $router = \FastRoute\simpleDispatcher(require $root . '/routes/web.php');
    $routePaths = [
        'incidences' => '/departamento-operativo/recursos-humanos/checador/incidencias',
        'daily-attendance' => '/departamento-operativo/recursos-humanos/checador/asistencia',
        'journeys' => '/departamento-operativo/recursos-humanos/checador/jornadas',
        'adjustments' => '/departamento-operativo/recursos-humanos/checador/ajustes',
        'temporary-access' => '/departamento-operativo/recursos-humanos/checador/acceso-temporal',
    ];
    $controllerMethods = [
        'incidences' => 'incidences', 'daily-attendance' => 'dailyAttendance',
        'journeys' => 'journeys', 'adjustments' => 'adjustments', 'temporary-access' => 'temporaryAccess',
    ];

    $runtimeNow = new \DateTimeImmutable('2026-10-29T12:00:00Z');

    $invokeGet = static function (string $workspace, int $userId, int $positionId, array $query) use ($router, $routePaths, $controllerMethods, $runtimeNow): array {
        \App\Core\Request::$queryParams = $query;
        \App\Core\Auth::$currentUser = new \App\Models\Usuario($userId, $positionId);
        \App\Core\Breadcrumb::$items = [];
        \App\Core\View::$lastView = '';
        \App\Core\View::$lastHtml = '';
        \App\Core\View::$lastData = [];
        \App\Core\View::$partialView = null;
        $_SERVER['REQUEST_URI'] = $routePaths[$workspace] . ($query === [] ? '' : '?' . http_build_query($query));
        $_GET = $query;
        http_response_code(200);
        $dispatch = $router->dispatch('GET', $routePaths[$workspace]);
        $runtimeError = null;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
        try {
            if (($dispatch[0] ?? null) === \FastRoute\Dispatcher::FOUND) {
                (new \App\Controllers\ChecadorController(static fn(): \DateTimeImmutable => $runtimeNow))
                    ->{$controllerMethods[$workspace]}();
            }
        } catch (\Throwable $error) {
            $runtimeError = $error;
        } finally {
            restore_error_handler();
        }
        return [
            'dispatch' => $dispatch,
            'error' => $runtimeError,
            'status' => http_response_code(),
            'view' => \App\Core\View::$lastView,
            'html' => \App\Core\View::$lastHtml,
            'data' => \App\Core\View::$lastData,
            'partial' => \App\Core\View::$partialView,
            'breadcrumbs' => \App\Core\Breadcrumb::$items,
        ];
    };

    $defaultIncidences = $invokeGet('incidences', 33, 6, []);
    $assert(($defaultIncidences['dispatch'][0] ?? null) === \FastRoute\Dispatcher::FOUND
        && ($defaultIncidences['error'] ?? null) === null
        && $defaultIncidences['view'] === 'departamento-operativo/2-recursos-humanos/checador/workspace-incidences'
        && ($defaultIncidences['data']['selectedDate'] ?? '') === '2026-10-29'
        && ($defaultIncidences['data']['state'] ?? '') === 'error'
        && count($defaultIncidences['data']['workerSelectOptions'] ?? []) === 2
        && str_contains($defaultIncidences['html'], 'Alicia Montalvo'),
        'GET /incidencias without date uses the injected current date, retains local roster options, and reports API failure');

    \App\Services\ChecadorDailyReadService::$incidenceMode = 'shared_failure';
    \App\Services\ChecadorDailyReadService::$incidenceReadCalls = 0;
    \App\Core\Logger::$warnings = [];
    $sharedFailure = $invokeGet('incidences', 33, 6, ['date' => '2026-10-29']);
    $assert(($sharedFailure['data']['state'] ?? '') === 'error'
        && count($sharedFailure['data']['attendanceRoster']['workers'] ?? []) === 46
        && \App\Services\ChecadorDailyReadService::$incidenceReadCalls === 1
        && substr_count($sharedFailure['html'], 'No fue posible completar la consulta de incidencias') === 1
        && !str_contains($sharedFailure['html'], 'no está disponible. Una respuesta fallida')
        && count(\App\Core\Logger::$warnings) === 1
        && !array_key_exists(1004, \App\Core\Logger::$warnings[0][1] ?? []),
        '46 workers sharing an API failure stop after one read, render one generic notice, and log aggregate diagnostics only');

    \App\Services\ChecadorDailyReadService::$incidenceMode = 'empty';
    \App\Services\ChecadorDailyReadService::$incidenceReadCalls = 0;
    \App\Core\Logger::$warnings = [];
    $confirmedEmpty = $invokeGet('incidences', 33, 6, ['date' => '2026-10-29']);
    $assert(($confirmedEmpty['data']['state'] ?? '') === 'success'
        && count($confirmedEmpty['data']['attendanceRoster']['workers'] ?? []) === 46
        && \App\Services\ChecadorDailyReadService::$incidenceReadCalls === 46
        && substr_count($confirmedEmpty['html'], 'No hay incidencias registradas para esta búsqueda') === 1
        && !str_contains($confirmedEmpty['html'], 'La consulta no devolvió incidencias para este trabajador')
        && count(\App\Core\Logger::$warnings) === 0,
        'successful empty incidence reads render exactly one confirmed-empty message');

    \App\Services\ChecadorDailyReadService::$incidenceMode = 'partial';
    \App\Services\ChecadorDailyReadService::$incidenceReadCalls = 0;
    \App\Core\Logger::$warnings = [];
    $partialRead = $invokeGet('incidences', 33, 6, ['date' => '2026-10-29']);
    $assert(($partialRead['data']['state'] ?? '') === 'partial'
        && str_contains($partialRead['html'], 'runtime-valid-incidence')
        && substr_count($partialRead['html'], 'Algunos registros no pudieron consultarse') === 1
        && !str_contains($partialRead['html'], 'No hay incidencias registradas para esta búsqueda')
        && !str_contains($partialRead['html'], 'La consulta no devolvió incidencias para este trabajador'),
        'partial incidence reads preserve the returned record and render one partial notice without a false empty result');
    \App\Services\ChecadorDailyReadService::$incidenceMode = 'disabled';

    $filteredIncidences = $invokeGet('incidences', 33, 6, ['date' => '2026-10-28', 'worker_id' => '1004']);
    $assert(($filteredIncidences['error'] ?? null) === null
        && ($filteredIncidences['data']['selectedDate'] ?? '') === '2026-10-28'
        && (int) ($filteredIncidences['data']['selectedWorkerId'] ?? 0) === 1004
        && str_contains($filteredIncidences['html'], 'Alicia Montalvo')
        && str_contains($filteredIncidences['html'], 'Registro controlado de prueba runtime.'),
        'GET /incidencias with date and worker filters renders the selected worker and detail');

    $emptyWorkerFilter = $invokeGet('incidences', 33, 6, ['date' => '2026-10-28', 'worker_id' => '']);
    $assert(($emptyWorkerFilter['error'] ?? null) === null
        && ($emptyWorkerFilter['data']['selectedWorkerId'] ?? null) === null
        && count($emptyWorkerFilter['data']['workerSelectOptions'] ?? []) === 2
        && str_contains($emptyWorkerFilter['html'], 'Alicia Montalvo'),
        'GET /incidencias with an empty worker_id treats it as all authorized workers');

    $historicalRoster = $invokeGet('incidences', 33, 6, ['date' => '2026-10-27', 'worker_id' => '']);
    $assert(($historicalRoster['error'] ?? null) === null
        && ($historicalRoster['data']['state'] ?? '') === 'error'
        && ($historicalRoster['data']['workerSelectStatus'] ?? '') === 'partial'
        && count($historicalRoster['data']['workerSelectOptions'] ?? []) === 2
        && str_contains($historicalRoster['html'], 'su pertenencia a la fecha consultada no está verificada'),
        'Incidencias exposes current roster choices as unverified references without using them to authorize historical reads');

    $defaultAttendance = $invokeGet('daily-attendance', 33, 6, []);
    $assert(($defaultAttendance['error'] ?? null) === null
        && ($defaultAttendance['data']['selectedDate'] ?? '') === '2026-10-29'
        && ($defaultAttendance['data']['state'] ?? '') !== 'success'
        && ($defaultAttendance['data']['events'] ?? null) === []
        && ($defaultAttendance['data']['attendanceReadSource'] ?? '') === 'raw_punch_events',
        'GET /asistencia without date uses the injected current date and reports the API failure without serving historical fixture rows');

    $otherWorker = $invokeGet('incidences', 19, 13, ['date' => '2026-10-28', 'worker_id' => '1007']);
    $assert(($otherWorker['error'] ?? null) === null
        && (int) ($otherWorker['data']['selectedWorkerId'] ?? 0) === 1007
        && str_contains($otherWorker['html'], 'Diego Arriaga'),
        'GET /incidencias with another authorized worker reaches the render path');

    $breadcrumb = $filteredIncidences['breadcrumbs'][3] ?? [];
    $assert(($breadcrumb[0] ?? '') === 'Checador'
        && ($breadcrumb[1] ?? '') === '/departamento-operativo/recursos-humanos/checador/asistencia?date=2026-10-28&worker_id=1004',
        'Checador breadcrumb preserves the effective date and worker when returning to attendance');

    $breadcrumbGet = $invokeGet('daily-attendance', 33, 6, ['date' => '2026-10-28', 'worker_id' => '1004']);
    $assert(($breadcrumbGet['dispatch'][0] ?? null) === \FastRoute\Dispatcher::FOUND
        && ($breadcrumbGet['error'] ?? null) === null
        && ($breadcrumbGet['status'] ?? 0) < 500
        && ($breadcrumbGet['view'] ?? '') === 'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
        'GET through the generated Checador breadcrumb reaches and renders attendance with the preserved filters');

    $otherWorkspaceResults = [];
    foreach (['daily-attendance', 'journeys', 'adjustments', 'temporary-access'] as $workspace) {
        $otherWorkspaceResults[$workspace] = $invokeGet($workspace, 33, 6, ['date' => '2026-10-28']);
    }
    $assert(array_reduce($otherWorkspaceResults, static fn(bool $ok, array $result): bool => $ok
        && ($result['dispatch'][0] ?? null) === \FastRoute\Dispatcher::FOUND
        && ($result['error'] ?? null) === null
        && ($result['status'] ?? 0) < 500
        && ($result['view'] ?? '') !== '', true),
        'other GET workspaces sharing renderDashboardWorkspace dispatch and render without runtime errors');

    $unauthorized = $invokeGet('incidences', 900, 99, []);
    $assert(($unauthorized['dispatch'][0] ?? null) === \FastRoute\Dispatcher::FOUND
        && ($unauthorized['error'] ?? null) === null
        && ($unauthorized['status'] ?? 0) === 403
        && ($unauthorized['partial'] ?? null) === 'errors/403',
        'the controller keeps its access-denied path for a principal outside the test ACL policy');

    echo "RUNTIME_HARNESS: controlled dependency stubs; not an authenticated HTTP session.\n";
    printf("CHECADOR_INCIDENCES_UNDEFINED_DATE_RUNTIME: %d PASS / %d FAIL\n", $passes, $failures);
    ob_end_flush();
    exit($failures === 0 ? 0 : 1);
}
