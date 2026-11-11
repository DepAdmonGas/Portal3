<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$renderDashboard = static function (?int $workplaceId, string $date, ?int $workerId, bool $canReadScheduling = true): array {
    $selectedWorkplaceId = $workplaceId;
    $selectedDate = $date;
    $selectedWorkerId = $workerId;
    $workplaces = $workplaceId === null ? [] : [[
        'id' => $workplaceId,
        'nombre' => $workplaceId === 9 ? 'Departamento de prueba' : 'Centro de prueba',
    ]];
    $state = 'idle';
    $stateMessage = '';
    $demoMode = false;
    $base = '/departamento-operativo/recursos-humanos/checador';
    $moduleNavigation = [
        ['label' => 'Asistencia del día', 'href' => $base . '/asistencia', 'available' => true],
        [
            'label' => 'Programación semanal',
            'description' => $canReadScheduling ? 'Consulta y organiza los turnos.' : 'No disponible.',
            'utility' => $canReadScheduling ? 'Turnos · Descansos · Asignaciones' : '',
            'href' => $base . '/programacion-semanal',
            'available' => $canReadScheduling,
            'unavailable' => 'Disponible con autorización de lectura de programación.',
        ],
        ['label' => 'Horarios y turnos', 'href' => $base . '/horarios-turnos', 'available' => true],
        ['label' => 'Reportes', 'href' => $base . '/reporte', 'available' => true],
    ];
    $buildQueryString = static function (array $params): string {
        return http_build_query(array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== ''), '', '&', PHP_QUERY_RFC3986);
    };
    $selectedWorkplaceKind = $workplaceId === 9 ? 'Departamento' : 'Estación';
    $authorizedProfileLabel = 'Perfil de prueba';
    $realApiPostOperationsEnabled = false;
    $payrollComparisonAvailable = false;
    $temporaryAccessPanel = ['visible' => false, 'requests' => [], 'can_edit' => false];
    $events = [];
    $attendanceRows = [];
    $attendanceCoverage = ['complete' => false, 'message' => 'Sin consulta de prueba.'];
    $attendanceRoster = ['coverage' => 'unknown', 'status' => 'not_available', 'message' => 'Sin padrón de prueba.'];
    $lifecycleFeedback = null;
    $evaluationFeedback = null;

    ob_start();
    try {
        include dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
        $html = (string) ob_get_clean();
    } catch (Throwable $exception) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        throw $exception;
    }

    return [$html, $buildQueryString ?? null];
};

$renderError = null;
try {
    [$stationHtml, $buildQueryString] = $renderDashboard(1, '2026-10-19', 558);
    [$departmentHtml] = $renderDashboard(9, '2026-10-19', 558);
    [$zeroHtml] = $renderDashboard(0, '2026-10-19', 0);
    [$restrictedHtml] = $renderDashboard(1, '2026-10-19', 558, false);
} catch (Throwable $exception) {
    $stationHtml = $departmentHtml = $zeroHtml = '';
    $buildQueryString = null;
    $renderError = $exception::class . ': ' . $exception->getMessage();
}

$assert($renderError === null
    && str_contains($stationHtml, 'Programación semanal')
    && str_contains($stationHtml, 'Consulta y organiza los turnos.'),
    'dashboard renders the authorized weekly directory link without a runtime TypeError');

$readModuleAnchor = static function (string $html, string $label): ?array {
    if (preg_match_all('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER) === false) {
        return null;
    }
    foreach ($matches as $match) {
        $anchorText = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (str_contains($anchorText, $label)) {
            return [
                'raw_href' => $match[1],
                'href' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }
    }
    return null;
};

$stationAnchor = $readModuleAnchor($stationHtml, 'Programación semanal');
$stationHref = $stationAnchor['href'] ?? null;
$stationQuery = [];
if ($stationHref !== null) {
    parse_str((string) parse_url($stationHref, PHP_URL_QUERY), $stationQuery);
}
$assert(parse_url((string) ($stationAnchor['href'] ?? ''), PHP_URL_PATH)
        === '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    && $stationQuery === [],
    'weekly navigation uses the canonical path and keeps locality in the authorized session context');
$assert(is_array($stationAnchor)
    && !str_contains($stationAnchor['raw_href'], 'schedule_week')
    && !str_contains($stationAnchor['raw_href'], 'workplace_id'),
    'weekly navigation does not expose a screen selector or untrusted locality query parameter');

$departmentAnchor = $readModuleAnchor($departmentHtml, 'Programación semanal');
$departmentHref = $departmentAnchor['href'] ?? null;
$departmentQuery = [];
if ($departmentHref !== null) {
    parse_str((string) parse_url($departmentHref, PHP_URL_QUERY), $departmentQuery);
}
$assert(parse_url((string) ($departmentAnchor['href'] ?? ''), PHP_URL_PATH)
        === '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    && $departmentQuery === [],
    'department scheduling navigation also relies on the validated institutional session context');

$zeroAnchor = $readModuleAnchor($zeroHtml, 'Programación semanal');
$zeroHref = $zeroAnchor['href'] ?? null;
$zeroQuery = [];
if ($zeroHref !== null) {
    parse_str((string) parse_url($zeroHref, PHP_URL_QUERY), $zeroQuery);
}
$assert($zeroQuery === []
    && is_callable($buildQueryString)
    && $buildQueryString(['integer_zero' => 0, 'string_zero' => '0', 'empty' => '', 'missing' => null]) === 'integer_zero=0&string_zero=0',
    'integer and string zero survive filtering while null and empty-string values are omitted');

$historyAnchor = $readModuleAnchor($stationHtml, 'Horarios y turnos');
$historyQuery = [];
if ($historyAnchor !== null) {
    parse_str((string) parse_url($historyAnchor['href'], PHP_URL_QUERY), $historyQuery);
}
$assert(parse_url((string) ($historyAnchor['href'] ?? ''), PHP_URL_PATH) === '/departamento-operativo/recursos-humanos/checador/horarios-turnos'
    && $historyQuery === []
    && !str_contains((string) ($historyAnchor['href'] ?? ''), 'schedule_history')
    && !str_contains((string) ($historyAnchor['href'] ?? ''), 'workplace_id'),
    'authorized schedule-history navigation uses the clean institutional route without technical screen or locality parameters');

$reportAnchor = $readModuleAnchor($stationHtml, 'Reportes');
$reportQuery = [];
if ($reportAnchor !== null) {
    parse_str((string) parse_url($reportAnchor['href'], PHP_URL_QUERY), $reportQuery);
}
$assert(parse_url((string) ($reportAnchor['href'] ?? ''), PHP_URL_PATH)
    === '/departamento-operativo/recursos-humanos/checador/reporte'
    && $reportQuery === []
    && is_callable($buildQueryString)
    && $buildQueryString(['period' => 'custom', 'page' => 0, 'order' => '0', 'worker_id' => 558, 'date' => '2026-10-19']) === 'period=custom&page=0&order=0&worker_id=558&date=2026-10-19',
    'report navigation remains on its workspace route and generic query building keeps pagination, order, worker, date and zero values');

$routes = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';
$controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$scheduleWeekView = file_get_contents(dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$assert(str_contains($routes, "'/recursos-humanos/checador/programacion-semanal'")
    && str_contains($controller, 'public function scheduleWeek(): void')
    && str_contains($controller, "Request::method() === 'GET'")
    && str_contains($controller, 'scheduleWeekHref($query)'),
    'canonical M03 route exists and the old screen flag is handled only as a GET redirect');
$assert(str_contains($controller, "'/departamento-operativo/recursos-humanos/checador/programacion-semanal'")
    && str_contains($controller, "ChecadorAccessService::authorizeScheduling(null, 'leer')")
    && str_contains($controller, 'scheduleWeekQueryParameters')
    && !str_contains($scheduleWeekView, 'name="schedule_week"')
    && !str_contains($scheduleWeekView, 'action="/departamento-operativo/recursos-humanos/checador"'),
    'week filter and save-return links use the canonical route while authorization remains in the shared renderer');

$assert(!str_contains($restrictedHtml, 'schedule_week=1')
    && !str_contains(strip_tags($restrictedHtml), 'Turnos de lunes a domingo'),
    'weekly navigation is withheld when independent schedule-read authorization is absent');

$assert(str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'ChecadorAccessService::authorize')
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '', 'authorize(?int $requestedLocalityId'),
    'server-side institutional workplace authorization remains in the render path');

printf("CHECADOR_HTTP_BUILD_QUERY_RUNTIME: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
