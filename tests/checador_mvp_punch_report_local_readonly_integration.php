<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\ChecadorRawPunchReadService;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

$_ENV['LOG_PATH'] = sys_get_temp_dir() . '/portal3-checador-local-readonly-' . getmypid() . '.log';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();
$connection = Capsule::connection();
$config = $connection->getConfig();
$environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
$host = strtolower(trim((string) ($config['host'] ?? '')));
$database = (string) ($config['database'] ?? '');
$port = (int) ($config['port'] ?? 3306);

if (!in_array($environment, ['dev', 'development', 'local'], true)
    || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || $database !== 'bd_admongas'
    || $port !== 3306) {
    fwrite(STDERR, "BLOCKED: target is not the approved local development database.\n");
    exit(2);
}

$identity = $connection->selectOne('SELECT DATABASE() AS schema_name, @@hostname AS server_hostname, @@port AS server_port');
if (($identity->schema_name ?? null) !== 'bd_admongas'
    || strtolower((string) ($identity->server_hostname ?? '')) !== 'fedora'
    || (int) ($identity->server_port ?? 0) !== 3306) {
    fwrite(STDERR, "BLOCKED: server identity is not the approved Fedora local instance.\n");
    exit(2);
}

$passes = 0;
$fails = 0;
ob_start();
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$label}\n";
        $passes++;
    } else {
        echo "FAIL {$label}\n";
        $fails++;
    }
};

$reader = new ChecadorRawPunchReadService();
$fromUtc = '2026-02-01 00:00:00';
$toUtc = '2026-03-01 00:00:00';
$pageOne = $reader->readPage(1, $fromUtc, $toUtc, null, 1, 2, 'asc');
$assert(($pageOne['ok'] ?? false) === true, 'real SELECT succeeds for approved local station and UTC interval');
$events = $pageOne['events'] ?? [];
$assert(count($events) === 2 && ($pageOne['pagination']['total_records'] ?? 0) >= 3, 'real rows are paginated without loading the complete result into one page');
$assert(($pageOne['pagination']['total_pages'] ?? 0) >= 2 && ($pageOne['pagination']['current_page'] ?? 0) === 1, 'real query returns first-page metadata');
$assert($events !== [] && array_reduce($events, static fn(bool $ok, array $event): bool => $ok
    && (int) ($event['id_estacion_resuelta'] ?? 0) === 1
    && ($event['ocurrido_at_utc'] ?? null) === null
    && ($event['id_personal_resuelto'] ?? null) === null
    && ($event['integrity_status'] ?? '') === 'NOT_VERIFIED_BY_PORTAL3'
    && ($event['occurrence_status'] ?? '') === 'OCCURRENCE_TIME_UNRESOLVED'
    && (string) ($event['recibido_at_utc'] ?? '') >= $fromUtc
    && (string) ($event['recibido_at_utc'] ?? '') < $toUtc, true), 'real station-1 rows preserve NULL identity/occurrence, receipt UTC, and unverified integrity');

$pageTwo = $reader->readPage(1, $fromUtc, $toUtc, null, 2, 2, 'asc');
$assert(($pageTwo['ok'] ?? false) === true
    && ($pageTwo['pagination']['current_page'] ?? 0) === 2
    && count($pageTwo['events'] ?? []) >= 1, 'real second SQL page is independently readable');
$pageOneIds = array_map(static fn(array $event): int => (int) $event['id'], $pageOne['events'] ?? []);
$pageTwoIds = array_map(static fn(array $event): int => (int) $event['id'], $pageTwo['events'] ?? []);
$assert(array_intersect($pageOneIds, $pageTwoIds) === [], 'ordered real pages do not repeat raw event IDs');

$canonicalFilter = $reader->readPage(1, $fromUtc, $toUtc, 558, 1, 50, 'asc');
$assert(($canonicalFilter['ok'] ?? false) === true && ($canonicalFilter['events'] ?? null) === [], 'canonical worker filter does not match unresolved identity via any external reference');

$dataTablePage = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, '', 1, 'asc');
$displayRows = $dataTablePage['events'] ?? [];
$assert(($dataTablePage['ok'] ?? false) === true
    && count($displayRows) === 3
    && ($dataTablePage['records_total'] ?? 0) === 3
    && ($dataTablePage['records_filtered'] ?? 0) === 3, 'DataTables server-side query returns the three real station-1 rows with scoped counts');
$dataTableOffsetPage = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 2, 10, '', 1, 'asc');
$assert(($dataTableOffsetPage['ok'] ?? false) === true
    && count($dataTableOffsetPage['events'] ?? []) === 1
    && ($dataTableOffsetPage['records_filtered'] ?? 0) === 3
    && (int) ($dataTableOffsetPage['events'][0]['id'] ?? 0) === (int) ($displayRows[2]['id'] ?? -1), 'DataTables start/length offsets return the matching SQL page without loading the rest of the range');
$dataTableWorkerFilter = $reader->readDataTablePage(1, $fromUtc, $toUtc, 558, 0, 10, '', 1, 'asc');
$assert(($dataTableWorkerFilter['ok'] ?? false) === true
    && ($dataTableWorkerFilter['records_total'] ?? -1) === 0
    && ($dataTableWorkerFilter['events'] ?? null) === [], 'DataTables canonical worker filter does not widen identity matches');
$unresolvedSearch = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, 'Colaborador no identificado', 1, 'asc');
$assert(($unresolvedSearch['ok'] ?? false) === true
    && ($unresolvedSearch['records_filtered'] ?? 0) === 3
    && count($unresolvedSearch['events'] ?? []) === 3, 'global search matches the Spanish collaborator label against the authorized SQL set');
$eventSearch = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, (string) ($displayRows[0]['event_uuid'] ?? ''), 1, 'asc');
$assert(($eventSearch['ok'] ?? false) === true
    && ($eventSearch['records_filtered'] ?? 0) >= 1
    && count($eventSearch['events'] ?? []) === ($eventSearch['records_filtered'] ?? -1), 'global search filters event identifiers in SQL before returning the server-side page');
$descendingPage = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, '', 1, 'desc');
$assert(($descendingPage['ok'] ?? false) === true
    && (string) ($descendingPage['events'][0]['recibido_at_utc'] ?? '') >= (string) ($descendingPage['events'][count($descendingPage['events']) - 1]['recibido_at_utc'] ?? ''), 'DataTables ordering is applied by SQL over the complete authorized result');

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php';
$renderedReport = (static function (array $data, string $path): string {
    extract($data, EXTR_SKIP);
    ob_start();
    require $path;
    return (string) ob_get_clean();
})([
    'events' => $displayRows,
    'pagination' => $displayPage['pagination'] ?? [],
    'state' => 'success',
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Interlomas',
    'fromDate' => '2026-02-01',
    'toDate' => '2026-02-28',
    'workerId' => '',
    'selectedPerPage' => 50,
    'sort' => 'asc',
    'tableSearch' => '',
    'tableOrderColumn' => 1,
    'canDownload' => true,
], $viewPath);
$assert(substr_count($renderedReport, 'class="modal fade"') === 2
    && str_contains($renderedReport, 'id="punch-report-filter-modal"')
    && str_contains($renderedReport, 'checador-punch-detail-modal')
    && str_contains($renderedReport, 'id="checador-punch-table"'), 'rendered report has separate filter and reusable accessible detail modals plus one DataTables table');
$assert(str_contains($renderedReport, 'Interlomas')
    && str_contains($renderedReport, 'Fecha y hora de recepción (UTC)')
    && str_contains($renderedReport, 'Hora original informada por el dispositivo')
    && str_contains($renderedReport, 'Dispositivo')
    && str_contains($renderedReport, '/checador/reporte/data'), 'rendered report exposes the correct DataTables endpoint and detail fields');
$labels = array_map(static fn(array $event): array => [
    \App\Services\ChecadorPresentationFormatter::collaboratorLabel($event['id_personal_resuelto'] ?? null),
    \App\Services\ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null),
    \App\Services\ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null),
], $displayRows);
$labelText = json_encode($labels, JSON_UNESCAPED_UNICODE);
$assert(is_string($labelText)
    && str_contains($labelText, 'Colaborador no identificado')
    && !str_contains($labelText, 'RECEIVED')
    && !str_contains($labelText, 'BIOMETRIC'), 'real SQL rows resolve to Spanish presentation labels without raw status or method codes');
$assert(!str_contains($renderedReport, 'RECEIVED')
    && !str_contains($renderedReport, 'PENDING_MATCH')
    && !str_contains($renderedReport, 'BIOMETRIC')
    && !str_contains($renderedReport, 'WEB_CAPTURE')
    && !str_contains($renderedReport, 'PORTAL_TELEGRAM_VERIFIED')
    && !str_contains($renderedReport, 'id_personal_resuelto')
    && !str_contains($renderedReport, 'id_raw_punch')
    && str_contains($renderedReport, 'Estado de la marcación'), 'real rows show Spanish status/method labels instead of raw codes');
$assert(str_contains($renderedReport, 'value="2026-02-01"')
    && str_contains($renderedReport, 'value="2026-02-28"')
    && str_contains($renderedReport, 'name="export" value="csv"'), 'rendered filters retain the selected UTC window and permission-gated CSV action');
$dom = new DOMDocument();
$previousLibxmlState = libxml_use_internal_errors(true);
$parsed = $dom->loadHTML($renderedReport);
libxml_clear_errors();
libxml_use_internal_errors($previousLibxmlState);
$modalsOutsideTableBody = $parsed;
foreach ($dom->getElementsByTagName('div') as $div) {
    if (!$div->hasAttribute('class') || !str_contains(' ' . $div->getAttribute('class') . ' ', ' modal ')) {
        continue;
    }
    for ($parent = $div->parentNode; $parent !== null; $parent = $parent->parentNode) {
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'tbody') {
            $modalsOutsideTableBody = false;
            break 2;
        }
    }
}
$assert($parsed && $modalsOutsideTableBody, 'rendered table and detail modals parse with modals outside table row groups');
foreach ($displayRows as $displayRow) {
    $assert((string) ($displayRow['recibido_at_utc'] ?? '') !== ''
        && str_contains($renderedReport, 'Fecha y hora de recepción (UTC)'), 'real receipt timestamps are supplied to the DataTables JSON projection and UTC column');
}

$csvMethod = new ReflectionMethod(App\Controllers\ChecadorController::class, 'punchReportCsvContent');
$csv = $csvMethod->invoke(null, $events, 1, 'Desarrollo estación 1');
$assert(is_string($csv)
    && str_starts_with($csv, "\xEF\xBB\xBF")
    && str_contains($csv, 'Identificador del registro')
    && str_contains($csv, 'Fecha y hora de marcación (UTC)')
    && str_contains($csv, 'Integridad no verificada por Portal3'), 'CSV formatter exports actual DB rows with UTC and explicit unverified-integrity labels');
$assert(is_string($csv)
    && !str_contains($csv, 'integrity_seal')
    && !str_contains($csv, 'integrity_key_id')
    && !str_contains($csv, 'metadata_json'), 'actual CSV projection excludes seals, key IDs, and metadata JSON');
$assert(is_string($csv)
    && !str_contains($csv, 'processing_status')
    && !str_contains($csv, 'metodo_captura')
    && !str_contains($csv, 'id_personal_resuelto')
    && !str_contains($csv, 'id_raw_punch')
    && !str_contains($csv, 'RECEIVED')
    && !str_contains($csv, 'PENDING_MATCH')
    && !str_contains($csv, 'BIOMETRIC')
    && !str_contains($csv, 'WEB_CAPTURE'), 'real CSV headers and status/method values are presented in Spanish');

$streamMethod = new ReflectionMethod(App\Controllers\ChecadorController::class, 'streamPunchReportCsv');
ob_start();
$streamMethod->invoke(null, $reader, $pageOne, 1, 'Desarrollo estación 1', $fromUtc, $toUtc, null, 2, 'asc');
$streamedCsv = ob_get_clean();
$assert(is_string($streamedCsv)
    && str_contains($streamedCsv, '"Estado de la descarga",Completa')
    && substr_count($streamedCsv, 'Integridad no verificada por Portal3') === 3, 'real paged CSV stream covers every matching local row and declares completion');

$filteredStreamMethod = new ReflectionMethod(App\Controllers\ChecadorController::class, 'streamPunchReportDataTableCsv');
ob_start();
$filteredStreamMethod->invoke(null, $reader, $eventSearch, 1, 'Desarrollo estación 1', $fromUtc, $toUtc, null, (string) ($displayRows[0]['event_uuid'] ?? ''), 1, 'asc');
$filteredCsv = ob_get_clean();
$assert(is_string($filteredCsv)
    && str_contains($filteredCsv, '"Estado de la descarga",Completa')
    && str_contains($filteredCsv, '"Registros exportados",' . (int) ($eventSearch['records_filtered'] ?? 0)), 'DataTables global search is preserved by the complete backend CSV stream');

printf("CHECADOR_MVP_LOCAL_READONLY_INTEGRATION: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
$testOutput = ob_get_clean();
if (is_string($testOutput)) {
    echo $testOutput;
}
@unlink($_ENV['LOG_PATH']);
exit($fails === 0 ? 0 : 1);
