<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Controllers\ChecadorController;
use App\Services\ChecadorJourneySqlReadService;
use Dotenv\Dotenv;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$base = dirname(__DIR__);
$serviceSource = file_get_contents($base . '/app/Services/ChecadorJourneySqlReadService.php') ?: '';
$controllerSource = file_get_contents($base . '/app/Controllers/ChecadorController.php') ?: '';
$accessSource = file_get_contents($base . '/app/Services/ChecadorAccessService.php') ?: '';
$viewCoreSource = file_get_contents($base . '/app/Core/View.php') ?: '';
$moduleStationServiceSource = file_get_contents($base . '/app/Services/ModuleStationService.php') ?: '';
$moduleStationJsSource = file_get_contents($base . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js') ?: '';
$viewSource = file_get_contents($base . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php') ?: '';
$m06ViewSource = file_get_contents($base . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php') ?: '';
$jsSource = file_get_contents($base . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.journeys.datatable.init.js') ?: '';
$m06JsSource = file_get_contents($base . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.datatable.init.js') ?: '';
$journeyMethod = preg_match('/public function journeys\(\): void([\s\S]*?)(?=\n    public function incidences\()/m', $controllerSource, $match) === 1 ? $match[1] : '';
$rosterMethod = preg_match('/private static function journeyWorkerRosterOptions\([\s\S]*?\n    }(?=\n\n    public function incidences\()/m', $controllerSource, $rosterMatch) === 1 ? $rosterMatch[0] : '';

$assert(str_contains($serviceSource, "Capsule::connection('default')") && !preg_match('/Guzzle|curl_|api\/v2|Http::/i', $serviceSource), 'C01 reader uses only the Portal3 default SQL connection, without API HTTP');
$assert(!preg_match('/->(?:insert|update|delete|upsert|statement|unprepared)\s*\(/i', $serviceSource), 'C01 service contains no SQL write or arbitrary statement operation');
$assert(str_contains($serviceSource, "'id_dispositivo' => \$raw['id_dispositivo'] === null ? null : (int) \$raw['id_dispositivo']"), 'nullable device IDs remain null instead of becoming a false zero');
$assert(str_contains($journeyMethod, 'ChecadorJourneySqlReadService') && !str_contains($journeyMethod, 'ChecadorDailyReadService') && !str_contains($journeyMethod, 'evaluateJourney('), 'journey route is isolated from the legacy API journey evaluator');
$assert(str_contains($journeyMethod, 'ChecadorAccessService::authorize(null)')
    && str_contains($journeyMethod, 'ChecadorAccessService::authorize($workplaceId)')
    && str_contains($journeyMethod, 'in_array($workplaceId, $authorizedIds, true)'), 'route revalidates the selected locality against the active session ACL');
$assert(str_contains($journeyMethod, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY")
    && str_contains($journeyMethod, "'moduleStationForceSelector' => \$isOperationsDirector")
    && str_contains($viewCoreSource, "\$viewData['moduleStationForceSelector']")
    && str_contains($moduleStationServiceSource, '$forceSelector || count($stations) + count($depts) > 1'),
    'Jornadas renders the institutional Checador selector for Dirección, including a single authorized choice');
$assert(str_contains($accessSource, "'position_id' => (int) (\$user->id_puesto ?? 0)")
    && str_contains($journeyMethod, "\$isOperationsDirector = (int) (\$access['position_id'] ?? 0) === 13")
    && str_contains($journeyMethod, "\$hasSavedContext = !empty(\$savedChecadorContext['id_depto']) || !empty(\$savedChecadorContext['id_estacion'])")
    && str_contains($journeyMethod, 'if ($isOperationsDirector && !$hasSavedContext)')
    && str_contains($journeyMethod, "!\$isOperationsDirector && isset(\$workplaces[0]['id'])")
    && str_contains($journeyMethod, "'selection_required'")
    && str_contains($journeyMethod, 'journeyWorkerRosterOptions($workplaceId')
    && str_contains($journeyMethod, 'Selecciona una estación o departamento autorizado'),
    'Director is not silently assigned the first locality and no roster/journey query runs before selection');
$assert(str_contains($moduleStationJsSource, "var journeysPath = checadorPath + '/jornadas'")
    && str_contains($moduleStationJsSource, 'currentPath !== journeysPath')
    && str_contains($moduleStationJsSource, "url.searchParams.set('workplace_id', nextStationId)")
    && str_contains($moduleStationJsSource, "['start_date', 'end_date', 'worker_id', 'page']"),
    'shared Checador selector navigation persists the selected journey scope and clears dependent filters');
$assert(str_contains($serviceSource, "where('j.id_estacion', \$workplaceId)")
    && str_contains($serviceSource, 'WorkerWorkplaceAssignmentResolver')
    && str_contains($serviceSource, 'sourceAssignmentMatches'), 'SQL scope and historical membership/source assignment checks are present');
$assert(str_contains($viewSource, 'name="start_date"') && str_contains($viewSource, 'name="end_date"')
    && str_contains($viewSource, 'name="worker_id"') && str_contains($viewSource, 'journeyPagination'), 'server filters and SQL pagination are wired into the existing view');
$assert(str_contains($serviceSource, "where('j.id_personal', \$workerId)")
    && str_contains($journeyMethod, 'elseif ($startDate !== null && $endDate !== null)'), 'worker and date filters are parameterized and no journey read runs without both dates');
$assert(str_contains($rosterMethod, 'workersForAttendanceFilter') && str_contains($rosterMethod, 'timezoneForDate')
    && str_contains($rosterMethod, 'historical_versioned_membership') && str_contains($rosterMethod, 'isCurrentWorkday')
    && str_contains($viewSource, 'employee_number'), 'worker selector uses the canonical roster independently and respects historical date coverage');
$assert(str_contains($jsSource, "dropdownParent: $(modal)") && str_contains($jsSource, "language: 'es'")
    && str_contains($moduleStationJsSource, 'ModuleStationSelector') && str_contains($jsSource, 'rangeDays <= 31')
    && str_contains($viewSource, 'No existen jornadas procesadas para los filtros seleccionados.'),
    'Select2 stays modal-bound, client date limits and empty journey state remain clear');
$assert(str_contains($viewSource, 'class="select2-modal-field w-100"') && str_contains($viewSource, 'class="form-select w-100"')
    && str_contains($m06ViewSource, 'class="select2-modal-field w-100"') && str_contains($m06ViewSource, 'class="form-select w-100"')
    && str_contains($jsSource, "select2-container').css('width', '100%')")
    && str_contains($jsSource, 'shown.bs.modal') && str_contains($jsSource, 'hidden.bs.modal')
    && str_contains($m06JsSource, 'dropdownParent: $(modal)'), 'Journey Select2 uses M06 field markup, width, modal lifecycle and dropdown parent');
$assert(str_contains($viewSource, 'l2_status_persisted') && str_contains($viewSource, 'no verificado de forma independiente')
    && !str_contains($serviceSource, 'evaluateJourney('), 'L2 is presented only as persisted, not recalculated or independently verified');

$fixture = [
    'id' => 701,
    'worker_id' => 558,
    'worker_name' => 'Trabajadora C01',
    'journey_status' => 'CALCULATED',
    'pairing_current_status' => 'ready',
    'context' => ['id_personal' => 558, 'id_estacion' => 1, 'fecha_local' => '2026-11-19', 'timezone' => 'America/Mexico_City'],
    'schedule' => ['estado_jornada' => 'SCHEDULED', 'scheduled_start_utc' => '2026-11-19 14:00:00', 'scheduled_end_utc' => '2026-11-19 23:00:00', 'payload_segmentos_json' => '[{"ordinal":1}]'],
    'pairing' => ['id' => 801, 'revision_numero' => 2, 'version_algoritmo' => 'p1-test', 'estado_completitud_l2' => 'INCOMPLETE', 'estado_pareo' => 'PAIRED', 'segments' => [['ordinal' => 1, 'status' => 'EXACT_PAIR', 'entry_event_uuid' => 'event-c01-in', 'exit_event_uuid' => 'event-c01-out'], ['ordinal' => 3, 'status' => 'UNMATCHED', 'created_at_utc' => '2026-11-19 23:30:00']], 'associated_events' => []],
    'calculation' => ['id' => 901, 'revision_numero' => 3, 'version_algoritmo_calculo' => 'p2-test', 'estado_calculo' => 'CALCULATED', 'segundos_trabajados_totales' => 28800, 'segments' => [['ordinal_segmento' => 1, 'tipo_segmento' => 'WORK', 'estado_segmento' => 'CALCULATED', 'segundos_programados' => 28800, 'segundos_trabajados' => null, 'delta_llegada_segundos' => null, 'delta_salida_segundos' => null, 'segundos_candidatos_fuera_horario' => null]]],
    'pairing_history' => [['id' => 800, 'revision_numero' => 1, 'estado_pareo' => 'REVIEW_REQUIRED', 'version_algoritmo' => 'p1-old', 'creado_at_utc' => '2026-11-19 23:00:00', 'fingerprint_ejecucion' => 'p1-old-fp']],
    'calculation_history' => [['id' => 900, 'revision_numero' => 2, 'estado_calculo' => 'REVIEW_REQUIRED', 'version_algoritmo_calculo' => 'p2-old', 'creado_at_utc' => '2026-11-19 23:10:00', 'fingerprint_ejecucion' => 'p2-old-fp']],
    'provenance' => ['journey_created_at_utc' => '2026-11-19 22:00:00', 'l2_status_persisted' => 'INCOMPLETE'],
    'source_evidence' => ['level2_verification' => 'not_independently_verifiable'],
    'evaluated_raw_events' => [['pairing_role' => 'ENTRY', 'event_uuid' => 'event-c01-in', 'ocurrido_at_utc' => '2026-11-19 14:00:00', 'id_dispositivo' => 2, 'metodo_captura' => 'FINGERPRINT']],
];
$render = static function (array $vars) use ($base): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    include $base . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php';
    return (string) ob_get_clean();
};
$selectionRequiredRendered = $render([
    'state' => 'selection_required', 'stateMessage' => 'Selecciona una estación o departamento autorizado.',
    'journeys' => [], 'journeyErrors' => [], 'journeyPagination' => ['page' => 1, 'total_records' => 0, 'total_pages' => 0],
    'excludedUnverifiedCount' => 0, 'selectedWorkplaceId' => null, 'selectedWorkerId' => null,
    'startDate' => '', 'endDate' => '', 'workerSelectOptions' => [],
]);
$assert(str_contains($selectionRequiredRendered, 'Selecciona una estación o departamento autorizado.')
    && preg_match('/id="journey-options"[\s\S]*?<button[^>]*disabled[^>]*>/', $selectionRequiredRendered) === 1,
    'selection-required state is clear and search stays unavailable without an authorized selected locality');

$rendered = $render([
    'state' => 'success', 'stateMessage' => '', 'journeys' => [701 => $fixture], 'journeyErrors' => [],
    'journeyPagination' => ['page' => 1, 'total_records' => 1, 'total_pages' => 1], 'excludedUnverifiedCount' => 0,
    'selectedWorkplaceId' => 1, 'selectedWorkerId' => null, 'selectedDate' => '2026-11-19',
    'startDate' => '2026-11-19', 'endDate' => '2026-11-19',
    'workerSelectOptions' => [['worker_id' => 558, 'full_name' => 'Trabajadora C01', 'employee_number' => 'C01-558']],
]);
$tableEnd = strpos($rendered, '</table>');
$detailModal = strpos($rendered, 'id="journey-detail-701"');
$assert(str_contains($rendered, 'Trabajadora C01') && str_contains($rendered, 'Cálculo persistido')
    && str_contains($rendered, 'Trabajadora C01 · C01-558')
    && str_contains($rendered, 'Historial de revisiones P1/P2') && str_contains($rendered, 'INCOMPLETE')
    && str_contains($rendered, 'Segmentos P1 persistidos') && str_contains($rendered, 'segmentos adicionales persistidos')
    && str_contains($rendered, 'Segmentos P2 persistidos') && str_contains($rendered, 'No disponible')
    && $tableEnd !== false && $detailModal !== false && $detailModal > $tableEnd,
    'fixture rendering exposes the persisted detail/history modals after the DataTables table');
$emptyRendered = $render([
    'state' => 'success', 'stateMessage' => '', 'journeys' => [], 'journeyErrors' => [],
    'journeyPagination' => ['page' => 1, 'total_records' => 0, 'total_pages' => 1], 'excludedUnverifiedCount' => 0,
    'selectedWorkplaceId' => 1, 'selectedWorkerId' => null, 'startDate' => '2026-11-19', 'endDate' => '2026-11-19',
    'workerSelectOptions' => [['worker_id' => 558, 'full_name' => 'Trabajadora C01', 'employee_number' => 'C01-558']],
]);
$assert(str_contains($emptyRendered, 'No existen jornadas procesadas para los filtros seleccionados.')
    && str_contains($emptyRendered, 'Trabajadora C01 · C01-558')
    && !str_contains($emptyRendered, 'FALTA') && !str_contains($emptyRendered, 'cero horas'),
    'authorized roster remains available and empty journey results do not imply absence or zero hours');
$errorRendered = $render([
    'state' => 'error', 'stateMessage' => 'La lectura falló.', 'journeys' => [], 'journeyErrors' => [],
    'journeyPagination' => ['page' => 1, 'total_records' => 0, 'total_pages' => 0], 'excludedUnverifiedCount' => 0,
    'selectedWorkplaceId' => 1, 'selectedWorkerId' => null, 'startDate' => '2026-11-19', 'endDate' => '2026-11-19',
    'workerSelectOptions' => [],
]);
$assert(str_contains($errorRendered, 'La lectura falló.') && str_contains($errorRendered, 'id="journey-search-modal"'),
    'filter modal remains available after a query error so the user can correct or retry filters');

$currentPairing = ChecadorJourneySqlReadService::selectCurrentPairing([
    ['id' => 11, 'id_jornada_programada' => 4, 'revision_numero' => 1, 'supercedido_por_id' => 12],
    ['id' => 12, 'id_jornada_programada' => 4, 'revision_numero' => 2, 'supercedido_por_id' => null],
]);
$assert(($currentPairing['status'] ?? '') === 'ready' && (int) ($currentPairing['revision']['id'] ?? 0) === 12,
    'P1 selects the unique latest non-superseded revision');
$brokenPairing = ChecadorJourneySqlReadService::selectCurrentPairing([
    ['id' => 11, 'id_jornada_programada' => 4, 'revision_numero' => 1, 'supercedido_por_id' => 99],
]);
$assert(($brokenPairing['status'] ?? '') === 'invalid', 'P1 rejects a broken supersession reference');
$currentCalculation = ChecadorJourneySqlReadService::selectCurrentCalculation([
    ['id' => 21, 'id_jornada_programada' => 4, 'id_pareo_jornada' => 11, 'revision_numero' => 1],
    ['id' => 22, 'id_jornada_programada' => 4, 'id_pareo_jornada' => 12, 'revision_numero' => 2],
], 12, 4);
$assert(($currentCalculation['status'] ?? '') === 'ready' && (int) ($currentCalculation['revision']['id'] ?? 0) === 22,
    'P2 selects the latest calculation linked to the selected current P1 only');
$missingCalculation = ChecadorJourneySqlReadService::selectCurrentCalculation([
    ['id' => 21, 'id_jornada_programada' => 4, 'id_pareo_jornada' => 11, 'revision_numero' => 1],
], 12, 4);
$assert(($missingCalculation['status'] ?? '') === 'missing', 'P2 history from a superseded P1 is not combined with current P1');

$assert(ChecadorJourneySqlReadService::membershipConfirmsWorkplace([
    'status' => 'RESOLVED_SINGLE', 'assignments' => [['workplace_id' => 1]],
], 1), 'historical membership accepts one exact authorized workplace');
$assert(!ChecadorJourneySqlReadService::membershipConfirmsWorkplace([
    'status' => 'RESOLVED_MULTIPLE', 'assignments' => [['workplace_id' => 1], ['workplace_id' => 2]],
], 1), 'historical membership rejects overlapping workplaces instead of leaking rows');
$assert(!ChecadorJourneySqlReadService::membershipConfirmsWorkplace([
    'status' => 'RESOLVED_SINGLE', 'assignments' => [['workplace_id' => 2]],
], 1), 'historical membership rejects a different canonical workplace');

$denied = (new ChecadorJourneySqlReadService(static fn(int $id): bool => false))->read(1, '2026-11-19', '2026-11-19');
$assert(($denied['error'] ?? '') === 'permission_denied', 'service rejects an unauthorized locality before querying');
$invalidRange = (new ChecadorJourneySqlReadService(static fn(int $id): bool => true))->read(1, '2026-11-20', '2026-11-01');
$assert(($invalidRange['error'] ?? '') === 'invalid_date_range', 'invalid and reversed ranges fail closed');

Dotenv::createImmutable($base)->safeLoad();
Database::initialize();
$realRead = (new ChecadorJourneySqlReadService(
    static fn(int $id): bool => true,
    static fn(int $workerId, int $workplaceId, string $effective, string $recorded): array => [
        'status' => 'RESOLVED_SINGLE',
        'assignments' => [['workplace_id' => $workplaceId]],
    ],
    static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC'))
))->read(1, '2026-11-19', '2026-11-19');
$assert(($realRead['ok'] ?? false) === true, 'real MariaDB default read completes without mutation');
if (($realRead['ok'] ?? false) !== true) echo 'C01_REAL_READ_ERROR: ' . preg_replace('/[^a-z0-9_]/i', '', (string) ($realRead['error'] ?? 'unknown')) . "\n";
if (($realRead['ok'] ?? false) === true) {
    $assert(isset($realRead['data']['pagination']) && is_array($realRead['data']['journeys'] ?? null), 'real MariaDB result has stable rows and pagination shape');
    if ((int) ($realRead['data']['pagination']['total_records'] ?? -1) === 0) {
        $assert(($realRead['data']['journeys'] ?? null) === [], 'real zero-row MariaDB result is a legitimate empty result');
    }
}
$utcNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$timezoneReader = new App\Services\ChecadorWeeklyScheduleSqlReadService();
$timezone = $timezoneReader->timezoneForDate(1, $utcNow->format('Y-m-d'));
if ($timezone !== null) {
    $localToday = $utcNow->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
    $timezone = $timezoneReader->timezoneForDate(1, $localToday);
}
$today = $timezone === null ? null : new DateTimeImmutable($localToday, new DateTimeZone('UTC'));
$from = $today?->modify('-1 day')->format('Y-m-d');
$to = $today?->format('Y-m-d');
$rosterMethod = new ReflectionMethod(ChecadorController::class, 'journeyWorkerRosterOptions');
$rosterMethod->setAccessible(true);
$liveRoster = $timezone === null ? ['workers' => [], 'message' => 'timezone_unresolved'] : $rosterMethod->invoke(null, 1, $from, $to);
$liveRosterWorkers = is_array($liveRoster['workers'] ?? null) ? $liveRoster['workers'] : [];
$assert($timezone !== null && count($liveRosterWorkers) > 0
    && array_reduce($liveRosterWorkers, static fn(bool $valid, mixed $worker): bool => $valid
        && is_array($worker) && (int) ($worker['worker_id'] ?? 0) > 0
        && (int) ($worker['worker_id'] ?? 0) !== 900001
        && !str_contains(mb_strtolower((string) ($worker['full_name'] ?? '')), 'demostración'), true),
    'real Interlomas roster loads without persisted journeys and contains only canonical worker identities');
if ($liveRosterWorkers !== []) echo 'C01_INTERLOMAS_AUTHORIZED_ROSTER_COUNT: ' . count($liveRosterWorkers) . "\n";

printf("CHECADOR_C01_SQL_READ_RESULT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
