<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$navigation = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js') ?: '';
$browser = file_get_contents($root . '/tests/e2e/specs/schedule-week-read.spec.mjs') ?: '';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$assert(str_contains($routes, "'/recursos-humanos/checador/programacion-semanal', Route::auth(['ChecadorController', 'scheduleWeek'])")
    && str_contains($routes, "'/recursos-humanos/checador/horarios-turnos', Route::auth(['ChecadorController', 'scheduleHistory'])"),
    'M03 has an authenticated dedicated route and M04 keeps its existing route');
$assert(str_contains($controller, 'public function scheduleWeek(): void')
    && str_contains($controller, '$this->renderDashboardWorkspace(null, false, true)')
    && str_contains($controller, '$scheduleWeekMode = $workspace === null && $dedicatedScheduleWeek')
    && str_contains($controller, "ChecadorAccessService::authorizeScheduling(null, 'leer')"),
    'the canonical route reuses the existing renderer and its scheduling read authorization');
$assert(str_contains($controller, "Request::method() === 'GET'")
    && str_contains($controller, '($query[\'schedule_week\'] ?? \'\') === \'1\'')
    && str_contains($controller, 'self::redirect(self::scheduleWeekHref($query))')
    && str_contains($controller, '($query[\'schedule_history\'] ?? \'\') !== \'1\''),
    'the legacy screen flag redirects only from GET and does not shadow M04 history compatibility');
$assert(str_contains($controller, '\'week_start\' => $weekStart')
    && str_contains($controller, 'ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart')
    && !str_contains($controller, '\'workplace_id\' => $query[\'workplace_id\']'),
    'only a valid Monday filter survives canonicalization; locality remains session-authoritative');
$assert(!str_contains($view, 'name="schedule_week"')
    && substr_count($view, 'action="/departamento-operativo/recursos-humanos/checador/programacion-semanal"') >= 4
    && str_contains($view, 'programacion-semanal/asignar')
    && str_contains($view, 'checador/horarios-turnos')
    && !str_contains($view, 'programacion-semanal/plantillas'),
    'week selection and navigation use the canonical path while existing POST actions remain separate');
$assert(str_contains($navigation, "pathname === rootPath + '/programacion-semanal'")
    && str_contains($navigation, "'schedule_week', 'schedule_history', 'date', 'worker_id', 'page', 'per_page'")
    && str_contains($navigation, "'week_start'") ,
    'the existing station selector preserves only the week filter on the canonical M03 path');
$assert(str_contains($browser, 'checador?schedule_week=1&week_start=${weekStart}')
    && str_contains($browser, "searchParams.get('schedule_week')).toBeNull()")
    && str_contains($browser, 'scheduleCardLink'),
    'authenticated browser coverage checks menu navigation and legacy redirect cleanup');

printf("CHECADOR_M03_CANONICAL_ROUTE: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
