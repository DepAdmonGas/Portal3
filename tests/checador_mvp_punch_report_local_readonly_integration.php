<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\ChecadorAttendanceRosterService;
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
$nameSearch = $reader->searchAttributedWorkers(1, 'QA', 1);
$assert(($nameSearch['ok'] ?? false) === true
    && count($nameSearch['workers'] ?? []) > 0
    && array_reduce($nameSearch['workers'] ?? [], static fn(bool $valid, array $worker): bool => $valid
        && (int) ($worker['id'] ?? 0) > 0 && trim((string) ($worker['text'] ?? '')) !== '', true),
    'real name search returns only named canonical workers with persisted Interlomas punch attribution');
$qa1005 = $reader->findAttributedWorker(1, 1005);
$assert(($qa1005['ok'] ?? false) === true && (int) ($qa1005['worker']['id'] ?? 0) === 1005,
    'persisted QA raw-punch identity 1005 resolves through the exact op_rh_personal primary-key mapping');
if (($nameSearch['workers'] ?? []) !== []) {
    $sampleWorker = $nameSearch['workers'][0];
    $sampleName = (string) $sampleWorker['text'];
    $nameParts = preg_split('/\s+/u', trim($sampleName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $firstNameSearch = $reader->searchAttributedWorkers(1, (string) ($nameParts[0] ?? 'QA'), 1);
    $surnameSearch = $reader->searchAttributedWorkers(1, (string) ($nameParts[array_key_last($nameParts)] ?? 'QA'), 1);
    $containsWorker = static fn(array $result): bool => array_reduce($result['workers'] ?? [], static fn(bool $found, array $worker): bool => $found
        || (int) ($worker['id'] ?? 0) === (int) $sampleWorker['id'], false);
    $assert(($firstNameSearch['ok'] ?? false) === true && $containsWorker($firstNameSearch), 'real first-name search matches the selected persisted worker');
    $assert(($surnameSearch['ok'] ?? false) === true && $containsWorker($surnameSearch), 'real surname search matches the selected persisted worker');
    $selectedWorker = $reader->findAttributedWorker(1, (int) $sampleWorker['id']);
    $assert(($selectedWorker['ok'] ?? false) === true
        && (int) ($selectedWorker['worker']['id'] ?? 0) === (int) $sampleWorker['id']
        && ($selectedWorker['worker']['text'] ?? '') === $sampleName, 'selected employee readback preserves the same real name and canonical punch ID');
}
$assert(($reader->searchAttributedWorkers(1, 'Q')['error']['kind'] ?? '') === 'bad_request', 'server search rejects terms shorter than Select2 required minimum');
$rosterService = new ChecadorAttendanceRosterService();
$applicationTimezone = trim((string) ($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: ''));
if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($applicationTimezone)) {
    $applicationTimezone = 'UTC';
}
$currentRosterDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->setTimezone(new DateTimeZone($applicationTimezone))->format('Y-m-d');
foreach ([9 => 'Autolavado', 2 => 'Palo Solo', 1 => 'Interlomas'] as $localityId => $localityLabel) {
    $roster = $rosterService->workersForAttendanceFilter((int) $localityId, $currentRosterDate, null, false);
    $rosterWorkers = $roster['workers'] ?? [];
    $rosterIds = array_values(array_unique(array_map(static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0), $rosterWorkers)));
    $rosterIds = array_values(array_filter($rosterIds, static fn(int $workerId): bool => $workerId > 0));
    $assert(in_array($roster['status'] ?? '', ['ready', 'current_roster_only'], true), 'real ' . $localityLabel . ' active roster is available to M02 from its authorized source');

    $initialPage = $reader->searchReportWorkers((int) $localityId, $rosterIds, '', 1);
    $allVisible = [];
    $allVisibleIds = [];
    for ($page = 1; ($initialPage['ok'] ?? false) && $page <= 1000; $page++) {
        $pageResult = $page === 1 ? $initialPage : $reader->searchReportWorkers((int) $localityId, $rosterIds, '', $page);
        if (!($pageResult['ok'] ?? false)) break;
        foreach ($pageResult['workers'] ?? [] as $worker) {
            $workerId = (int) ($worker['id'] ?? 0);
            $allVisibleIds[] = $workerId;
            $allVisible[$workerId] = $worker;
        }
        if (!($pageResult['more'] ?? false)) break;
    }
    $allVisibleIdSet = array_fill_keys(array_keys($allVisible), true);
    $assert(($initialPage['ok'] ?? false) === true
        && count(array_filter($rosterIds, static fn(int $workerId): bool => isset($allVisibleIdSet[$workerId]))) === count($rosterIds)
        && count(array_unique($allVisibleIds)) === count($allVisibleIds), 'empty-term initial catalog includes all ' . count($rosterIds) . ' real ' . $localityLabel . ' roster workers without duplicates');

    $noPunchWorkerId = null;
    foreach ($rosterWorkers as $worker) {
        $candidateId = (int) ($worker['worker_id'] ?? 0);
        if ($candidateId > 0 && ($reader->findAttributedWorker((int) $localityId, $candidateId)['ok'] ?? false) !== true) {
            $noPunchWorkerId = $candidateId;
            break;
        }
    }
    if ($noPunchWorkerId !== null) {
        $noPunchWorker = $reader->findReportWorker((int) $localityId, $rosterIds, $noPunchWorkerId);
        $assert(($noPunchWorker['ok'] ?? false) === true
            && (int) ($noPunchWorker['worker']['id'] ?? 0) === $noPunchWorkerId,
            'real ' . $localityLabel . ' roster worker without locality punch history remains selectable by canonical personnel ID');
    } else {
        $assert(true, 'real ' . $localityLabel . ' roster has no un-attributed identity candidate requiring fallback validation');
    }

    if ($rosterWorkers !== []) {
        $sample = $rosterWorkers[0];
        $sampleId = (int) ($sample['worker_id'] ?? 0);
        $sampleName = trim((string) ($sample['full_name'] ?? ''));
        $parts = preg_split('/\s+/u', $sampleName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = (string) ($parts[0] ?? '');
        $surname = (string) ($parts[array_key_last($parts)] ?? '');
        $nameResult = $reader->searchReportWorkers((int) $localityId, $rosterIds, $firstName, 1);
        $surnameResult = $reader->searchReportWorkers((int) $localityId, $rosterIds, $surname, 1);
        $singleCharacterSearch = $reader->searchReportWorkers((int) $localityId, $rosterIds, mb_substr($sampleName, 0, 1, 'UTF-8'), 1);
        $containsSample = static fn(array $result): bool => array_reduce($result['workers'] ?? [], static fn(bool $found, array $worker): bool => $found
            || (int) ($worker['id'] ?? 0) === $sampleId, false);
        $assert(($nameResult['ok'] ?? false) === true && $containsSample($nameResult), 'real ' . $localityLabel . ' search matches an active-roster name without a minimum-length gate');
        $assert(($surnameResult['ok'] ?? false) === true && $containsSample($surnameResult), 'real ' . $localityLabel . ' search matches a surname');
        $assert(($singleCharacterSearch['ok'] ?? false) === true, 'real ' . $localityLabel . ' search accepts a partial single-character query');
        $assert(($reader->findReportWorker((int) $localityId, $rosterIds, $sampleId)['worker']['text'] ?? '') === $sampleName,
            'real ' . $localityLabel . ' selected-value readback preserves its canonical op_rh_personal.id and name');
    }
}
foreach ([2 => 'Palo Solo', 9 => 'Autolavado'] as $localityId => $localityLabel) {
    $otherLocalitySearch = $reader->searchAttributedWorkers((int) $localityId, 'QA', 1);
    $scopeIntact = ($otherLocalitySearch['ok'] ?? false) === true
        && array_reduce($otherLocalitySearch['workers'] ?? [], static function (bool $valid, array $worker) use ($reader, $localityId): bool {
            $readback = $reader->findAttributedWorker((int) $localityId, (int) ($worker['id'] ?? 0));
            return $valid && ($readback['ok'] ?? false) === true
                && (int) ($readback['worker']['id'] ?? 0) === (int) ($worker['id'] ?? 0)
                && ($readback['worker']['text'] ?? '') === ($worker['text'] ?? null);
        }, true);
    $assert($scopeIntact, 'real ' . $localityLabel . ' search/readback remains restricted to canonical locality ' . $localityId);
}
$assert(($reader->findAttributedWorker(2, 1005)['error']['kind'] ?? '') === 'worker_scope_mismatch'
    && ($reader->findAttributedWorker(9, 1005)['error']['kind'] ?? '') === 'worker_scope_mismatch', 'Interlomas QA employee 1005 cannot be restored as a Palo Solo or Autolavado report selection');
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
$sampleEvent = $displayRows[0] ?? [];
$sampleMethod = (string) ($sampleEvent['metodo_captura'] ?? '');
$sampleStatus = (string) ($sampleEvent['processing_status'] ?? '');
$methodFiltered = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, '', 1, 'asc', '', $sampleMethod, '');
$statusFiltered = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, '', 1, 'asc', '', '', $sampleStatus);
$combinedFiltered = $reader->readDataTablePage(1, $fromUtc, $toUtc, null, 0, 10, '', 1, 'asc', '', $sampleMethod, $sampleStatus);
$assert(($methodFiltered['ok'] ?? false) === true
    && count($methodFiltered['events'] ?? []) > 0
    && array_reduce($methodFiltered['events'], static fn(bool $valid, array $event): bool => $valid
        && ($event['metodo_captura'] ?? null) === $sampleMethod, true), 'real SQL capture-method filter returns only persisted matching values');
$assert(($statusFiltered['ok'] ?? false) === true
    && count($statusFiltered['events'] ?? []) > 0
    && array_reduce($statusFiltered['events'], static fn(bool $valid, array $event): bool => $valid
        && ($event['processing_status'] ?? null) === $sampleStatus, true), 'real SQL processing-status filter returns only persisted matching values');
$assert(($combinedFiltered['ok'] ?? false) === true
    && count($combinedFiltered['events'] ?? []) > 0
    && array_reduce($combinedFiltered['events'], static fn(bool $valid, array $event): bool => $valid
        && ($event['metodo_captura'] ?? null) === $sampleMethod
        && ($event['processing_status'] ?? null) === $sampleStatus, true), 'real SQL combines capture-method and status filters without widening locality scope');
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

$qaPage = $reader->readDataTablePage(1, '2026-10-27 00:00:00', '2026-10-28 00:00:00', null, 0, 25, '', 1, 'asc');
$qaUuids = [
    '5af9caac-46f7-4e0a-a764-7b7597e36f51',
    '0a597c41-8260-45a5-ba1c-3598595e53c5',
    '3eb7038d-2cea-4650-af76-a82108a2e114',
];
$qaEvents = $qaPage['events'] ?? [];
$qaCounts = [];
foreach ($qaEvents as $event) {
    $eventUuid = (string) ($event['event_uuid'] ?? '');
    $qaCounts[$eventUuid] = ($qaCounts[$eventUuid] ?? 0) + 1;
}
$qaFound = array_values(array_filter($qaUuids, static fn(string $uuid): bool => ($qaCounts[$uuid] ?? 0) === 1));
$qaWorkers = array_values(array_unique(array_map(static fn(array $event): int => (int) ($event['id_personal_resuelto'] ?? 0), $qaEvents)));
$qaStations = array_values(array_unique(array_map(static fn(array $event): int => (int) ($event['id_estacion_resuelta'] ?? 0), $qaEvents)));
$assert(($qaPage['ok'] ?? false) === true
    && ($qaPage['records_total'] ?? 0) === 3
    && count($qaFound) === 3
    && (array_sum($qaCounts) - count($qaCounts)) === 0, 'three persisted QA UUIDs appear exactly once from the local raw SQL table');
$assert($qaWorkers === [1002, 1003] && $qaStations === [1]
    && array_reduce($qaEvents, static fn(bool $valid, array $event): bool => $valid
        && ($event['processing_status'] ?? null) === 'RECEIVED'
        && ($event['integrity_status'] ?? null) === 'NOT_VERIFIED_BY_PORTAL3', true), 'QA SQL rows retain worker/station mappings, receipt state, and unverified integrity');

$oct28From = '2026-10-28 00:00:00';
$oct29From = '2026-10-29 00:00:00';
$oct28All = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 0, 25, '', 1, 'asc', 'Interlomas');
$oct28ById = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 0, 25, '1005', 1, 'asc', 'Interlomas');
$oct28ByVisibleName = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 0, 25, 'QA - Colaborador 02', 1, 'asc', 'Interlomas');
$oct28ByStation = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 0, 25, 'Interlomas', 1, 'asc', 'Interlomas');
$assert(($oct28All['ok'] ?? false) === true && ($oct28All['records_total'] ?? -1) === 11
    && ($oct28All['records_filtered'] ?? -1) === 11, 'October 28 local raw report remains an eleven-row station-scoped UTC query');
$assert(($oct28ById['ok'] ?? false) === true && ($oct28ById['records_filtered'] ?? -1) === 4
    && count($oct28ById['events'] ?? []) === 4, 'DataTables global search still matches worker ID 1005 in SQL');
$assert(($oct28ByVisibleName['ok'] ?? false) === true && ($oct28ByVisibleName['records_filtered'] ?? -1) === 4
    && count($oct28ByVisibleName['events'] ?? []) === 4, 'DataTables global search matches the collaborator label rendered from op_rh_personal');
$assert(($oct28ByStation['ok'] ?? false) === true && ($oct28ByStation['records_filtered'] ?? -1) === 11
    && count($oct28ByStation['events'] ?? []) === 11, 'DataTables global search matches only the server-authorized Interlomas label');
$oct28FirstPage = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 0, 10, 'Interlomas', 1, 'asc', 'Interlomas');
$oct28SecondPage = $reader->readDataTablePage(1, $oct28From, $oct29From, null, 10, 10, 'Interlomas', 1, 'asc', 'Interlomas');
$assert(($oct28FirstPage['ok'] ?? false) === true && ($oct28SecondPage['ok'] ?? false) === true
    && ($oct28FirstPage['records_filtered'] ?? -1) === 11 && ($oct28SecondPage['records_filtered'] ?? -1) === 11
    && count($oct28FirstPage['events'] ?? []) === 10 && count($oct28SecondPage['events'] ?? []) === 1,
    'station search preserves SQL filtered counts and independent DataTables pagination');
$oct29All = $reader->readDataTablePage(1, $oct29From, '2026-10-30 00:00:00', null, 0, 25, '', 1, 'asc', 'Interlomas');
$assert(($oct29All['ok'] ?? false) === true && ($oct29All['records_total'] ?? -1) === 1
    && ($oct29All['records_filtered'] ?? -1) === 1, 'October 29 UTC raw report remains separate from the QA daily projection');

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
    'captureMethod' => '',
    'processingStatus' => '',
    'captureMethodOptions' => ['BIOMETRIC' => 'Registro biométrico'],
    'processingStatusOptions' => ['RECEIVED' => 'Marcación recibida'],
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
$visibleReportText = preg_replace('/\s+/u', ' ', strip_tags($renderedReport)) ?: '';
$assert(!str_contains($visibleReportText, 'RECEIVED')
    && !str_contains($visibleReportText, 'PENDING_MATCH')
    && !str_contains($visibleReportText, 'BIOMETRIC')
    && !str_contains($visibleReportText, 'WEB_CAPTURE')
    && !str_contains($visibleReportText, 'PORTAL_TELEGRAM_VERIFIED')
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

$streamMethod = new ReflectionMethod(App\Controllers\ChecadorController::class, 'streamPunchReportDataTableCsv');
ob_start();
$streamMethod->invoke(null, $reader, $dataTablePage, 1, 'Desarrollo estación 1', $fromUtc, $toUtc, null, '', 1, 'asc');
$streamedCsv = ob_get_clean();
$assert(is_string($streamedCsv)
    && str_contains($streamedCsv, '"Estado de la descarga",Completa')
    && substr_count($streamedCsv, 'Integridad no verificada por Portal3') === count($displayRows), 'real CSV stream covers every matching local row and declares completion');

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
