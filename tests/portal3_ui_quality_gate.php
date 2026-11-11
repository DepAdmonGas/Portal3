<?php

declare(strict_types=1);

/**
 * Portal3 UI quality gate.
 *
 * Renders the real attendance partial inside the real operating layout with
 * local, synthetic data. This verifies generated HTML structure; it does not
 * replace an authenticated browser run or assert pixel-level appearance.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Breadcrumb;

$root = dirname(__DIR__);
$passes = 0;
$fails = 0;
$browserVerified = getenv('PORTAL3_AUTHENTICATED_E2E_PASS') === '1';
$blocked = $browserVerified ? 0 : 1;
$assert = static function (bool $ok, string $message) use (&$passes, &$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . "\n";
    $ok ? $passes++ : $fails++;
};
$domFor = static function (string $html): array {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return [$document, new DOMXPath($document)];
};
$hasClass = static fn(DOMElement $element, string $class): bool => in_array(
    $class,
    preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [],
    true
);

$renderAttendance = static function (string $state, bool $canCreate, bool $hasCreateKey) use ($root): string {
    $selectedWorkplaceId = 12;
    $selectedWorkplaceName = 'Estación Centro';
    $selectedWorkplaceKind = 'Estación';
    $selectedDate = '2026-10-28';
    $snapshotRef = 'snapshot-ui-gate';
    $selectedWorkerId = null;
    $workerSelectOptions = [];
    $timezone = ['timezone' => 'America/Mexico_City'];
    $attendanceRows = [[
        'worker_id' => 1004,
        'full_name' => 'Alicia Montalvo',
        'employee_number' => '990101',
        'attendance_relationship' => 'observed',
        'status' => 'evaluated',
        'observed_count' => 0,
        'schedule' => ['estado_jornada' => 'UNSCHEDULED'],
        'slots' => [],
        'additional_events' => [],
    ]];
    $administrativeIncidences = [];
    $administrativeErrors = [];
    $incidenceCreateAllowed = $canCreate;
    $incidenceCreateKeys = $hasCreateKey ? [1004 => 'fixture-create-key'] : [];
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
};

$renderPage = static function (string $body) use ($root): string {
    $user = (object) [
        'nombre' => 'Cuenta de pruebas',
        'puesto' => (object) ['tipo_puesto' => 'Pruebas locales'],
        'email' => 'ui-gate@example.invalid',
        'estacion' => (object) ['nombre' => 'Estación Centro'],
    ];
    $title = 'Asistencia del día';
    $content = $body;
    $moduleStationSelector = '<div data-module-station-selector>Estación: Estación Centro</div>';
    $moduleStationKey = 'checador';
    $filtro_usuario = [];
    $links = [
        '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        '/assets/libs/select2/dist/css/select2.min.css',
        '/assets/css/select2-modal.css',
    ];
    $scripts = [
        '/assets/js/vendor.min.js',
        '/assets/libs/select2/dist/js/select2.full.min.js',
        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
        '/assets/js/core/module-station-selector.js',
        '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js',
    ];
    $yearMesTemplate = '';
    $nombreContexto = '';
    $detalle = [];
    $esGestoria = false;
    $multiestacion = false;
    $idDepto = null;
    $idEstacion = null;
    $departamentosFiltrados = [];
    $estacionesFiltradas = [];
    $nombreFiltro = '';
    $totalPendientes = 0;
    $pendientesJson = '{}';
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Asistencia', null);
        require $root . '/app/Views/layouts/departamento-operativo.php';
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
};
$renderIncidenceView = static function (bool $canCreate = false) use ($root): string {
    $administrativeIncidences = [1004 => [[
        'uuid_incidencia' => 'de12bfa2-20db-4fee-9ba7-4c0bb8ff9da0',
        'worker_id' => 1004,
        'workplace_id' => 12,
        'category' => 'JUSTIFICATION',
        'current_status' => 'PENDING_REVIEW',
        'current_revision' => 1,
        'summary_reason' => 'Registro sintético para probar estructura visual.',
        'date_range' => ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'],
        'revisions' => [],
    ]]];
    $workerDisplayNames = [1004 => 'Alicia Montalvo'];
    $workerSelectOptions = [['worker_id' => 1004, 'full_name' => 'Alicia Montalvo', 'employee_number' => '990101']];
    $selectedWorkerId = 1004;
    $selectedWorkplaceId = 12;
    $selectedWorkplaceName = 'Estación Centro';
    $selectedDate = '2026-10-28';
    $timezone = ['timezone' => 'America/Mexico_City'];
    $administrativeErrors = [];
    $incidenceCreateAllowed = $canCreate;
    $localQaIncidenceStoreAvailable = $canCreate;
    $persistentQaFixtureMode = true;
    $lifecyclePermissions = [];
    $lifecycleKeys = [];
    $state = 'success';
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php';
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$html = $renderPage($renderAttendance('success', false, false));
[, $xpath] = $domFor($html);
$mainContent = $xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," body-wrapper ")]/div[contains(concat(" ",normalize-space(@class)," ")," container-fluid ")]')->item(0);
$visiblePageTitleHeadings = static function (DOMXPath $xpath, ?DOMElement $mainContent, string $pageTitle): array {
    if (!$mainContent instanceof DOMElement) return [];
    $matches = [];
    $excludedTags = ['A', 'BUTTON', 'INPUT', 'LABEL', 'NAV', 'OPTION', 'SCRIPT', 'SELECT', 'STYLE', 'TEXTAREA'];
    foreach ($xpath->query('.//*', $mainContent) as $element) {
        if (!$element instanceof DOMElement) continue;
        $text = preg_replace('/\s+/u', ' ', trim($element->textContent)) ?? trim($element->textContent);
        if ($text !== $pageTitle) continue;
        $classNames = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
        $looksLikeHeading = preg_match('/\Ah[1-6]\z/i', $element->tagName) === 1
            || $element->getAttribute('role') === 'heading'
            || in_array('fw-semibold', $classNames, true)
            || count(array_intersect($classNames, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'page-title'])) > 0
            || count(array_intersect($classNames, ['display-1', 'display-2', 'display-3', 'display-4', 'display-5', 'display-6'])) > 0;
        if (!$looksLikeHeading) continue;

        $excluded = false;
        for ($ancestor = $element; $ancestor instanceof DOMElement; $ancestor = $ancestor->parentNode instanceof DOMElement ? $ancestor->parentNode : null) {
            if (in_array(strtoupper($ancestor->tagName), $excludedTags, true)
                || $ancestor->hasAttribute('hidden')
                || strtolower($ancestor->getAttribute('aria-hidden')) === 'true'
                || preg_match('/(?:^|\s)d-none(?:\s|$)/', $ancestor->getAttribute('class')) === 1
                || preg_match('/(?:^|\s)(?:dropdown-menu|modal|breadcrumb|visually-hidden)(?:\s|$)/', $ancestor->getAttribute('class')) === 1
                || preg_match('/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\s*(?:;|$)/i', $ancestor->getAttribute('style')) === 1) {
                $excluded = true;
                break;
            }
        }
        if ($excluded) continue;

        // A styled wrapper around a real heading is the same visual heading,
        // so count only the most specific matching heading element.
        $containsMatchingHeading = false;
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof DOMElement) continue;
            $descendantText = preg_replace('/\s+/u', ' ', trim($descendant->textContent)) ?? trim($descendant->textContent);
            $descendantClasses = preg_split('/\s+/', trim($descendant->getAttribute('class'))) ?: [];
            if ($descendantText === $pageTitle && (preg_match('/\Ah[1-6]\z/i', $descendant->tagName) === 1
                || $descendant->getAttribute('role') === 'heading'
                || in_array('fw-semibold', $descendantClasses, true)
                || count(array_intersect($descendantClasses, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'page-title'])) > 0
                || count(array_intersect($descendantClasses, ['display-1', 'display-2', 'display-3', 'display-4', 'display-5', 'display-6'])) > 0)) {
                $containsMatchingHeading = true;
                break;
            }
        }
        if (!$containsMatchingHeading) $matches[] = $element;
    }
    return $matches;
};
$titleHeadings = $visiblePageTitleHeadings($xpath, $mainContent instanceof DOMElement ? $mainContent : null, 'Asistencia del día');
$assert(count($titleHeadings) === 1, 'rendered main area has only one visible visual heading with the page title');
$duplicateTitleFixture = $renderPage($renderAttendance('success', false, false) . '<div class="h4">Asistencia del día</div>');
[, $duplicateTitleXpath] = $domFor($duplicateTitleFixture);
$duplicateTitleMain = $duplicateTitleXpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," body-wrapper ")]/div[contains(concat(" ",normalize-space(@class)," ")," container-fluid ")]')->item(0);
$assert(count($visiblePageTitleHeadings($duplicateTitleXpath, $duplicateTitleMain instanceof DOMElement ? $duplicateTitleMain : null, 'Asistencia del día')) === 2, 'title checker detects a duplicate non-semantic visual heading in the rendered main area');
$nonTitleFixture = $renderPage($renderAttendance('success', false, false)
    . '<nav class="breadcrumb"><h2>Asistencia del día</h2></nav>'
    . '<div class="dropdown-menu"><h2>Asistencia del día</h2></div>'
    . '<div class="d-none"><h2>Asistencia del día</h2></div>'
    . '<select><option>Asistencia del día</option></select>');
[, $nonTitleXpath] = $domFor($nonTitleFixture);
$nonTitleMain = $nonTitleXpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," body-wrapper ")]/div[contains(concat(" ",normalize-space(@class)," ")," container-fluid ")]')->item(0);
$assert(count($visiblePageTitleHeadings($nonTitleXpath, $nonTitleMain instanceof DOMElement ? $nonTitleMain : null, 'Asistencia del día')) === 1, 'breadcrumb, dropdown, hidden heading and select option do not count as duplicate page headings');
if (count($titleHeadings) === 1) {
    echo 'TITLE_SOURCE app/Views/layouts/departamento-operativo.php:367 (institutional page heading); no second visible visual heading exists in the current rendered fixture.' . "\n";
} else {
    foreach ($titleHeadings as $heading) {
        $classLabel = trim($heading->getAttribute('class'));
        echo 'TITLE_DUPLICATE_NODE <' . $heading->tagName . '> id=' . $heading->getAttribute('id') . ' class=' . $classLabel . "\n";
    }
}
$assert($xpath->query('//nav[@aria-label="breadcrumb"]/ol[contains(@class,"breadcrumb")]')->length === 1, 'rendered page retains the breadcrumb');
$assert(str_contains($html, 'Estación Centro') && str_contains($html, '28 de octubre de 2026'), 'rendered page presents the selected station and formatted work date');
$assert($xpath->query('//script[contains(@src,"bootstrap.bundle.min.js")]')->length === 1, 'real layout includes the Bootstrap bundle required by native dropdowns and modals');

$table = $xpath->query('//table[@id="checador-daily-attendance-table"]')->item(0);
$actionCell = $xpath->query('//table[@id="checador-daily-attendance-table"]/tbody/tr[1]/td[8]')->item(0);
$nameCell = $xpath->query('//table[@id="checador-daily-attendance-table"]/tbody/tr[1]/th[1]')->item(0);
$dataCells = $xpath->query('//table[@id="checador-daily-attendance-table"]/tbody/tr[1]/td[position() < 8]');
$trigger = $xpath->query('//table[@id="checador-daily-attendance-table"]//button[@data-bs-toggle="dropdown"]')->item(0);
$dropdown = $xpath->query('//table[@id="checador-daily-attendance-table"]//div[contains(concat(" ",normalize-space(@class)," ")," dropdown ")]')->item(0);
$menu = $xpath->query('//table[@id="checador-daily-attendance-table"]//ul[contains(concat(" ",normalize-space(@class)," ")," dropdown-menu ")]')->item(0);
$groupsReference = file_get_contents($root . '/public/assets/js/grupos/datatable.init.js') ?: '';
$attendanceInitJs = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js') ?: '';
$bootstrapJs = file_get_contents($root . '/public/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?: '';
$actionNames = [];
foreach ($xpath->query('//table[@id="checador-daily-attendance-table"]//ul[contains(@class,"dropdown-menu")]/li/*') as $action) {
    $actionNames[] = trim($action->textContent);
}
$assert($table instanceof DOMElement && !$hasClass($table, 'text-center'), 'attendance data table does not apply text-center to the whole table');
$assert($actionCell instanceof DOMElement && $hasClass($actionCell, 'text-center') && $hasClass($actionCell, 'align-middle'), 'only the attendance action cell carries centering utilities');
$dataUncentered = true;
foreach ($dataCells as $cell) {
    if ($hasClass($cell, 'text-center')) $dataUncentered = false;
}
$assert($dataUncentered && $nameCell instanceof DOMElement && !$hasClass($nameCell, 'text-center')
    && $xpath->query('//*[@id and starts-with(@id,"daily-attendance-detail-")]//div[contains(concat(" ",normalize-space(@class)," ")," modal-body ") and contains(concat(" ",normalize-space(@class)," ")," text-center ")]')->length === 0,
    'attendance names/data cells and detail modal bodies remain normally aligned');
$assert(str_contains($groupsReference, '<div class="dropdown dropstart">')
    && str_contains($groupsReference, '<a class="pointer" data-bs-toggle="dropdown">')
    && str_contains($groupsReference, 'dropdown-item pointer d-flex align-items-center gap-3')
    && str_contains($groupsReference, 'fs-4 ti ti-edit'), 'Grupos reference confirms the shared dropstart, three-dots, aligned-item pattern');
$assert($dropdown instanceof DOMElement && $hasClass($dropdown, 'dropdown') && $hasClass($dropdown, 'dropstart')
    && $trigger instanceof DOMElement && strtolower($trigger->tagName) === 'button'
    && $trigger->getAttribute('type') === 'button'
    && $hasClass($trigger, 'text-muted') && $hasClass($trigger, 'pointer')
    && $hasClass($trigger, 'border-0') && $hasClass($trigger, 'bg-transparent') && $hasClass($trigger, 'p-0')
    && !$hasClass($trigger, 'btn') && !$hasClass($trigger, 'btn-outline-secondary'), 'three-dots activator uses the discreet native reference styling without a button skin');
$triggerIcon = $xpath->query('.//i[contains(concat(" ",normalize-space(@class)," ")," ti-dots-vertical ")]', $trigger instanceof DOMElement ? $trigger : null)->item(0);
$menuItemsNative = $menu instanceof DOMElement && $menu->getAttribute('id') === ($trigger instanceof DOMElement ? $trigger->getAttribute('aria-controls') : '');
foreach ($xpath->query('./li/*', $menu instanceof DOMElement ? $menu : null) as $menuItem) {
    $itemIcon = $xpath->query('.//i[contains(concat(" ",normalize-space(@class)," ")," ti ")]', $menuItem)->item(0);
    $menuItemsNative = $menuItemsNative && $menuItem instanceof DOMElement
        && $hasClass($menuItem, 'dropdown-item') && $hasClass($menuItem, 'pointer')
        && $hasClass($menuItem, 'd-flex') && $hasClass($menuItem, 'align-items-center') && $hasClass($menuItem, 'gap-3')
        && $itemIcon instanceof DOMElement && $hasClass($itemIcon, 'fs-4');
}
$assert($menu instanceof DOMElement && $hasClass($menu, 'dropdown-menu') && $menuItemsNative, 'menu and icon/text rows use the native Bootstrap and Grupos item classes');
$assert($trigger instanceof DOMElement && $triggerIcon instanceof DOMElement && $hasClass($triggerIcon, 'fs-6')
    && $trigger->getAttribute('aria-expanded') === 'false' && $trigger->getAttribute('aria-haspopup') === 'true'
    && $trigger->getAttribute('aria-label') === 'Acciones para Alicia Montalvo'
    && $trigger->getAttribute('aria-controls') !== '', 'activator preserves an accessible label, expanded state and menu association');
$assert(str_contains($bootstrapJs, 'ArrowDown') && str_contains($bootstrapJs, 'ArrowUp') && str_contains($bootstrapJs, 'Escape')
    && $trigger instanceof DOMElement && strtolower($trigger->tagName) === 'button', 'native button focus and Bootstrap dropdown keyboard controls are available');
$assert(str_contains($attendanceInitJs, 'preDraw.dt.portal3AttendanceDropdown')
    && str_contains($attendanceInitJs, 'draw.dt.portal3AttendanceDropdown')
    && str_contains($attendanceInitJs, 'strategy: \'fixed\''), 'attendance DataTables uses the existing fixed Popper strategy to avoid responsive-table clipping');
$assert($actionNames === ['Ver detalle', 'Jornada', 'Incidencias'], 'default dropdown preserves exactly the three actions available without create permission');
$assert(count($actionNames) === count(array_unique($actionNames)) && $xpath->query('//table[@id="checador-daily-attendance-table"]//li//*[contains(@class,"disabled") or @disabled or @aria-disabled="true"]')->length === 0, 'dropdown has no repeated or disabled-as-authorized actions');
$detailTarget = $xpath->query('//table[@id="checador-daily-attendance-table"]//button[@data-bs-target]')->item(0)?->getAttribute('data-bs-target');
$assert(is_string($detailTarget) && $detailTarget !== '' && $xpath->query('//*[@id="' . substr($detailTarget, 1) . '"]')->length === 1, 'Ver detalle points to one rendered attendance detail modal');

$authorizedHtml = $renderPage($renderAttendance('success', true, true));
[, $authorizedXpath] = $domFor($authorizedHtml);
$authorizedActions = [];
foreach ($authorizedXpath->query('//table[@id="checador-daily-attendance-table"]//ul[contains(@class,"dropdown-menu")]/li/*') as $action) {
    $authorizedActions[] = trim($action->textContent);
}
$assert($authorizedActions === ['Ver detalle', 'Jornada', 'Incidencias', 'Registrar incidencia'], 'Registrar incidencia appears only when the render fixture provides permission and a key');
$missingKeyHtml = $renderPage($renderAttendance('success', true, false));
[, $missingKeyXpath] = $domFor($missingKeyHtml);
$assert($missingKeyXpath->query('//table[@id="checador-daily-attendance-table"]//li[contains(normalize-space(.),"Registrar incidencia")]')->length === 0, 'create action is omitted when its authorization key is absent');
$errorHtml = $renderPage($renderAttendance('error', true, true));
[, $errorXpath] = $domFor($errorHtml);
$assert($errorXpath->query('//table[@id="checador-daily-attendance-table"]//button[@data-bs-toggle="dropdown"]')->length === 0, 'error-state row does not expose an action trigger');

$incidenceHtml = $renderIncidenceView();
[, $incidenceXpath] = $domFor($incidenceHtml);
$incidenceRow = $incidenceXpath->query('//table[contains(@class,"table")]/tbody/tr[th[normalize-space(.)="Justificación"]]')->item(0);
$incidenceActionCell = $incidenceRow instanceof DOMElement ? $incidenceXpath->query('./td[last()]', $incidenceRow)->item(0) : null;
$singleAction = $incidenceActionCell instanceof DOMElement ? $incidenceXpath->query('.//*[self::button or self::a]', $incidenceActionCell)->item(0) : null;
$singleActionIsNative = $singleAction instanceof DOMElement
    && $hasClass($singleAction, 'btn')
    && $hasClass($singleAction, 'btn-sm')
    && $hasClass($singleAction, 'btn-outline-primary')
    && trim($singleAction->textContent) === 'Ver detalle'
    && $incidenceXpath->query('.//i[contains(concat(" ",normalize-space(@class)," ")," ti-eye ")]', $singleAction)->length === 1
    && $singleAction->getAttribute('data-bs-toggle') === 'modal';
$assert($singleActionIsNative && $incidenceActionCell instanceof DOMElement && $hasClass($incidenceActionCell, 'table-actions-cell'), 'single available incidence action renders as a native direct button centered in its action cell');
$assert($incidenceRow instanceof DOMElement && trim($incidenceRow->textContent) !== ''
    && str_contains($incidenceHtml, 'Alicia Montalvo')
    && str_contains($incidenceHtml, 'de12bfa2-20db-4fee-9ba7-4c0bb8ff9da0'),
    'Alicia Montalvo and her incidence record remain available in the rendered view');
$incidenceModalTarget = $singleAction instanceof DOMElement ? $singleAction->getAttribute('data-bs-target') : '';
$assert($incidenceModalTarget !== '' && $incidenceXpath->query('//*[@id="' . substr($incidenceModalTarget, 1) . '"]')->length === 1, 'Ver detalle retains its target and matching detail modal');
$incidenceHeaderActions = [];
foreach ($incidenceXpath->query('//section[@aria-label="Incidencias"]//div[contains(@class,"card-header")]//button') as $headerButton) {
    $incidenceHeaderActions[] = trim($headerButton->textContent);
}
$assert($incidenceHeaderActions === ['Buscar'], 'Incidencias preserves Buscar and omits Nueva incidencia without permission');
$incidenceAllowedHtml = $renderIncidenceView(true);
[, $incidenceAllowedXpath] = $domFor($incidenceAllowedHtml);
$allowedHeaderActions = [];
foreach ($incidenceAllowedXpath->query('//section[@aria-label="Incidencias"]//div[contains(@class,"card-header")]//button') as $headerButton) {
    $allowedHeaderActions[] = trim($headerButton->textContent);
}
$assert($allowedHeaderActions === ['Buscar', 'Nueva incidencia'], 'Incidencias preserves Nueva incidencia when its permission fixture is enabled');
$incidenceDataAligned = $incidenceRow instanceof DOMElement;
if ($incidenceRow instanceof DOMElement) {
    foreach ($incidenceXpath->query('./td[position() < last()]', $incidenceRow) as $cell) {
        if ($hasClass($cell, 'text-center')) $incidenceDataAligned = false;
    }
}
$incidenceModal = $incidenceModalTarget !== ''
    ? $incidenceXpath->query('//*[@id="' . substr($incidenceModalTarget, 1) . '"]')->item(0)
    : null;
$incidenceModalOutsideTable = $incidenceModal instanceof DOMElement;
for ($ancestor = $incidenceModal instanceof DOMElement ? $incidenceModal->parentNode : null; $ancestor instanceof DOMElement; $ancestor = $ancestor->parentNode) {
    if (in_array(strtolower($ancestor->tagName), ['table', 'td', 'th'], true)) {
        $incidenceModalOutsideTable = false;
        break;
    }
}
$assert($incidenceDataAligned
    && $incidenceModalOutsideTable
    && $incidenceXpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," modal-body ") and contains(concat(" ",normalize-space(@class)," ")," text-center ")]')->length === 0,
    'incidence data cells remain left-aligned and detail/history modals stay outside action cells and tables');

$renderJourneyView = static function (array $journeys) use ($root): string {
    $journeyErrors = [];
    $state = 'success';
    $selectedWorkplaceId = 1;
    $selectedWorkplaceName = 'Interlomas';
    $selectedDate = '2026-10-28';
    $selectedWorkerId = null;
    $dashboardHref = '/departamento-operativo/recursos-humanos/checador';
    $demoMode = false;
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php';
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
};
$journeyQaHtml = $renderJourneyView([1005 => [
    'qa_fixture' => true,
    'qa_fixture_version' => 'CHECADOR_M02_QA_20261028_V1',
    'qa_status_label' => 'Jornada completa',
    'worker_name' => 'Bruno Cervantes',
    'evaluated' => false,
    'journey_status' => 'QA_SIMULATED',
    'context' => ['fecha_local' => '2026-10-28', 'timezone' => 'America/Mexico_City'],
    'schedule' => ['estado_jornada' => 'SCHEDULED'],
    'calculation' => ['qa_state' => 'NOT_CALCULATED'],
    'source_evidence' => ['source_classification' => 'SYNTHETIC_QA_FIXTURE'],
    'qa_events' => [[
        'event_uuid' => '7f5bcda8-8a04-4a8e-975b-cc073df00555',
        'ocurrido_at_utc' => '2026-10-28 13:55:00',
        'pairing_role' => 'ENTRY',
        'id_dispositivo' => 1001,
    ]],
]]);
[, $journeyQaXpath] = $domFor($journeyQaHtml);
$journeyAction = $journeyQaXpath->query('//table//td[contains(concat(" ",normalize-space(@class)," ")," table-actions-cell ")]//button')->item(0);
$journeyActionCell = $journeyAction instanceof DOMElement ? $journeyAction->parentNode : null;
$journeyModalTarget = $journeyAction instanceof DOMElement ? $journeyAction->getAttribute('data-bs-target') : '';
$journeyModal = $journeyModalTarget !== ''
    ? $journeyQaXpath->query('//*[@id="' . substr($journeyModalTarget, 1) . '"]')->item(0)
    : null;
$journeyModalOutsideTable = $journeyModal instanceof DOMElement;
for ($ancestor = $journeyModal instanceof DOMElement ? $journeyModal->parentNode : null; $ancestor instanceof DOMElement; $ancestor = $ancestor->parentNode) {
    if (in_array(strtolower($ancestor->tagName), ['table', 'td', 'th'], true)) {
        $journeyModalOutsideTable = false;
        break;
    }
}
$journeyActionIsNative = $journeyAction instanceof DOMElement
    && $hasClass($journeyAction, 'btn')
    && $hasClass($journeyAction, 'btn-sm')
    && $hasClass($journeyAction, 'btn-outline-primary')
    && $journeyAction->getAttribute('data-bs-toggle') === 'modal'
    && $journeyAction->getAttribute('aria-haspopup') === 'dialog'
    && $journeyQaXpath->query('.//i[contains(concat(" ",normalize-space(@class)," ")," ti-history ")]', $journeyAction)->length === 1;
$assert($journeyActionIsNative && $journeyActionCell instanceof DOMElement
    && $hasClass($journeyActionCell, 'table-actions-cell')
    && $journeyModalOutsideTable,
    'Journey uses a native Modernize action button and renders its modal outside tables and action cells');
$journeyModalBody = $journeyModal instanceof DOMElement
    ? $journeyQaXpath->query('.//div[contains(concat(" ",normalize-space(@class)," ")," modal-body ")]', $journeyModal)->item(0)
    : null;
$journeyUuid = $journeyModal instanceof DOMElement ? $journeyQaXpath->query('.//code', $journeyModal)->item(0) : null;
$qaResultCell = $journeyQaXpath->query('//table/tbody/tr[1]/td[3]')->item(0);
$assert($journeyModalBody instanceof DOMElement
    && $hasClass($journeyModalBody, 'text-start')
    && $journeyUuid instanceof DOMElement
    && $hasClass($journeyUuid, 'text-break')
    && $qaResultCell instanceof DOMElement
    && trim($qaResultCell->textContent) === 'Sin evaluación · escenario QA local'
    && str_contains($journeyQaHtml, 'Escenario sintético QA local; no representa una evaluación oficial de Journey.')
    && !str_contains($journeyQaHtml, 'Jornada completa')
    && !str_contains($journeyQaHtml, 'btn-table-action'),
    'unevaluated QA Journey remains neutral and its detail content and UUID use native alignment utilities');
$canonicalJourneyHtml = $renderJourneyView([558 => [
    'evaluated' => true,
    'journey_status' => 'EVALUATED',
    'context' => ['fecha_local' => '2026-10-28', 'timezone' => 'America/Mexico_City'],
    'schedule' => ['estado_jornada' => 'SCHEDULED'],
    'calculation' => ['estado_calculo' => 'CALCULATED', 'segundos_trabajados_totales' => 28800],
    'source_evidence' => ['snapshot_audit_ref' => 'CANONICAL-AUDIT-REF'],
    'evaluated_raw_event_count' => 1,
    'evaluated_raw_events' => [],
]]);
$assert(str_contains($canonicalJourneyHtml, 'Resultado disponible'), 'authoritatively evaluated Journey retains its canonical result label');

$incidenceView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php') ?: '';
$journeyView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php') ?: '';
$temporaryAccessView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php') ?: '';
$scheduleView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$scheduleController = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$scheduleService = file_get_contents($root . '/app/Services/ChecadorDailyReadService.php') ?: '';
$weeklyScheduleService = file_get_contents($root . '/app/Services/ChecadorWeeklyScheduleService.php') ?: '';
$scheduleRoutes = file_get_contents($root . '/routes/web.php') ?: '';
$actionCss = file_get_contents($root . '/public/assets/css/table-actions.css') ?: '';
$stylesCss = file_get_contents($root . '/public/assets/css/styles.css') ?: '';
$remainingConsumers = [];
$reportDatatable = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js') ?: '';
$allRemainingConsumersPresent = !str_contains($incidenceView, 'btn-table-action')
    && !str_contains($reportDatatable, 'btn-table-action')
    && str_contains($reportDatatable, 'class="btn btn-sm btn-outline-primary" data-checador-detail')
    && str_contains($reportDatatable, 'ti ti-eye me-1')
    && str_contains($actionCss, '.btn-table-action')
    && str_contains($stylesCss, '.btn-outline-primary');
foreach ($remainingConsumers as $consumerSource) {
    $allRemainingConsumersPresent = $allRemainingConsumersPresent && str_contains($consumerSource, 'btn-table-action');
}
$assert($allRemainingConsumersPresent
    && !str_contains($journeyView, 'btn-table-action')
    && !str_contains($temporaryAccessView, 'btn-table-action')
    && !str_contains($scheduleView, 'btn-table-action')
    && str_contains($scheduleView, 'dropdown dropstart')
    && str_contains($scheduleView, 'ti ti-dots-vertical')
    && str_contains($scheduleView, 'data-habitual-worker-id')
    && str_contains($temporaryAccessView, 'dropdown dropstart')
    && str_contains($temporaryAccessView, 'ti ti-dots-vertical'),
    'Incidencias, M03, M04 and M07 use their current native Modernize action patterns');
$assert(!str_contains($actionCss, '.btn-table-action > .fa') && str_contains($actionCss, '.btn-table-action > .ti'), 'unused Font Awesome child selectors are removed while the consumed Tabler icon rule remains');
$assert(!preg_match('/(?:^|})\s*(?:table\s+td|\.modal-body\s*\*|\.card\s*\*|body\s+\.btn)\s*\{/m', $actionCss), 'table-actions.css has no scanned broad table/modal/card/button selectors');
$assert(str_contains($scheduleRoutes, "Route::auth(['ChecadorController', 'createStationScheduleTemplate'])")
    && str_contains($scheduleRoutes, "'/recursos-humanos/checador/programacion-semanal/plantillas-locales'")
    && str_contains($scheduleController, 'createShiftTemplate($user, $template')
    && str_contains($scheduleService, "'POST', 'api/v2/shift-templates'")
    && str_contains($scheduleService, '$this->getShiftTemplateVersion($user, $key, 1, $workplaceId)'),
    'schedule-template registration uses the existing authenticated API POST and requires canonical version readback');
$assert(str_contains($weeklyScheduleService, 'workersWithVerifiedMembership')
    && str_contains($scheduleController, 'workersWithVerifiedMembership(')
    && str_contains($scheduleController, 'firstWeekdayOnOrAfter(')
    && str_contains($scheduleController, '[$targetDate]')
    && str_contains($scheduleView, '$canInitialOnPartialMembership')
    && str_contains($scheduleView, "\$row['schedule_read_status'] ?? ''")
    && !str_contains($scheduleController, "in_array(\$coverage, ['ready', 'demo_fixture'], true)"),
    'initial assignment is enabled by complete per-worker reading and date-specific membership, not by blanket full-week coverage');
$assert(!str_contains($scheduleView, 'data-schedule-template-form')
    && !str_contains($scheduleView, '/programacion-semanal/plantillas')
    && str_contains($scheduleView, '/checador/horarios-turnos')
    && str_contains($scheduleView, '/programacion-semanal/asignar')
    && str_contains($scheduleView, 'name="tipo_asignacion"')
    && str_contains($scheduleView, 'value="REST">Descanso explícito')
    && str_contains($scheduleView, 'data-schedule-worker-select')
    && str_contains($scheduleView, 'data-schedule-target-date'),
    'M03 reuses M04 templates and its authorized assignment writer without a duplicate template editor');
echo 'RISK table-actions-cell centers the whole opted-in cell, not only its button; current Checador usages must remain limited to action cells.' . "\n";
echo 'NOT_VERIFIED no live CSS cascade, viewport geometry, or screenshot comparison is produced by fixture DOM rendering.' . "\n";
echo 'BROWSER_RUNNER ' . (is_file($root . '/tests/e2e/node_modules/.bin/playwright') ? 'PLAYWRIGHT_INSTALLED' : 'PLAYWRIGHT_NOT_INSTALLED') . "\n";
echo 'BROWSER_VERIFIED ' . ($browserVerified ? 'YES (authenticated read-only E2E completed by quality wrapper)' : 'NO') . "\n";
if (!$browserVerified) {
    echo 'BLOCKED authenticated browser verification: the E2E runner exists, but quality must be invoked with both authorized local profile states and a successful browser run.' . "\n";
}

echo "SUMMARY PASS={$passes} FAIL={$fails} BLOCKED={$blocked}\n";
echo "DOM_RENDER_MODE=FIXTURE_RENDERED_DOM\n";
echo 'VISUAL_ACCEPTANCE=' . ($browserVerified ? 'PENDING_USER_APPROVAL' : 'BLOCKED') . "\n";
exit($fails > 0 ? 1 : 0);
