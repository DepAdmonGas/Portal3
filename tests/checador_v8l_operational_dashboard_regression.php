<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorOperationalPeriod;
use GuzzleHttp\Client;

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

$keys = [
    'APP_ENV',
    'CHECADOR_ADVANCED_DEMO_ENABLED',
    'CHECADOR_ADVANCED_DEMO_SCENARIO',
    'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE',
];
$original = [];
foreach ($keys as $key) {
    $original[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
}

try {
    $nowUtc = new DateTimeImmutable('2026-10-23 05:30:00', new DateTimeZone('UTC'));
    $today = ChecadorOperationalPeriod::reportRange('today', $nowUtc);
    $last7 = ChecadorOperationalPeriod::reportRange('last7', $nowUtc);
    $month = ChecadorOperationalPeriod::reportRange('month', $nowUtc);
    $yearSegment = ChecadorOperationalPeriod::reportRange('year', $nowUtc, 2026, 10);
    $futureSegment = ChecadorOperationalPeriod::reportRange('year', $nowUtc, 2026, 11);

    $assert(($today['from_date'] ?? '') === '2026-10-23' && ($today['to_date'] ?? '') === '2026-10-23', 'today preset is deterministic and UTC-labelled');
    $assert(($last7['from_date'] ?? '') === '2026-10-17' && ($last7['to_date'] ?? '') === '2026-10-23', 'last-seven-days preset is bounded and inclusive');
    $assert(($month['from_date'] ?? '') === '2026-10-01' && ($month['to_date'] ?? '') === '2026-10-23', 'current-month preset starts at month boundary and clips at today');
    $assert(($yearSegment['from_date'] ?? '') === '2026-10-01' && ($yearSegment['to_date'] ?? '') === '2026-10-23', 'year-to-date resolves only the selected monthly segment');
    $assert($futureSegment === null && ChecadorOperationalPeriod::reportRange('unsupported', $nowUtc) === null, 'future and unsupported segments fail closed');
    $assert(ChecadorOperationalPeriod::isIanaTimezone('America/Mexico_City')
        && !ChecadorOperationalPeriod::isIanaTimezone('CST'), 'workplace-local conversion accepts IANA zones, not ambiguous abbreviations');

    $_ENV['APP_ENV'] = 'dev';
    $_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'true';
    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = 'ready';
    unset($_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE']);
    $assert(ChecadorDailyReadService::demoReferenceDate(
        new DateTimeImmutable('2026-10-23 12:00:00', new DateTimeZone('America/Mexico_City'))
    ) === '2026-10-23', 'demo default date follows an injectable local calendar clock');
    $_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] = '2026-10-23';
    $assert(ChecadorDailyReadService::demoReferenceDate() === '2026-10-23', 'development demo reference date is explicitly controllable');

    $networkCalls = 0;
    $client = new Client([
        'base_uri' => 'https://checador-demo-test.invalid/',
        'http_errors' => false,
        'handler' => static function () use (&$networkCalls): never {
            $networkCalls++;
            throw new RuntimeException('Demo made an outbound request.');
        },
    ]);
    $user = new Usuario();
    $user->id = 990023;
    $service = new ChecadorDailyReadService($client, static fn(Usuario $identity): string => 'unused-demo-token');
    $timezone = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-23');
    $snapshot = ($timezone['ok'] ?? false)
        ? $service->createDailyPunchSnapshot($user, 14, $timezone['data'], 'v8l-controlled-date-snapshot')
        : ['ok' => false];
    $page = ($snapshot['ok'] ?? false)
        ? $service->readDailyPunchSnapshotPage($user, (string) $snapshot['data']['snapshot_token'])
        : ['ok' => false, 'events' => []];
    $assert(($timezone['data']['local_date'] ?? '') === '2026-10-23'
        && ($timezone['data']['from_utc'] ?? '') === '2026-10-23T06:00:00Z'
        && ($page['ok'] ?? false) === true
        && count($page['events'] ?? []) === 3, 'controlled demo date drives timezone, snapshot and the existing ready event fixture');

    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = 'incidence_active';
    $incidenceService = new ChecadorDailyReadService($client);
    $incidences = $incidenceService->readApplicableIncidences($user, 900001, 14, '2026-10-23', '2026-10-23');
    $uuid = (string) ($incidences['data']['incidences'][0]['uuid_incidencia'] ?? '');
    $detail = $uuid !== '' ? $incidenceService->readIncidence($user, $uuid) : ['ok' => false];
    $assert(($incidences['ok'] ?? false) === true
        && $uuid === 'DEMO-INCIDENCE-ACTIVE-20261023'
        && ($detail['data']['date_range']['start_date'] ?? '') === '2026-10-23', 'incidence scenario and canonical readback follow the controlled demo work date');
    $assert($networkCalls === 0, 'controlled demo fixtures remain isolated from real API calls');

    $controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
    $reportView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php') ?: '';
    $dashboardView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php') ?: '';
    $assert(str_contains($controller, 'ChecadorDailyReadService::demoReferenceDate()')
        && str_contains($controller, "? 'month' : 'today'")
        && str_contains($reportView, "'year' => 'Año en curso · mensual'")
        && str_contains($reportView, 'Registros originales · consulta de solo lectura')
        && str_contains($reportView, 'name="export" value="csv"'), 'controller defaults and report periods preserve role defaults, source boundary and CSV action');
    $dailyAttendanceView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
    $controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
    $assert(str_contains($controller, '$this->renderModuleDirectory();')
        && str_contains($controller, "renderDashboardWorkspace('daily-attendance')")
        && str_contains($dailyAttendanceView, '<table class="table table-striped table-bordered text-nowrap align-middle mb-0"')
        && str_contains($dailyAttendanceView, 'Asistencia del día')
        && str_contains($dailyAttendanceView, '>Demo</span>'), 'module directory and separate operational daily workspace remain available with a discreet demo marker');
    $moduleLabels = [
        'Asistencia del día', 'Programación semanal', 'Horarios y turnos', 'Jornadas laborales', 'Incidencias',
        'Ajustes administrativos', 'PIN temporal', 'Reportes', 'Comparación con Nómina', 'Configuración',
    ];
    $moduleDirectoryStart = strpos($controller, '$entries = [', strpos($controller, 'private function buildModuleNavigation'));
    $moduleDirectoryEnd = $moduleDirectoryStart === false ? false : strpos($controller, '];', $moduleDirectoryStart);
    $moduleDirectory = $moduleDirectoryStart !== false && $moduleDirectoryEnd !== false
        ? substr($controller, $moduleDirectoryStart, $moduleDirectoryEnd - $moduleDirectoryStart)
        : '';
    $assert(substr_count($moduleDirectory, "'label' =>") === 10
        && array_reduce($moduleLabels, static fn(bool $allFound, string $label): bool => $allFound && str_contains($moduleDirectory, "'label' => '{$label}'"), true)
        && substr_count($dashboardView, 'aria-label="Módulos de Checador"') === 1,
        'the module directory presents one consistent navigation for ten registered modules');
    $assert(str_contains($dashboardView, 'row-cols-1 row-cols-md-2 row-cols-xl-3')
        && str_contains($dailyAttendanceView, '<table class="table table-striped table-bordered text-nowrap align-middle mb-0"')
        && str_contains($dailyAttendanceView, 'Entrada 1') && str_contains($dailyAttendanceView, 'Salida 1')
        && str_contains($dailyAttendanceView, 'Entrada 2') && str_contains($dailyAttendanceView, 'Salida 2')
        && !str_contains($dailyAttendanceView, 'Almuerzo') && !str_contains($dailyAttendanceView, 'Comida'),
        'institutional responsive cards and main attendance table keep neutral four-position labels');
    $assert(str_contains($controller, '$scheduleRead =')
        && str_contains($controller, "authorizeScheduling(\$requestedWorkplace, 'leer')")
        && !str_contains($dailyAttendanceView, 'Completitud de la fuente (Nivel 2)')
        && !str_contains($dailyAttendanceView, 'Sin asignación según el resolvedor')
        && !str_contains($dailyAttendanceView, 'Respuesta calculada por API V2'),
        'weekly navigation follows read authorization and user copy avoids internal status terminology');
} finally {
    foreach ($keys as $key) {
        if ($original[$key] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $original[$key];
        }
    }
}

printf("CHECADOR_V8L_OPERATIONAL_DASHBOARD: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
