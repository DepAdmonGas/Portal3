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
    && str_contains($stationHtml, 'Turnos de lunes a domingo'),
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
$assert(($stationQuery['schedule_week'] ?? null) === '1'
    && ($stationQuery['workplace_id'] ?? null) === '1'
    && ($stationQuery['week_start'] ?? null) === '2026-10-19',
    'station, weekly flag and Monday derived from the selected labor date are present in the generated URL');
$assert(is_array($stationAnchor)
    && str_contains($stationAnchor['raw_href'], '&amp;')
    && !preg_match('/(?<!&)&(?!amp;)/', $stationAnchor['raw_href']),
    'query separators are HTML-escaped inside the navigation href');

$departmentAnchor = $readModuleAnchor($departmentHtml, 'Programación semanal');
$departmentHref = $departmentAnchor['href'] ?? null;
$departmentQuery = [];
if ($departmentHref !== null) {
    parse_str((string) parse_url($departmentHref, PHP_URL_QUERY), $departmentQuery);
}
$assert(($departmentQuery['workplace_id'] ?? null) === '9',
    'canonical department workplace context remains in the generated URL');

$zeroAnchor = $readModuleAnchor($zeroHtml, 'Programación semanal');
$zeroHref = $zeroAnchor['href'] ?? null;
$zeroQuery = [];
if ($zeroHref !== null) {
    parse_str((string) parse_url($zeroHref, PHP_URL_QUERY), $zeroQuery);
}
$assert(($zeroQuery['workplace_id'] ?? null) === '0'
    && is_callable($buildQueryString)
    && $buildQueryString(['integer_zero' => 0, 'string_zero' => '0', 'empty' => '', 'missing' => null]) === 'integer_zero=0&string_zero=0',
    'integer and string zero survive filtering while null and empty-string values are omitted');

$historyAnchor = $readModuleAnchor($stationHtml, 'Horarios y turnos');
$historyQuery = [];
if ($historyAnchor !== null) {
    parse_str((string) parse_url($historyAnchor['href'], PHP_URL_QUERY), $historyQuery);
}
$assert(($historyQuery['schedule_history'] ?? null) === '1'
    && ($historyQuery['workplace_id'] ?? null) === '1'
    && ($historyQuery['date'] ?? null) === '2026-10-19'
    && ($historyQuery['worker_id'] ?? null) === '558',
    'authorized schedule-history navigation retains station, worker and labor-date context');

$reportAnchor = $readModuleAnchor($stationHtml, 'Reportes');
$reportQuery = [];
if ($reportAnchor !== null) {
    parse_str((string) parse_url($reportAnchor['href'], PHP_URL_QUERY), $reportQuery);
}
$assert(($reportQuery['source_workplace_id'] ?? null) === '1'
    && ($reportQuery['source_work_date'] ?? null) === '2026-10-19'
    && ($reportQuery['source_worker_id'] ?? null) === '558'
    && is_callable($buildQueryString)
    && $buildQueryString(['period' => 'custom', 'page' => 0, 'order' => '0', 'worker_id' => 558, 'date' => '2026-10-19']) === 'period=custom&page=0&order=0&worker_id=558&date=2026-10-19',
    'report navigation retains source context and generic query building keeps pagination, order, worker, date and zero values');

$assert(!str_contains($restrictedHtml, 'schedule_week=1')
    && !str_contains(strip_tags($restrictedHtml), 'Turnos de lunes a domingo'),
    'weekly navigation is withheld when independent schedule-read authorization is absent');

$assert(str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'ChecadorAccessService::authorize')
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '', 'authorize(?int $requestedLocalityId'),
    'server-side institutional workplace authorization remains in the render path');

printf("CHECADOR_HTTP_BUILD_QUERY_RUNTIME: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
