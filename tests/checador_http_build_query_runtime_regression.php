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
    $buildQueryString = static function (array $params): string {
        $filtered = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
        return http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    };
    $moduleNavigation = [[
        'label' => 'Programación semanal',
        'description' => 'Turnos de lunes a domingo.',
        'utility' => 'Turnos · Asignaciones',
        'action' => 'Ver programación',
        'icon' => 'ti-calendar-week',
        'href' => '/departamento-operativo/recursos-humanos/checador/programacion-semanal',
        'available' => $canReadScheduling,
        'unavailable' => 'Disponible con autorización de lectura de programación.',
    ]];
    $isModuleDirectory = true;
    $requiresExplicitWorkplaceSelection = false;
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
$assert(($stationAnchor['href'] ?? '') === '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    && !isset($stationQuery['schedule_week']) && !isset($stationQuery['workplace_id']),
    'weekly navigation uses the clean route and relies on authorized session context');
$assert(str_contains(file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '', "'/recursos-humanos/checador/programacion-semanal'")
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'public function scheduleWeek(): void')
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'self::redirect(self::scheduleWeekHref($query))'),
    'clean GET route is registered and the legacy weekly flag redirects through the week-preserving canonical URL');

$departmentAnchor = $readModuleAnchor($departmentHtml, 'Programación semanal');
$departmentHref = $departmentAnchor['href'] ?? null;
$departmentQuery = [];
if ($departmentHref !== null) {
    parse_str((string) parse_url($departmentHref, PHP_URL_QUERY), $departmentQuery);
}
$assert(($departmentHref ?? '') === '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    && !isset($departmentQuery['workplace_id']),
    'weekly navigation does not publish an untrusted locality ID for department context');

$zeroAnchor = $readModuleAnchor($zeroHtml, 'Programación semanal');
$zeroHref = $zeroAnchor['href'] ?? null;
$zeroQuery = [];
if ($zeroHref !== null) {
    parse_str((string) parse_url($zeroHref, PHP_URL_QUERY), $zeroQuery);
}
$assert(($zeroHref ?? '') === '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    && !isset($zeroQuery['workplace_id'])
    && is_callable($buildQueryString)
    && $buildQueryString(['integer_zero' => 0, 'string_zero' => '0', 'empty' => '', 'missing' => null]) === 'integer_zero=0&string_zero=0',
    'integer and string zero survive filtering while null and empty-string values are omitted');

$assert(!str_contains($restrictedHtml, 'href="/departamento-operativo/recursos-humanos/checador/programacion-semanal"')
    && str_contains($restrictedHtml, 'aria-disabled="true"'),
    'weekly navigation remains unavailable without independent schedule-read authorization');

$assert(str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'ChecadorAccessService::authorize')
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '', 'authorize(?int $requestedLocalityId'),
    'server-side institutional workplace authorization remains in the render path');

printf("CHECADOR_HTTP_BUILD_QUERY_RUNTIME: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
