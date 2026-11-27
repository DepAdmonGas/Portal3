<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/CsrfToken.php';

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/role-comodines.php';
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$base = [
    'coverageRows' => [[
        'coverage_uuid' => '18f3b93e-a184-4ce8-bcc0-1b949962a3e9',
        'coverage_id' => 501,
        'worker_id' => 1004,
        'home_workplace_id' => 2,
        'coverage_workplace_id' => 1,
        'worker_label' => '<img src=x onerror="window.__comodinesXss=true">',
        'home_label' => 'Palo Solo',
        'coverage_label' => 'Interlomas',
        'period_label' => '10/11/2026 a 13/11/2026 (fin no incluido)',
        'from_local' => '2026-11-10',
        'to_local_exclusive' => '2026-11-13',
        'status_label' => 'Pendiente',
        'status_code' => 'REQUESTED',
        'requested_at_utc' => '2026-11-09 17:00:00.000000',
        'requested_at_label' => '09/11/2026 11:00',
    ]],
    'roles' => [[
        'period_label' => '01/11/2026 al 07/11/2026',
        'status_label' => 'Finalizado',
        'station_label' => 'Palo Solo',
        'identity_label' => '<img src=x onerror="window.__legacyXss=true">',
        'days_label' => 'Lunes, Miércoles',
        'programming_label' => 'Programación incompleta o legacy',
    ]],
    'state' => 'ready',
    'stateMessage' => '',
    'coverageReadState' => 'ready',
    'coverageReadMessage' => '',
    'canRequestCoverage' => false,
    'canReviewCoverage' => false,
    'canCancelCoverage' => false,
    'selectedWorkplaceId' => 2,
    'selectedWorkplaceName' => 'Palo Solo',
    'workplaces' => [['id' => 2, 'nombre' => 'Palo Solo'], ['id' => 9, 'nombre' => 'Autolavado']],
    'coverageWorkplaces' => [['id' => 1, 'nombre' => 'Interlomas']],
    'coverageTemplates' => [],
    'coverageCreateUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas',
    'coverageWorkersUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/trabajadores',
    'coverageDetailUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas',
];
$render = static function (array $overrides = []) use ($viewPath, $base): string {
    extract(array_merge($base, $overrides), EXTR_SKIP);
    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};

$html = $render();
$previous = libxml_use_internal_errors(true);
$document = new DOMDocument('1.0', 'UTF-8');
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($document);
$assert($xpath->query('//*[@id="checador-role-comodines-heading"]')->length === 0
    && !str_contains($html, 'Administración de coberturas del personal')
    && !str_contains($html, 'Centro:')
    && $xpath->query('//button[@data-bs-target="#checador-comodines-search-modal"]')->length === 1,
    'the duplicate title, subtitle, and center label are removed while search remains available');
$headers = $xpath->query('//table[@id="checador-role-comodines-canonical-table"]/thead/tr/th');
$labels = [];
if ($headers !== false) foreach ($headers as $header) $labels[] = trim($header->textContent);
$assert($labels === ['Trabajador', 'Localidad habitual', 'Localidad de cobertura', 'Periodo', 'Estado', 'Fecha de solicitud', 'Acciones'],
    'the canonical table contains only the seven requested real fields');
$row = $xpath->query('//table[@id="checador-role-comodines-canonical-table"]/tbody/tr')->item(0);
$assert($row instanceof DOMElement
    && $row->getAttribute('data-worker-id') === '1004'
    && $row->getAttribute('data-home-workplace-id') === '2'
    && $row->getAttribute('data-coverage-workplace-id') === '1'
    && $row->getAttribute('data-from-local') === '2026-11-10'
    && $row->getAttribute('data-to-local-exclusive') === '2026-11-13'
    && $xpath->query('//table[@id="checador-role-comodines-canonical-table"]//button[@data-coverage-detail]')->length === 1
    && $xpath->query('//table[@id="checador-role-comodines-canonical-table"]//ul[contains(@class,"dropdown-menu")]')->length === 0,
    'canonical row carries filter metadata and uses one direct detail action rather than a needless dropdown');
$assert(str_contains($html, '&lt;img src=x onerror=&quot;window.__comodinesXss=true&quot;&gt;')
    && $xpath->query('//table[@id="checador-role-comodines-canonical-table"]//img')->length === 0
    && $xpath->query('//table[@id="checador-role-comodines-legacy-table"]//img')->length === 0,
    'canonical and historical labels are escaped before rendering');
$assert($xpath->query('//table[@id="checador-role-comodines-legacy-table"]')->length === 1
    && $xpath->query('//h3[normalize-space(.)="Registros históricos (Legacy)"]')->length === 1
    && $xpath->query('//table[@id="checador-role-comodines-legacy-table"]//button')->length === 0,
    'historical records remain in a separate read-only section');
$assert($xpath->query('//*[@id="checador-comodines-search-modal"]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-filter-from"]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-filter-worker"]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-filter-workplace"]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-filter-status"]')->length === 1,
    'search modal offers period, worker, authorized locality, and observed status filters');
$assert($xpath->query('//select[@id="checador-comodines-filter-worker"]/option[@value="1004" and contains(normalize-space(.),"ID 1004")]')->length === 1,
    'worker filter identifies existing coverage rows by canonical RH ID without adding unrelated roster options');
$assert(!str_contains($html, 'coverageWriterEnabled')
    && !str_contains($html, 'Las operaciones administrativas de cobertura están desactivadas en este entorno.'),
    'the page does not show an obsolete environment-based writer blocker');
$assert($xpath->query('//*[@id="checador-comodines-search-modal"]//div[contains(@class,"modal-header") and contains(@class,"bg-primary") and contains(@class,"text-white")]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-search-modal"]//button[contains(@class,"btn-close-white")]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-search-modal"]//button[@data-comodines-filter-clear and contains(@class,"btn-light")]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-search-modal"]//button[@data-bs-dismiss="modal" and contains(@class,"bg-danger-subtle") and contains(@class,"text-danger")]')->length === 1
    && $xpath->query('//*[@id="checador-comodines-search-modal"]//button[@data-comodines-filter-apply and contains(@class,"btn-primary")]')->length === 1,
    'search modal uses Portal3 blue header, close, neutral clear, soft-red cancel, and primary search controls');

$requestHtml = $render([
    'canRequestCoverage' => true,
    'coverageTemplates' => [['id' => 4, 'version' => 2, 'nombre' => 'Turno Local Matutino']],
]);
$requestDoc = new DOMDocument('1.0', 'UTF-8');
$requestDoc->loadHTML('<?xml encoding="utf-8" ?>' . $requestHtml);
$requestXPath = new DOMXPath($requestDoc);
$toolbar = $requestXPath->query('//div[contains(concat(" ",normalize-space(@class)," ")," justify-content-end ") and contains(concat(" ",normalize-space(@class)," ")," gap-2 ")]')->item(0);
$assert($requestXPath->query('//*[@id="checador-comodines-create-modal"]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-worker" and @data-select2-ajax]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-target"]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-target"]/option[@value="1" and normalize-space(.)="Interlomas"]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-target"]/option[@value="9"]')->length === 0
    && $requestXPath->query('//*[@data-coverage-day]')->length === 7
    && str_contains($requestHtml, 'Guardar solicitud')
    && str_contains($requestHtml, 'Descanso explícito')
    && str_contains($requestHtml, 'versión 2'),
    'new coverage modal renders Select2 worker search, authorized destination, seven explicit weekdays, and exact template versions');
$assert($requestXPath->query('//*[@id="checador-comodines-create-modal"]//div[contains(@class,"modal-header") and contains(@class,"bg-primary") and contains(@class,"text-white")]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-create-modal"]//button[contains(@class,"btn-close-white")]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-create-modal"]//button[@data-bs-dismiss="modal" and contains(@class,"bg-danger-subtle") and contains(@class,"text-danger")]')->length === 1
    && $requestXPath->query('//*[@id="checador-comodines-create-modal"]//button[@data-checador-comodines-submit and contains(@class,"btn-success")]')->length === 1,
    'new coverage modal follows Portal3 blue header, close control, and institutional footer button styles');
$assert($toolbar instanceof DOMElement
    && $requestXPath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " dropdown ") and contains(concat(" ", normalize-space(@class), " "), " d-inline-block ")]', $toolbar)->length === 1
    && $requestXPath->query('.//button[@data-bs-toggle="dropdown" and contains(@class,"btn-light") and contains(@class,"dropdown-toggle") and contains(@class,"text-dark")]', $toolbar)->length === 1
    && $requestXPath->query('.//button[@data-bs-toggle="dropdown"]//i[contains(@class,"ti-dots-vertical") and contains(@class,"fs-4")]', $toolbar)->length === 1
    && $requestXPath->query('.//button[@data-bs-target="#checador-comodines-search-modal"]//i[contains(@class,"ti-search") and contains(@class,"me-1")]', $toolbar)->length === 1
    && $requestXPath->query('.//button[@data-bs-target="#checador-comodines-create-modal"]', $toolbar)->length === 1
    && $requestXPath->query('.//ul[contains(@class,"dropdown-menu-end")]//button[contains(@class,"dropdown-item") and contains(@class,"pointer")]', $toolbar)->length === 2
    && $requestXPath->query('.//button[@data-bs-toggle="dropdown"]//text()[normalize-space()]', $toolbar)->length === 0
    && $requestXPath->query('following-sibling::*[1][self::section and @aria-labelledby="checador-comodines-coverage-heading"]', $toolbar)->length === 1,
    'one right-aligned institutional dropdown contains search and authorized create actions before the registered-coverages card');
$assert($requestXPath->query('//*[@data-bs-target="#checador-comodines-create-modal"]')->length === 1,
    'the create dropdown action appears only when server-side capability permits it');

$emptyHtml = $render(['coverageRows' => [], 'roles' => []]);
$assert(str_contains($emptyHtml, 'Aún no hay coberturas registradas')
    && !str_contains($emptyHtml, 'La consulta se completó y no devolvió')
    && !str_contains($emptyHtml, 'Programaciones registradas'),
    'empty canonical state is concise and uses the requested wording');
$unavailableHtml = $render(['coverageReadState' => 'unavailable', 'coverageReadMessage' => 'No fue posible consultar las coberturas canónicas en este momento.']);
$assert(str_contains($unavailableHtml, 'No fue posible consultar las coberturas canónicas en este momento.')
    && !str_contains($unavailableHtml, 'No se pudo resolver de forma única'),
    'a canonical read error remains visible without exposing technical diagnostics');
libxml_clear_errors();
libxml_use_internal_errors($previous);

printf("CHECADOR_V2_COMODINES_VIEW_RENDER: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
