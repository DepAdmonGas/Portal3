<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
\App\Core\Database::initialize();

use App\Models\Usuario;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorPersistedJourneyReadService;
use App\Services\ChecadorV2PermissionService;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

// 1. Route verification
$routes = file_get_contents(__DIR__ . '/../routes/web.php');
$assert(
    is_string($routes) && str_contains($routes, "'/departamento-operativo/recursos-humanos/checador/jornadas'"),
    'M05 route /departamento-operativo/recursos-humanos/checador/jornadas is registered'
);

// 2. Persisted source tables verification in MariaDB
$pdo = Capsule::connection()->getPdo();
$requiredTables = [
    'op_rh_v2_calculos_jornada',
    'op_rh_v2_calculos_segmentos',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_pareos_jornada',
    'op_rh_v2_pareos_segmentos',
    'op_rh_v2_pareos_marcaciones_asociadas',
    'op_rh_v2_marcaciones_crudas',
];
$existingTables = $pdo->query("SHOW TABLES LIKE 'op_rh_v2_%'")->fetchAll(\PDO::FETCH_COLUMN);
$allTablesExist = count(array_intersect($requiredTables, $existingTables)) === count($requiredTables);
$assert($allTablesExist, 'Persisted journey source tables exist in MariaDB');

// 3. Service real read and error handling
$service = new ChecadorPersistedJourneyReadService();

$invalidWorkplace = $service->getPersistedJourneys(0, '2026-10-01', '2026-10-31');
$assert(
    $invalidWorkplace['ok'] === false && ($invalidWorkplace['error'] ?? '') === 'workplace_invalid',
    'Service rejects invalid workplace ID (< 1)'
);

$invalidDates = $service->getPersistedJourneys(1, 'invalid-date', '2026-10-31');
$assert(
    $invalidDates['ok'] === false && ($invalidDates['error'] ?? '') === 'dates_invalid',
    'Service rejects malformed date parameters'
);

$emptyRead = $service->getPersistedJourneys(1, '2026-10-01', '2026-10-31');
$assert(
    $emptyRead['ok'] === true && $emptyRead['total'] === 0 && is_array($emptyRead['journeys']),
    'Empty state returns ok=true and total=0 without errors on unpopulated database'
);

// 4. 12-hour format display tests
$time1 = ChecadorPersistedJourneyReadService::format12hTime('2026-10-20 14:00:00', 'America/Mexico_City');
$assert($time1 === '08:00 am', 'format12hTime formats 14:00:00 UTC to 08:00 am (Mexico City UTC-6)');

$time2 = ChecadorPersistedJourneyReadService::format12hTime('2026-10-20 22:30:00', 'America/Mexico_City');
$assert($time2 === '04:30 pm', 'format12hTime formats 22:30:00 UTC to 04:30 pm (Mexico City UTC-6)');

$time3 = ChecadorPersistedJourneyReadService::format12hTime(null, 'America/Mexico_City', '12:00:00');
$assert($time3 === '12:00 pm', 'format12hTime formats raw 12:00:00 to 12:00 pm');

$time4 = ChecadorPersistedJourneyReadService::format12hTime(null, null, null);
$assert($time4 === '--', 'format12hTime returns -- for null punch time');

// 5. Contractual status badges and durations
$badgeCalc = ChecadorPersistedJourneyReadService::statusBadge('CALCULATED');
$assert(
    $badgeCalc['label'] === 'Calculada' && str_contains($badgeCalc['class'], 'bg-success'),
    'Status CALCULATED maps to Calculada with success badge'
);

$badgeDayOff = ChecadorPersistedJourneyReadService::statusBadge('DAY_OFF_RECORDED');
$assert(
    $badgeDayOff['label'] === 'Descanso registrado' && str_contains($badgeDayOff['class'], 'bg-info'),
    'Status DAY_OFF_RECORDED maps to Descanso registrado with info badge'
);

$badgeNoPunch = ChecadorPersistedJourneyReadService::statusBadge('UNMATCHED_NO_PUNCHES');
$assert(
    $badgeNoPunch['label'] === 'Sin marcaciones' && str_contains($badgeNoPunch['class'], 'bg-secondary'),
    'Status UNMATCHED_NO_PUNCHES maps to Sin marcaciones with secondary badge'
);

$dur1 = ChecadorPersistedJourneyReadService::formatDuration(28800);
$assert($dur1 === '08:00 hrs', 'formatDuration formats 28800 seconds to 08:00 hrs');

$dur2 = ChecadorPersistedJourneyReadService::formatDuration(null);
$assert($dur2 === '--', 'formatDuration returns -- for null duration');

// 6. Locality authorization & isolation: Encargado Palo Solo, Autolavado, Dirección
$_SESSION = ['usuario' => ['id' => 52]];
\App\Core\Auth::forget();
$authPaloSolo52 = ChecadorAccessService::authorizeJourneyRead(2);
$assert($authPaloSolo52['allowed'] === true, 'Encargado User 52 is authorized for Palo Solo (id=2)');

$authAutolavado52 = ChecadorAccessService::authorizeJourneyRead(9);
$assert($authAutolavado52['allowed'] === true, 'Encargado User 52 is authorized for Autolavado (id=9)');

$_SESSION = ['usuario' => ['id' => 19]];
\App\Core\Auth::forget();
$authDirAutolavado = ChecadorAccessService::authorizeJourneyRead(9);
$assert($authDirAutolavado['allowed'] === true, 'Dirección User 19 is authorized for Autolavado (id=9)');

$_SESSION = ['usuario' => ['id' => 22]];
\App\Core\Auth::forget();
$authForeign = ChecadorAccessService::authorizeJourneyRead(9);
$assert($authForeign['allowed'] === false, 'Foreign user without department scope is denied for Autolavado');

// 7. View template renders DataTables 1.13.5 structure and detail modal
$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php';
$render = static function (array $vars) use ($viewPath): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};

$htmlEmpty = $render([
    'selectedWorkplaceName' => 'Palo Solo',
    'selectedWorkplaceId' => 2,
    'selectedDate' => '2026-11-06',
    'dateFrom' => '2026-11-01',
    'dateTo' => '2026-11-06',
    'statusFilter' => '',
    'selectedWorkerId' => null,
    'state' => 'success',
    'stateMessage' => '',
    'persistedJourneys' => [],
    'workerSelectOptions' => [
        ['worker_id' => 23, 'full_name' => 'Beltran Torres Jose Luis'],
    ],
    'contractualStatuses' => ChecadorPersistedJourneyReadService::getContractualStatuses(),
]);

$assert(
    str_contains($htmlEmpty, 'id="checador-journeys-table"') === false
    && str_contains($htmlEmpty, 'No se encontraron jornadas laborales para los filtros seleccionados.'),
    'Empty state renders clean alert without table when 0 rows found'
);

$sampleJourney = [
    'id' => 101,
    'worker_id' => 23,
    'worker_name' => 'Beltran Torres Jose Luis',
    'employee_number' => '1004',
    'workplace_id' => 2,
    'workplace_name' => 'Palo Solo',
    'date' => '2026-11-06',
    'status' => 'CALCULATED',
    'status_badge' => ['label' => 'Calculada', 'class' => 'badge text-bg-success'],
    'shift_name' => 'TURNO 1 (07:00 am – 03:00 pm)',
    'scheduled_type' => 'SCHEDULED',
    'entry_display' => '07:02 am',
    'exit_display' => '03:05 pm',
    'entry_raw' => '2026-11-06 07:02:00',
    'exit_raw' => '2026-11-06 15:05:00',
    'duration_display' => '08:03 hrs',
    'scheduled_duration_display' => '08:00 hrs',
    'revision_number' => 1,
    'algorithm_version' => 'v2.4-p2',
    'execution_fingerprint' => 'abc123def456',
    'calculated_at_utc' => '2026-11-06 15:10:00',
    'timezone' => 'America/Mexico_City',
    'segments' => [
        [
            'ordinal_segmento' => 1,
            'tipo_segmento' => 'WORK',
            'estado_segmento' => 'CALCULATED',
            'segundos_programados' => 28800,
            'segundos_trabajados' => 28980,
            'seg_entry_raw' => '2026-11-06 07:02:00',
            'seg_exit_raw' => '2026-11-06 15:05:00',
        ]
    ],
    'raw_punches' => [
        [
            'rol_marcacion' => 'ENTRY',
            'event_uuid' => 'uuid-punch-entry-1',
            'source_timestamp_raw' => '2026-11-06 07:02:00',
            'id_dispositivo' => 101,
            'metodo_captura' => 'BIOMETRIC',
            'processing_status' => 'RECEIVED',
        ],
        [
            'rol_marcacion' => 'EXIT',
            'event_uuid' => 'uuid-punch-exit-1',
            'source_timestamp_raw' => '2026-11-06 15:05:00',
            'id_dispositivo' => 101,
            'metodo_captura' => 'BIOMETRIC',
            'processing_status' => 'RECEIVED',
        ]
    ],
    'incidences' => [],
];

$htmlWithRows = $render([
    'selectedWorkplaceName' => 'Palo Solo',
    'selectedWorkplaceId' => 2,
    'selectedDate' => '2026-11-06',
    'dateFrom' => '2026-11-01',
    'dateTo' => '2026-11-06',
    'statusFilter' => '',
    'selectedWorkerId' => null,
    'state' => 'success',
    'stateMessage' => '',
    'persistedJourneys' => [$sampleJourney],
    'workerSelectOptions' => [
        ['worker_id' => 23, 'full_name' => 'Beltran Torres Jose Luis'],
    ],
    'contractualStatuses' => ChecadorPersistedJourneyReadService::getContractualStatuses(),
]);

$assert(
    str_contains($htmlWithRows, 'id="checador-journeys-table"')
    && str_contains($htmlWithRows, 'Beltran Torres Jose Luis')
    && str_contains($htmlWithRows, '07:02 am')
    && str_contains($htmlWithRows, '03:05 pm')
    && str_contains($htmlWithRows, 'Calculada')
    && str_contains($htmlWithRows, 'data-bs-target="#journey-detail-101"')
    && str_contains($htmlWithRows, 'id="journey-detail-101"'),
    'Table renders persisted journey row, 12h times, status badge, and detail modal'
);

$assert(
    str_contains($htmlWithRows, 'uuid-punch-entry-1')
    && str_contains($htmlWithRows, 'abc123def456')
    && str_contains($htmlWithRows, 'v2.4-p2'),
    'Detail modal renders raw punch UUID, execution fingerprint, and algorithm version'
);

echo "SUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);
