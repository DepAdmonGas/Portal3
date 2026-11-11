<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
\App\Core\Database::initialize();

use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Services\ChecadorPayrollComparisonReadService;
use App\Services\RecibosNominaService;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

echo "=== INICIANDO REGRESION FUNCIONAL: CHECADOR V2 COMPARACION NOMINA ===\n";

// 1. ROUTE VERIFICATION
$dispatcher = \FastRoute\simpleDispatcher(require __DIR__ . '/../routes/web.php');
$routeRes = $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/comparacion-nomina');
$assert($routeRes[0] === \FastRoute\Dispatcher::FOUND, 'Route /departamento-operativo/recursos-humanos/checador/comparacion-nomina is registered');

// 2. AUTHORIZATION & LOCALITY ISOLATION
$service = new ChecadorPayrollComparisonReadService();

// Encargado Palo Solo (User 52)
Session::set('usuario', ['id' => 52]);
Auth::forget();
$authEncargadoAll = $service->authorization();
$assert($authEncargadoAll['allowed'] === true, 'Encargado User 52 is authorized for general payroll comparison');
$encargadoLocs = array_map(static fn(array $w): int => (int) $w['id'], $authEncargadoAll['workplaces'] ?? []);
$assert(in_array(2, $encargadoLocs, true), 'Encargado User 52 has Palo Solo (id=2) in scope');
$assert(in_array(9, $encargadoLocs, true), 'Encargado User 52 has Autolavado (id=9) in scope');
$assert(!in_array(1, $encargadoLocs, true), 'Encargado User 52 does NOT have Interlomas (id=1) in scope (locality isolation)');

$authPaloSolo = $service->authorization(2);
$assert($authPaloSolo['allowed'] === true, 'Encargado User 52 is authorized specifically for Palo Solo (id=2)');
$authAutolavado = $service->authorization(9);
$assert($authAutolavado['allowed'] === true, 'Encargado User 52 is authorized specifically for Autolavado (id=9)');
$authInterlomas = $service->authorization(1);
$assert($authInterlomas['allowed'] === false, 'Encargado User 52 is denied for out-of-scope station Interlomas (id=1)');

// Dirección de Operaciones (User 19)
Session::set('usuario', ['id' => 19]);
Auth::forget();
$authDireccion = $service->authorization();
$assert($authDireccion['allowed'] === true, 'Dirección User 19 is authorized');
$direccionLocs = array_map(static fn(array $w): int => (int) $w['id'], $authDireccion['workplaces'] ?? []);
$assert(in_array(1, $direccionLocs, true), 'Dirección User 19 has Interlomas (id=1) in scope');
$assert(in_array(2, $direccionLocs, true), 'Dirección User 19 has Palo Solo (id=2) in scope');
$assert(in_array(9, $direccionLocs, true), 'Dirección User 19 has Autolavado (id=9) in scope');

// 3. PERIOD RESOLUTION
$periodWeek44 = ChecadorPayrollComparisonReadService::resolvePeriod(2, 2026, 'week', 44);
$assert($periodWeek44 !== null && $periodWeek44['start_date'] === '2026-10-29' && $periodWeek44['end_date'] === '2026-11-04', 'Period Semana 44/2026 resolves to 2026-10-29 .. 2026-11-04');
$periodWeek8 = ChecadorPayrollComparisonReadService::resolvePeriod(9, 2026, 'week', 8);
$assert($periodWeek8 !== null && $periodWeek8['start_date'] === '2026-02-19' && $periodWeek8['end_date'] === '2026-02-25', 'Period Semana 8/2026 resolves to 2026-02-19 .. 2026-02-25');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(2, 2026, 'week', 99) === null, 'Invalid period number is rejected');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(2, 2027, 'week', 1) === null, 'Future year is rejected');

// 4. SUMMARY COMPARISON ON REAL DATA (PALO SOLO - SEMANA 44/2026)
Session::set('usuario', ['id' => 52]);
Auth::forget();
$resPaloSolo = $service->comparePeriodSummary([
    'workplace_id' => 2,
    'year' => 2026,
    'period_number' => 44,
]);
$assert($resPaloSolo['ok'] === true, 'comparePeriodSummary succeeds for Palo Solo Semana 44/2026');
$paloSoloRows = $resPaloSolo['data']['rows'] ?? [];
$assert(count($paloSoloRows) === 21, 'Palo Solo Semana 44/2026 returns 21 worker comparison rows matching real data');
$assert(($resPaloSolo['data']['summary']['total_workers'] ?? 0) === 21, 'Summary metrics reports total_workers = 21');
$sampleRow = $paloSoloRows[0] ?? [];
$assert(!empty($sampleRow['worker_name']), 'Worker name is present in comparison row');
$assert(isset($sampleRow['comparison_status']), 'comparison_status is present');
$assert(isset($sampleRow['checador_summary']), 'checador_summary is present');
$assert(isset($sampleRow['payroll_summary']), 'payroll_summary is present');
$assert(isset($sampleRow['difference_summary']), 'difference_summary is present');

// 5. SUMMARY COMPARISON ON REAL DATA (AUTOLAVADO - SEMANA 8/2026)
$resAutolavado = $service->comparePeriodSummary([
    'workplace_id' => 9,
    'year' => 2026,
    'period_number' => 8,
]);
$assert($resAutolavado['ok'] === true, 'comparePeriodSummary succeeds for Autolavado Semana 8/2026');
$autolavadoRows = $resAutolavado['data']['rows'] ?? [];
$assert(count($autolavadoRows) === 18, 'Autolavado Semana 8/2026 returns 18 worker comparison rows matching real data');
$assert(($resAutolavado['data']['summary']['total_workers'] ?? 0) === 18, 'Autolavado summary metrics reports total_workers = 18');

// 6. FILTER COMBINATION (WORKER_ID)
$workerIdTarget = (int) ($paloSoloRows[0]['worker_id'] ?? 0);
$resFilteredWorker = $service->comparePeriodSummary([
    'workplace_id' => 2,
    'year' => 2026,
    'period_number' => 44,
    'worker_id' => $workerIdTarget,
]);
$assert($resFilteredWorker['ok'] === true, 'comparePeriodSummary succeeds with worker_id filter');
$assert(count($resFilteredWorker['data']['rows'] ?? []) === 1, 'Worker filter restricts result to exactly 1 row');
$assert((int) ($resFilteredWorker['data']['rows'][0]['worker_id'] ?? 0) === $workerIdTarget, 'Returned row matches filtered worker ID');

// 7. FILTER COMBINATION (STATUS)
$resFilteredStatus = $service->comparePeriodSummary([
    'workplace_id' => 2,
    'year' => 2026,
    'period_number' => 44,
    'status' => 'DIFERENCIA',
]);
$assert($resFilteredStatus['ok'] === true, 'comparePeriodSummary succeeds with status filter');
$assert(count($resFilteredStatus['data']['rows'] ?? []) === 21, 'Status filter returns matching rows');

// 8. INTERLOMAS REAL ATTENDANCE PUNCH DETECTION (SEMANA 43/2026)
Session::set('usuario', ['id' => 19]);
Auth::forget();
$resInterlomas = $service->comparePeriodSummary([
    'workplace_id' => 1,
    'year' => 2026,
    'period_number' => 43,
]);
$assert($resInterlomas['ok'] === true, 'comparePeriodSummary succeeds for Interlomas Semana 43/2026');
$interlomasRows = $resInterlomas['data']['rows'] ?? [];
$assert(count($interlomasRows) > 0, 'Interlomas Semana 43/2026 returns comparison rows');
$hasPunchMatch = false;
$hasDifferenceAttendanceWithoutPayroll = false;
foreach ($interlomasRows as $ir) {
    if (!empty($ir['raw_punches'])) {
        $hasPunchMatch = true;
        if ($ir['comparison_status'] === 'DIFERENCIA' && str_contains($ir['difference_summary'], 'Asistencia registrada')) {
            $hasDifferenceAttendanceWithoutPayroll = true;
        }
    }
}
$assert($hasPunchMatch === true, 'Interlomas Semana 43 detects real raw punches from op_rh_v2_marcaciones_crudas');
$assert($hasDifferenceAttendanceWithoutPayroll === true, 'Interlomas Semana 43 detects verified difference for attendance without payroll receipt');

// 9. SECURITY & NON-MONETARY DATA INTEGRITY
$viewContent = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/payroll-comparison.php');
$assert(!str_contains(strtolower($viewContent), 'importe_total'), 'View does NOT contain importe_total');
$assert(!str_contains(strtolower($viewContent), 'prima_vacacional'), 'View does NOT contain prima_vacacional');
$assert(!str_contains(strtolower($viewContent), 'cuenta bancaria'), 'View does NOT contain cuenta bancaria');
$assert(str_contains($viewContent, 'table-actions-cell text-center'), 'View contains centered actions cell table-actions-cell text-center');
$assert(str_contains($viewContent, 'checador-payroll-table'), 'View contains table ID checador-payroll-table');
$assert(str_contains($viewContent, 'payroll-detail-modal-'), 'View renders detail modal with id prefix payroll-detail-modal-');

echo "\n======================================================\n";
echo "RESULTADO REGRESION: {$passes} PASS / {$fails} FAIL\n";
echo "======================================================\n";

if ($fails > 0) {
    exit(1);
}
