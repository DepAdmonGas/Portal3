<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
\App\Core\Database::initialize();

use App\Models\Usuario;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorPersistedIncidenceReadService;
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
    str_contains($routes, "'/departamento-operativo/recursos-humanos/checador/incidencias'")
    && str_contains($routes, "['ChecadorController', 'incidences']"),
    'M06 route /departamento-operativo/recursos-humanos/checador/incidencias is registered'
);
$assert(
    str_contains($routes, "'/recursos-humanos/checador/incidencias'")
    && str_contains($routes, "['ChecadorController', 'incidences']"),
    'M06 route /recursos-humanos/checador/incidencias is registered'
);

// 2. Database source verification
$pdo = Capsule::connection()->getPdo();
$tables = [
    'op_rh_v2_incidencias_maestro',
    'op_rh_v2_incidencias_revisiones',
    'op_rh_v2_incidencias_aplicaciones',
    'op_rh_v2_evaluaciones_incidencias',
    'op_rh_v2_evaluaciones_administrativas',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_marcaciones_crudas',
    'op_rh_personal',
    'op_rh_localidades',
];
$allTablesExist = true;
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
    if ($stmt->rowCount() === 0) {
        $allTablesExist = false;
        break;
    }
}
$assert($allTablesExist, 'Persisted incidence source tables exist in MariaDB bd_admongas');

// 3. Service input validation
$service = new ChecadorPersistedIncidenceReadService();

$invWorkplace = $service->getPersistedIncidences(0, '2026-10-01', '2026-10-07');
$assert($invWorkplace['ok'] === false && $invWorkplace['error'] === 'workplace_invalid', 'Service rejects invalid workplace ID (< 1)');

$invDates = $service->getPersistedIncidences(2, 'invalid-date', '2026-10-07');
$assert($invDates['ok'] === false && $invDates['error'] === 'dates_invalid', 'Service rejects malformed date parameters');

// 4. Real read without HTTP calls or recalculation
$realRead = $service->getPersistedIncidences(2, '2026-10-01', '2026-10-07');
$assert($realRead['ok'] === true && is_array($realRead['incidences']) && $realRead['total'] === 0, 'Empty state returns ok=true and total=0 without errors on unpopulated database');

// 5. Contractual Types and Statuses catalogs
$types = ChecadorPersistedIncidenceReadService::getContractualTypes();
$assert(
    isset($types['VACATION'], $types['PERMISSION'], $types['DISABILITY'], $types['AUTHORIZED_LATENESS'], $types['AUTHORIZED_EARLY_DEPARTURE'], $types['OFFICIAL_MISSION'], $types['PUNCH_ATTENDANCE'], $types['JUSTIFICATION']),
    'getContractualTypes contains all frozen canonical incidence categories'
);

$statuses = ChecadorPersistedIncidenceReadService::getContractualStatuses();
$assert(
    isset($statuses['ACTIVE'], $statuses['RESOLVED'], $statuses['AUTHORIZED'], $statuses['REJECTED'], $statuses['CANCELLED'], $statuses['REVOKED']),
    'getContractualStatuses contains all contractual incidence states'
);

// 6. Time and date formatting (12-hour format requirement: 07:00 am, 04:00 pm)
$time1 = ChecadorPersistedIncidenceReadService::format12hTimeString('07:00:00');
$assert($time1 === '07:00 am', "format12hTimeString formats 07:00:00 to 07:00 am (got: $time1)");

$time2 = ChecadorPersistedIncidenceReadService::format12hTimeString('16:00:00');
$assert($time2 === '04:00 pm', "format12hTimeString formats 16:00:00 to 04:00 pm (got: $time2)");

$time3 = ChecadorPersistedIncidenceReadService::format12hTime('2026-10-21 13:00:00', 'America/Mexico_City');
$assert($time3 === '07:00 am', "format12hTime converts UTC 13:00:00 to 07:00 am in America/Mexico_City (got: $time3)");

$time4 = ChecadorPersistedIncidenceReadService::format12hTime(null);
$assert($time4 === '--', 'format12hTime returns -- for null timestamp');

$dateDisplay = ChecadorPersistedIncidenceReadService::formatCivilDate('2026-10-21');
$assert($dateDisplay === '21/10/2026', "formatCivilDate formats 2026-10-21 to 21/10/2026 (got: $dateDisplay)");

// 7. Status badge metadata
$badgeActive = ChecadorPersistedIncidenceReadService::getStatusBadge('ACTIVE');
$assert(str_contains($badgeActive['class'], 'bg-success-subtle') && $badgeActive['label'] === 'Activa', 'ACTIVE status maps to Activa with success badge');

$badgeResolved = ChecadorPersistedIncidenceReadService::getStatusBadge('RESOLVED');
$assert(str_contains($badgeResolved['class'], 'bg-info-subtle') && $badgeResolved['label'] === 'Resuelta', 'RESOLVED status maps to Resuelta with info badge');

$badgeRejected = ChecadorPersistedIncidenceReadService::getStatusBadge('REJECTED');
$assert(str_contains($badgeRejected['class'], 'bg-danger-subtle') && $badgeRejected['label'] === 'Rechazada', 'REJECTED status maps to Rechazada with danger badge');

// 8. Locality authorization & isolation: Encargado Palo Solo, Autolavado, Dirección
$_SESSION = ['usuario' => ['id' => 52]];
\App\Core\Auth::forget();
$authPaloSolo52 = ChecadorAccessService::authorizeIncidenceRead(2);
$assert($authPaloSolo52['allowed'] === true, 'Encargado User 52 is authorized for Palo Solo (id=2)');

$authAutolavado52 = ChecadorAccessService::authorizeIncidenceRead(9);
$assert($authAutolavado52['allowed'] === true, 'Encargado User 52 is authorized for Autolavado (id=9)');

$authDeniedStation52 = ChecadorAccessService::authorizeIncidenceRead(1);
$assert($authDeniedStation52['allowed'] === false, 'Encargado User 52 is denied for out-of-scope station (id=1)');

$_SESSION = ['usuario' => ['id' => 19]];
\App\Core\Auth::forget();
$authDirAutolavado = ChecadorAccessService::authorizeIncidenceRead(9);
$assert($authDirAutolavado['allowed'] === true, 'Dirección User 19 is authorized for Autolavado (id=9)');

$authDirPaloSolo = ChecadorAccessService::authorizeIncidenceRead(2);
$assert($authDirPaloSolo['allowed'] === true, 'Dirección User 19 is authorized for Palo Solo (id=2)');

$_SESSION = ['usuario' => ['id' => 4]];
\App\Core\Auth::forget();
$authForeign = ChecadorAccessService::authorizeIncidenceRead(9);
$assert($authForeign['allowed'] === false, 'Foreign user without department scope is denied for Autolavado (id=9)');

// 9. View template rendering: Empty State and DataTables structure
$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php';
$renderView = static function (array $data) use ($viewPath): string {
    extract($data);
    ob_start();
    include $viewPath;
    return (string) ob_get_clean();
};

// Render with 0 persisted incidences -> clean empty state
$emptyHtml = $renderView([
    'persistedIncidences' => [],
    'selectedWorkplaceId' => 2,
    'selectedWorkplaceName' => 'Palo Solo',
    'selectedDate' => '2026-11-06',
    'incidenceDateFrom' => '2026-11-01',
    'incidenceDateTo' => '2026-11-06',
    'workerSelectOptions' => [],
    'selectedWorkerId' => null,
    'incidenceType' => '',
    'incidenceStatus' => '',
    'contractualIncidenceTypes' => ChecadorPersistedIncidenceReadService::getContractualTypes(),
    'contractualIncidenceStatuses' => ChecadorPersistedIncidenceReadService::getContractualStatuses(),
    'state' => 'success',
    'stateMessage' => '',
]);

$assert(
    str_contains($emptyHtml, 'No se encontraron incidencias para los filtros seleccionados.')
    && !str_contains($emptyHtml, '<table id="checador-incidences-table"'),
    'Empty state renders clean alert without table when 0 rows found'
);

// Render with 1 persisted incidence -> DataTables table, badges, 12h times, and detail modal
$sampleIncidence = [
    'id' => 101,
    'uuid' => 'INC_UUID_TEST_101',
    'worker_id' => 950,
    'worker_name' => 'Juan Pérez Gómez',
    'employee_number' => '12345',
    'workplace_id' => 2,
    'workplace_name' => 'Palo Solo',
    'type' => 'VACATION',
    'type_label' => 'Vacaciones',
    'scope' => 'WORKER',
    'date_start' => '2026-10-21',
    'date_end' => '2026-10-25',
    'date_display' => '21/10/2026 – 25/10/2026',
    'status' => 'ACTIVE',
    'status_badge' => ChecadorPersistedIncidenceReadService::getStatusBadge('ACTIVE'),
    'current_revision' => 1,
    'motivo' => 'Periodo vacacional autorizado',
    'folio_evidencia' => 'FOL-2026-VAC-01',
    'shift_name' => 'Turno 1 (07:00 am – 03:00 pm)',
    'shift_hours' => '07:00 am – 03:00 pm',
    'created_by_user_id' => 52,
    'approved_by_user_id' => 19,
    'created_at_utc' => '2026-10-20 15:00:00',
    'created_at_local' => '20/10/2026 09:00 am',
    'revisions' => [
        [
            'id' => 201,
            'revision_numero' => 1,
            'estado_revision' => 'ACTIVE',
            'status_badge' => ChecadorPersistedIncidenceReadService::getStatusBadge('ACTIVE'),
            'motivo' => 'Periodo vacacional autorizado',
            'folio_evidencia' => 'FOL-2026-VAC-01',
            'creado_por_usuario_id' => 52,
            'aprobado_por_usuario_id' => 19,
            'creado_at_utc' => '2026-10-20 15:00:00',
            'creado_at_local' => '20/10/2026 09:00 am',
        ]
    ],
    'applications' => [
        [
            'id' => 301,
            'id_jornada_programada' => 5001,
            'fecha_jornada_local' => '2026-10-21',
            'fecha_display' => '21/10/2026',
            'estado_aplicacion' => 'APPLIED',
        ]
    ],
    'evaluations' => [],
    'punches' => [
        [
            'id' => 401,
            'dispositivo_id' => 12,
            'event_uuid' => 'PUNCH_EVENT_UUID_999',
            'time_12h' => '07:00 am',
            'fecha_local' => '2026-10-21',
            'metodo_captura' => 'BIO',
            'source_raw' => '2026-10-21 07:00:00',
        ]
    ],
    'modal_id' => 'checador-incidence-detail-101',
];

$populatedHtml = $renderView([
    'persistedIncidences' => [$sampleIncidence],
    'selectedWorkplaceId' => 2,
    'selectedWorkplaceName' => 'Palo Solo',
    'selectedDate' => '2026-11-06',
    'incidenceDateFrom' => '2026-10-20',
    'incidenceDateTo' => '2026-10-25',
    'workerSelectOptions' => [['worker_id' => 950, 'full_name' => 'Juan Pérez Gómez', 'employee_number' => '12345']],
    'selectedWorkerId' => null,
    'incidenceType' => '',
    'incidenceStatus' => '',
    'contractualIncidenceTypes' => ChecadorPersistedIncidenceReadService::getContractualTypes(),
    'contractualIncidenceStatuses' => ChecadorPersistedIncidenceReadService::getContractualStatuses(),
    'state' => 'success',
    'stateMessage' => '',
]);

$assert(str_contains($populatedHtml, 'id="checador-incidences-table"'), 'Table renders with id checador-incidences-table');
$assert(str_contains($populatedHtml, 'Juan Pérez Gómez'), 'Table contains worker name');
$assert(str_contains($populatedHtml, '21/10/2026 – 25/10/2026'), 'Table contains date range');
$assert(str_contains($populatedHtml, 'Vacaciones'), 'Table contains type label');
$assert(str_contains($populatedHtml, 'Turno 1 (07:00 am – 03:00 pm)'), 'Table contains scheduled shift');
$assert(str_contains($populatedHtml, 'badge bg-success-subtle text-success') && str_contains($populatedHtml, 'Activa'), 'Table contains status badge');
$assert(str_contains($populatedHtml, 'class="table-actions-cell text-center"'), 'Actions cell is centered with class table-actions-cell text-center');
$assert(str_contains($populatedHtml, '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#checador-incidence-detail-101"'), 'Direct button Detalle points to detail modal');

// 10. Read-only Detail modal verification
$assert(str_contains($populatedHtml, 'id="checador-incidence-detail-101"'), 'Detail modal is rendered with matching ID');
$assert(str_contains($populatedHtml, 'INC_UUID_TEST_101'), 'Detail modal renders incidence UUID');
$assert(str_contains($populatedHtml, 'FOL-2026-VAC-01'), 'Detail modal renders evidence folio');
$assert(str_contains($populatedHtml, 'Periodo vacacional autorizado'), 'Detail modal renders motivo/description');
$assert(str_contains($populatedHtml, '07:00 am') && str_contains($populatedHtml, 'PUNCH_EVENT_UUID_999'), 'Detail modal renders associated punch with 12h time and UUID');
$assert(str_contains($populatedHtml, 'Usuario ID 52') && str_contains($populatedHtml, 'Usuario ID 19'), 'Detail modal renders audit creator and approver user IDs');
$assert(!str_contains($populatedHtml, 'name="resolution_notes"') && !str_contains($populatedHtml, '<textarea'), 'Detail modal is strictly read-only and contains no operational edit forms');

echo "SUMMARY: $passes PASS, $fails FAIL\n";
exit($fails > 0 ? 1 : 0);
