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
    'coverageWriterEnabled' => true,
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
$assert($toolbar instanceof DOMElement
    && $requestXPath->query('.//button[@data-bs-target="#checador-comodines-search-modal"]', $toolbar)->length === 1
    && $requestXPath->query('.//button[@data-bs-target="#checador-comodines-create-modal"]', $toolbar)->length === 1
    && $requestXPath->query('following-sibling::*[1][self::section and @aria-labelledby="checador-comodines-coverage-heading"]', $toolbar)->length === 1,
    'both action buttons are right-aligned immediately before the registered-coverages card');
$assert($requestXPath->query('//*[@data-bs-target="#checador-comodines-create-modal"]')->length === 1,
    'the create action appears only when server-side capability permits it');

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
