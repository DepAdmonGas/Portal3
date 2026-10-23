<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$base = dirname(__DIR__);
$viewDir = $base . '/app/Views/departamento-operativo/2-recursos-humanos/checador/';
$controller = file_get_contents($base . '/app/Controllers/ChecadorController.php') ?: '';
$indexStart = strpos($controller, 'public function index(): void');
$indexEnd = $indexStart === false ? false : strpos($controller, 'public function dailyAttendance(): void', $indexStart);
$indexAction = $indexStart !== false && $indexEnd !== false ? substr($controller, $indexStart, $indexEnd - $indexStart) : '';
$routes = file_get_contents($base . '/routes/web.php') ?: '';
$layout = file_get_contents($base . '/app/Views/layouts/departamento-operativo.php') ?: '';
$themeCss = file_get_contents($base . '/public/assets/css/styles.css') ?: '';
$mainView = file_get_contents($viewDir . 'index.php') ?: '';
$dailyView = file_get_contents($viewDir . 'daily-attendance.php') ?: '';
$render = static function (string $view, array $vars) use ($viewDir): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require $viewDir . $view;
    return (string) ob_get_clean();
};

$cards = [
    ['Asistencia del día', 'Consulta las checadas, horarios y estado de asistencia del personal de tu estación.', 'Personal · Marcaciones · Incidencias', 'Ver asistencia'],
    ['Programación semanal', 'Consulta y organiza los turnos del personal de lunes a domingo.', 'Turnos · Descansos · Asignaciones', 'Ver programación'],
    ['Horarios y turnos', 'Revisa los horarios disponibles, las asignaciones vigentes y su historial.', 'Horarios · Vigencias · Historial', 'Consultar horarios'],
    ['Jornadas laborales', 'Consulta jornadas evaluadas, tiempos registrados y situaciones que requieren revisión.', 'Jornadas · Horas · Seguimiento', 'Consultar jornadas'],
    ['Incidencias', 'Revisa las incidencias del personal, su estado y el seguimiento de cada caso.', 'Registros · Estados · Historial', 'Ver incidencias'],
    ['Ajustes administrativos', 'Consulta los ajustes documentados y las referencias asociadas a las evaluaciones.', 'Referencias · Motivos · Consulta', 'Consultar ajustes'],
    ['PIN temporal', 'Consulta solicitudes de acceso temporal para trabajadores y su estado.', 'Solicitudes · Accesos · Seguimiento', 'Ver solicitudes'],
    ['Reportes', 'Consulta marcaciones por estación, trabajador y periodo, con opción de exportación.', 'Marcaciones · Filtros · CSV', 'Abrir reportes'],
    ['Comparación con Nómina', 'Revisa información de asistencia y referencias de nómina por trabajador y periodo.', 'Periodos · Referencias · Consulta', 'Ver comparación'],
    ['Configuración', 'Accede a la configuración de horarios, reglas de asistencia, puestos y lectores.', 'Horarios · Reglas · Personal · Lectores', 'Abrir configuración'],
];
$navigation = [];
foreach ($cards as $index => [$label, $description, $utility, $action]) {
    $navigation[] = [
        'label' => $label,
        'description' => $description,
        'utility' => $utility,
        'action' => $action,
        'icon' => 'ti-calendar-check',
        'href' => '/checador/modulo-' . $index . '?workplace_id=1',
        'available' => true,
    ];
}
$mainHtml = $render('index.php', [
    'moduleNavigation' => $navigation,
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Centro de prueba & estación',
    'selectedWorkplaceKind' => 'Estación',
    'isModuleDirectory' => true,
]);
$unauthorizedNavigation = $navigation;
$unauthorizedNavigation[1]['available'] = false;
$unauthorizedNavigation[1]['unavailable'] = 'Disponible con autorización de lectura de programación.';
$unauthorizedHtml = $render('index.php', [
    'moduleNavigation' => $unauthorizedNavigation,
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Centro de prueba & estación',
    'selectedWorkplaceKind' => 'Estación',
    'isModuleDirectory' => true,
]);
$unauthorizedFeaturedNavigation = $navigation;
$unauthorizedFeaturedNavigation[0]['available'] = false;
$unauthorizedFeaturedHtml = $render('index.php', [
    'moduleNavigation' => $unauthorizedFeaturedNavigation,
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Centro de prueba & estación',
    'selectedWorkplaceKind' => 'Estación',
    'isModuleDirectory' => true,
]);
$dailyHtml = $render('daily-attendance.php', [
    'moduleNavigation' => $navigation,
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Centro de prueba & estación',
    'selectedWorkplaceKind' => 'Estación',
    'selectedDate' => '2026-10-22',
    'selectedWorkerId' => null,
    'demoMode' => false,
    'state' => 'success',
    'attendanceCoverage' => ['complete' => true],
    'attendanceRows' => [],
]);
$assert(str_contains($routes, "'/recursos-humanos/checador'")
    && str_contains($indexAction, '$this->renderModuleDirectory();')
    && !str_contains($indexAction, "renderDashboardWorkspace('daily-attendance')"), 'authenticated root route dispatches to the module directory only');
$assert(str_contains($routes, "'/recursos-humanos/checador/asistencia'")
    && str_contains($controller, 'public function dailyAttendance(): void')
    && str_contains($controller, "renderDashboardWorkspace('daily-attendance')")
    && str_contains($controller, "'schedule_week' => '1'")
    && str_contains($controller, "'schedule_history' => '1'"), 'daily alias and separate weekly/history routes remain available');
$assert(!str_contains($mainHtml, '<h1')
    && substr_count($mainHtml, '<h2') === 1
    && !str_contains($mainHtml, 'Estación:')
    && !str_contains($mainHtml, 'Centro de prueba &amp; estación')
    && !str_contains($mainHtml, '<table')
    && !str_contains($mainHtml, 'snapshot_ref'), 'main route renders only the card directory without duplicate title, station text, or operational data');
$assert(str_contains($layout, '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>')
    && str_contains($layout, "<?= \$moduleStationSelector ?? '' ?>")
    && str_contains($layout, 'Breadcrumb::render();')
    && str_contains($controller, "'title' => 'Checador'"), 'institutional title, breadcrumb layout, and shared station selector remain outside the directory view');
$assert(substr_count($mainHtml, 'data-checador-featured-card') === 1
    && substr_count($mainHtml, 'data-checador-module-card') === 9
    && substr_count($mainHtml, '<article') === 10
    && str_contains($mainHtml, 'Asistencia del día')
    && substr_count($mainHtml, '<h2') === 1
    && !str_contains($mainHtml, 'Módulos de Checador</h2>')
    && str_contains($controller, "'href' => \$queryString(\$base . '/asistencia', \$context)"), 'directory renders all ten cards and links the daily view to its separate route');
$copyPreserved = true;
foreach ($cards as [$label, $description, $utility, $action]) {
    foreach ([$label, $description, ...array_map('trim', explode('·', $utility))] as $text) {
        $copyPreserved = $copyPreserved && str_contains($mainHtml, htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
}
$assert($copyPreserved, 'directory renders all ten card titles, descriptions, and utility labels');
$controllerCopyPreserved = true;
foreach ($cards as [$label, $description, $utility, $action]) {
    foreach ([$label, $description, $utility, $action] as $text) {
        $controllerCopyPreserved = $controllerCopyPreserved && str_contains($controller, "'" . $text . "'");
    }
}
$assert($controllerCopyPreserved, 'controller defines all owner-approved card copy verbatim');
$expectedColumns = ['Nombre completo', 'Horario efectivo', 'Entrada 1', 'Salida 1', 'Entrada 2', 'Salida 2', 'Estado', 'Incidencias', 'Acciones'];
$columnCount = 0;
foreach ($expectedColumns as $column) if (str_contains($dailyHtml, '>' . $column . '</th>')) $columnCount++;
$assert($columnCount === 9 && str_contains($dailyHtml, '<h1 class="h3 mb-1">Asistencia del día</h1>')
    && str_contains($dailyHtml, 'data-checador-filter-modal')
    && str_contains($dailyView, 'Ver detalle')
    && !str_contains($dailyView, 'Almuerzo') && !str_contains($dailyView, 'Comida'), 'separate attendance workspace keeps nine operational columns and neutral four-slot semantics');
$assert(str_contains($mainView, 'aria-label="Módulos de Checador"')
    && !str_contains($dailyHtml, 'Comparación con Nómina')
    && !str_contains($dailyHtml, 'Configuración')
    && !str_contains($dailyView, "require __DIR__ . '/index.php'"), 'daily attendance stays focused and does not embed unrelated module cards');
$assert(substr_count($mainHtml, '<a href=') === 10
    && substr_count($mainHtml, 'focus-ring-primary rounded-3') === 10
    && !str_contains($mainHtml, 'class="btn'), 'all ten authorized cards are full-card links with visible focus and no inner buttons');
$assert(str_contains($mainHtml, 'display-6 fw-bolder text-white')
    && str_contains($mainHtml, 'rounded-3 bg-primary text-white p-4')
    && str_contains($mainHtml, 'fs-13 text-white')
    && str_contains($mainHtml, 'ti-chevron-right fs-9 text-white')
    && str_contains($mainHtml, 'fs-7 text-white mb-4'), 'featured card uses a contrasting icon tile, larger icon and chevron, strong title and readable description');
$assert(substr_count($mainHtml, 'card-title fs-6 fw-bolder mb-2') === 9
    && substr_count($mainHtml, 'text-body-secondary mb-3') === 9
    && substr_count($mainHtml, 'bg-primary-subtle text-primary p-3') === 9
    && substr_count($mainHtml, 'fs-9" aria-hidden="true"') === 9
    && substr_count($mainHtml, 'ti ti-chevron-right fs-7 text-primary') === 9
    && !str_contains($mainHtml, 'text-secondary mb-3')
    && !str_contains($mainHtml, 'fs-4" aria-hidden="true"'), 'all nine secondary cards use institutional dark titles, muted descriptions and visible icons');
$previousStyleCausesVerified = preg_match('/\.display-6\s*\{[^}]*font-weight:\s*300;/', $themeCss) === 1
    && preg_match('/\.fs-1\s*\{\s*font-size:\s*0\.625rem !important;/', $themeCss) === 1
    && preg_match('/\.fs-4\s*\{\s*font-size:\s*1rem !important;/', $themeCss) === 1
    && preg_match('/\.fw-bold\s*\{\s*font-weight:\s*500 !important;/', $themeCss) === 1
    && str_contains($themeCss, '--bs-secondary: #49beff;')
    && str_contains($themeCss, 'color: var(--bs-secondary-color) !important;');
$assert($previousStyleCausesVerified, 'V8O-R3 typography scale, weight and secondary-blue color issues are confirmed in the active stylesheet');
$assert(substr_count($mainHtml, 'badge rounded-pill bg-white text-primary fw-semibold px-3 py-2') === 3,
    'featured card uses three light badges');
$assert(substr_count($mainHtml, 'badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1') === 28,
    'secondary cards use light-background badges');
$assert(substr_count($mainHtml, 'bg-primary-subtle text-primary p-3') === 9,
    'all secondary cards use consistently sized light icon tiles');
$assert(substr_count($mainHtml, '<li><span class="badge rounded-pill') === 31,
    'all module utility badges remain informational and non-interactive');
$assert(!str_contains($mainHtml, 'style='), 'card redesign adds no inline styles');
$assert(str_contains($mainHtml, 'row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3')
    && str_contains($mainHtml, 'focus-ring focus-ring-primary'), 'responsive Bootstrap grid and visible keyboard focus utilities remain in use');
$themeClasses = ['display-6', 'fs-1', 'fs-4', 'fs-5', 'fs-6', 'fs-7', 'fs-9', 'fs-12', 'fs-13', 'fw-bold', 'fw-bolder', 'fw-semibold', 'card-title', 'text-primary', 'text-primary-emphasis', 'text-secondary', 'text-body-secondary', 'text-white', 'bg-white', 'bg-body', 'bg-primary-subtle', 'rounded-3', 'rounded-pill', 'badge', 'gap-2', 'list-unstyled', 'd-flex', 'flex-wrap', 'mt-auto', 'mb-0', 'p-3', 'p-4', 'p-lg-4', 'px-3', 'py-2', 'shadow-sm', 'border', 'focus-ring', 'focus-ring-primary', 'body-container-do', 'icon-container-do', 'card-hover'];
$realThemeClasses = str_contains($layout, "asset('css/styles.css')");
foreach ($themeClasses as $class) {
    $realThemeClasses = $realThemeClasses && str_contains($themeCss, '.' . $class);
}
$assert(str_contains($themeCss, 'Bootstrap  v5.3.2') && $realThemeClasses, 'all card classes exist in the active shared stylesheet; the embedded Bootstrap version is verified separately');
$assert(substr_count($unauthorizedHtml, '<a href=') === 9
    && substr_count($unauthorizedHtml, 'aria-disabled="true"') === 1
    && str_contains($unauthorizedHtml, 'Acceso según permisos del perfil.')
    && !str_contains($unauthorizedHtml, 'Disponible con autorización de lectura de programación.')
    && !str_contains($unauthorizedHtml, 'No disponible para este centro o perfil.')
    && !str_contains($unauthorizedHtml, '/checador/modulo-1?workplace_id=1'), 'unauthorized card has an availability message and no navigable link');
$assert(substr_count($unauthorizedFeaturedHtml, '<a href=') === 9
    && str_contains($unauthorizedFeaturedHtml, 'data-checador-featured-card aria-disabled="true"')
    && !str_contains($unauthorizedFeaturedHtml, 'href="/checador/modulo-0?workplace_id=1"')
    && str_contains($unauthorizedFeaturedHtml, 'Acceso según permisos del perfil.')
    && !str_contains($unauthorizedFeaturedHtml, '>Disponible</'), 'featured attendance card is also non-interactive when its server gate denies access');
$assert(str_contains($dailyHtml, 'Registros observados') === false
    && str_contains($dailyView, 'event_uuid'), 'empty attendance is not inferred and event provenance remains available when returned');
$assert(str_contains($dailyView, 'snapshot_ref'), 'daily attendance preserves snapshot context in detail navigation');
$assert(str_contains($controller, 'ChecadorAccessService::authorize($requestedWorkplace)')
    && str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY"), 'selected station is reauthorized and uses the shared Checador selector context');

printf("CHECADOR_V8O_MODULE_DIRECTORY_DAILY_ATTENDANCE: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
