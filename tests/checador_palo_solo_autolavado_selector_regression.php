<?php

declare(strict_types=1);

/**
 * Regression and integration verification suite for Palo Solo / Autolavado Selector.
 * Validates:
 * 1. Individual ACL boundary (User 52 & 117 vs User 33).
 * 2. Institutional selector rendering (ModuleStationService::render).
 * 3. Context propagation and validation (/api/module-context/set).
 * 4. Complete data isolation between Palo Solo (2) and Autolavado (9).
 * 5. M03 Fail-closed behavior on department context without silent context override.
 * 6. Scheduling write rejection (403) for department localities.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Services\ChecadorAccessService;
use App\Services\ModuleStationService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\ChecadorRawPunchReadService;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
session_start();
Database::initialize();

function assertCheck(bool $condition, string $testName, string $details = ''): void {
    if (!$condition) {
        echo "[\033[31mFAIL\033[0m] {$testName} - {$details}\n";
        exit(1);
    }
    echo "[\033[32mPASS\033[0m] {$testName}\n";
}

echo "=== INICIANDO VALIDACIÓN: CHECADOR_V2_PALO_SOLO_AUTOLAVADO_SELECTOR ===\n\n";

// --- TEST 1: Individual ACL Boundary ---
echo "--- Test 1: Alcance de Encargado Palo Solo (User 52) vs Interlomas (User 33) ---\n";
// Clear any static cache
$reflAuth = new ReflectionClass(Auth::class);
$userProp = $reflAuth->getProperty('user');
$userProp->setAccessible(true);

// User 52: Palo Solo with individual ACL
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 52]);
$access52 = ChecadorAccessService::authorize();
assertCheck($access52['allowed'] === true, 'User 52 autorizado en Checador');
assertCheck(count($access52['station_workplaces']) === 1, 'User 52 tiene exactamente 1 estación física');
assertCheck((int) $access52['station_workplaces'][0]['id'] === 2, 'User 52 estación física es Palo Solo (2)');
assertCheck(count($access52['department_workplaces']) === 1, 'User 52 tiene exactamente 1 departamento');
assertCheck((int) $access52['department_workplaces'][0]['id'] === 9, 'User 52 departamento es Autolavado (9)');
assertCheck(count($access52['workplaces']) === 2, 'User 52 total workplaces es 2 (Palo Solo y Autolavado)');

// User 33: Interlomas WITHOUT individual Palo Solo ACL
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 33]);
$access33 = ChecadorAccessService::authorize();
assertCheck($access33['allowed'] === true, 'User 33 autorizado en Checador');
assertCheck(count($access33['station_workplaces']) === 1, 'User 33 tiene exactamente 1 estación física');
assertCheck((int) $access33['station_workplaces'][0]['id'] === 1, 'User 33 estación física es Interlomas (1)');
assertCheck(count($access33['department_workplaces']) === 0, 'User 33 NO tiene departamentos (Autolavado bloqueado)');
assertCheck(count($access33['workplaces']) === 1, 'User 33 total workplaces es 1 (sólo Interlomas)');

// --- TEST 2: Selector HTML Rendering ---
echo "\n--- Test 2: Renderizado del selector institucional ---\n";
// User 52: Must show select element with Estaciones and Departamentos
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 52]);
$html52 = ModuleStationService::render('checador');
assertCheck(strpos($html52, 'id="module-station-selector-checador"') !== false, 'User 52 recibe elemento <select> de Checador');
assertCheck(strpos($html52, 'optgroup label="Estaciones"') !== false, 'User 52 ve grupo "Estaciones"');
assertCheck(strpos($html52, 'value="estacion_2"') !== false, 'User 52 ve opción "estacion_2" (Palo Solo)');
assertCheck(strpos($html52, 'optgroup label="Departamentos"') !== false, 'User 52 ve grupo "Departamentos"');
assertCheck(strpos($html52, 'value="depto_9"') !== false, 'User 52 ve opción "depto_9" (Autolavado)');
assertCheck(strpos($html52, 'value="estacion_1"') === false, 'User 52 NO ve Interlomas');
assertCheck(strpos($html52, 'value="estacion_3"') === false, 'User 52 NO ve otras estaciones');

// User 33: Must NOT show select element (only badge)
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 33]);
$html33 = ModuleStationService::render('checador');
assertCheck(strpos($html33, 'id="module-station-selector-checador"') === false, 'User 33 NO recibe elemento <select>');
assertCheck(strpos($html33, 'Interlomas') !== false, 'User 33 ve badge de Interlomas');

// --- TEST 3: Context Propagation and SetContext ---
echo "\n--- Test 3: Propagación y persistencia de contexto ---\n";
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 52]);

// Select Palo Solo
ModuleStationService::setContext('checador', 2, null);
$ctxPaloSolo = ModuleStationService::getContext('checador');
assertCheck($ctxPaloSolo['id_estacion'] === 2 && $ctxPaloSolo['id_depto'] === null, 'Contexto Palo Solo establecido');
assertCheck($ctxPaloSolo['nombre'] === 'Palo Solo', 'Nombre de contexto es Palo Solo');

// Select Autolavado
ModuleStationService::setContext('checador', null, 9);
$ctxAutolavado = ModuleStationService::getContext('checador');
assertCheck($ctxAutolavado['id_estacion'] === null && $ctxAutolavado['id_depto'] === 9, 'Contexto Autolavado establecido');
assertCheck($ctxAutolavado['nombre'] === 'Autolavado', 'Nombre de contexto es Autolavado');

// Reject invalid station (e.g. 1 Interlomas for user 52)
ModuleStationService::setContext('checador', 1, null);
$ctxAfterInvalid = ModuleStationService::getContext('checador');
assertCheck($ctxAfterInvalid['id_estacion'] === null, 'Estación 1 fuera de alcance no se persiste para User 52');

// --- TEST 4: Data Isolation (Roster) ---
echo "\n--- Test 4: Aislamiento total de datos entre Palo Solo y Autolavado ---\n";
$rosterService = new ChecadorAttendanceRosterService();
$rosterPaloSolo = $rosterService->workersForAttendanceFilter(2, '2026-11-05');
$rosterAutolavado = $rosterService->workersForAttendanceFilter(9, '2026-11-05');

assertCheck(count($rosterPaloSolo['workers']) === 21, 'Palo Solo contiene exactamente 21 trabajadores activos');
$rosterDb = Illuminate\Database\Capsule\Manager::connection();
$autolavadoCandidateRows = $rosterDb->table('op_rh_personal as person')
    ->where('person.estado', 1)
    ->where(static function ($query): void {
        $query->where('person.id_estacion', 9)
            ->orWhereIn('person.id', static function ($membershipQuery): void {
                $membershipQuery->select('worker_id')
                    ->from('op_rh_personal_localidad_version')
                    ->where('workplace_id', 9);
            });
    })
    ->orderBy('person.id')
    ->get(['person.id', 'person.id_estacion', 'person.nombre_completo']);
$autolavadoCandidateIds = $autolavadoCandidateRows
    ->map(static fn($person): int => (int) $person->id)
    ->all();
$autolavadoVersionedIds = $autolavadoCandidateIds === []
    ? []
    : array_fill_keys(array_map('intval', $rosterDb->table('op_rh_personal_localidad_version')
        ->whereIn('worker_id', $autolavadoCandidateIds)
        ->distinct()
        ->pluck('worker_id')
        ->all()), true);
$autolavadoEffectiveAtUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\\TH:i:s.u\\Z');
$autolavadoResolver = new \App\Services\WorkerWorkplaceAssignmentResolver();
$expectedAutolavadoIds = [];
foreach ($autolavadoCandidateRows as $candidate) {
    $workerId = (int) $candidate->id;
    $name = trim((string) $candidate->nombre_completo);
    if ($workerId < 1 || $name === '' || $name === 'Nombre no disponible') {
        continue;
    }
    if (isset($autolavadoVersionedIds[$workerId])) {
        $membership = $autolavadoResolver->resolveAssignments($workerId, $autolavadoEffectiveAtUtc);
        $assignments = $membership['assignments'] ?? [];
        if (($membership['status'] ?? '') === 'RESOLVED_SINGLE'
            && count($assignments) === 1
            && (int) ($assignments[0]['workplace_id'] ?? 0) === 9) {
            $expectedAutolavadoIds[] = $workerId;
        }
        continue;
    }
    if ((int) ($candidate->id_estacion ?? 0) === 9) {
        $expectedAutolavadoIds[] = $workerId;
    }
}
$actualAutolavadoIds = array_map(
    static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
    $rosterAutolavado['workers']
);
sort($expectedAutolavadoIds);
sort($actualAutolavadoIds);
assertCheck(($rosterAutolavado['status'] ?? '') === 'current_roster_only'
    && ($rosterAutolavado['coverage'] ?? '') === 'current_active_roster'
    && count($actualAutolavadoIds) === count(array_unique($actualAutolavadoIds))
    && $actualAutolavadoIds === $expectedAutolavadoIds,
    'Autolavado current roster contains each active worker justified by its current locality or effective canonical membership');

$paloSoloIds = array_column($rosterPaloSolo['workers'], 'worker_id');
$autolavadoIds = $actualAutolavadoIds;
$overlap = array_intersect($paloSoloIds, $autolavadoIds);
assertCheck(count($overlap) === 0, 'CERO intersección de trabajadores entre Palo Solo y Autolavado');

// Verify positions: Autolavado workers should not include despachadores
$db = Illuminate\Database\Capsule\Manager::connection();
$autolavadoPositions = $db->table('op_rh_personal')
    ->whereIn('id', $autolavadoIds)
    ->pluck('puesto')
    ->all();
assertCheck(!in_array(1, $autolavadoPositions, true), 'Autolavado no contiene despachadores de gasolinera');

// --- TEST 5: Raw Punch Service department support ---
echo "\n--- Test 5: Servicio de marcaciones crudas para departamento ---\n";
$punchService = new ChecadorRawPunchReadService();
$pageAutolavado = $punchService->readDataTablePage(
    9,
    '2026-11-01 00:00:00',
    '2026-11-05 23:59:59',
    null,
    0,
    25,
    '',
    1,
    'asc',
    'Autolavado'
);
assertCheck($pageAutolavado['ok'] === true, 'readDataTablePage resuelve exitosamente para Autolavado (id: 9)');
assertCheck(isset($pageAutolavado['records_total']), 'readDataTablePage devuelve conteo sin error SQL ni excepción');

// --- TEST 6: M03 Fail-Closed on Department ---
echo "\n--- Test 6: M03 Programación semanal bloqueada para departamento ---\n";
// Scheduling authorization for Autolavado must be DENIED
$schedRead9 = ChecadorAccessService::authorizeScheduling(9, 'leer');
assertCheck($schedRead9['allowed'] === false, 'Lectura de programación para Autolavado (9) denegada');
assertCheck($schedRead9['reason'] === 'requested_station_out_of_scope', 'Razón es requested_station_out_of_scope');

$schedEdit9 = ChecadorAccessService::authorizeScheduling(9, 'editar');
assertCheck($schedEdit9['allowed'] === false, 'Edición de programación para Autolavado (9) denegada');

$templateAdmin9 = ChecadorAccessService::authorizeStationTemplateAdministration(9);
assertCheck($templateAdmin9['allowed'] === true, 'Administración de plantillas M04 para Autolavado (9) conserva su autorización independiente');

// Scheduling authorization for Palo Solo must remain ALLOWED
$schedRead2 = ChecadorAccessService::authorizeScheduling(2, 'leer');
assertCheck($schedRead2['allowed'] === true, 'Lectura de programación para Palo Solo (2) autorizada');

$schedEdit2 = ChecadorAccessService::authorizeScheduling(2, 'editar');
assertCheck($schedEdit2['allowed'] === true, 'Edición de programación para Palo Solo (2) autorizada');

echo "\n=== TODAS LAS PRUEBAS UNITARIAS E INTEGRACIÓN PASARON EXITOSAMENTE ===\n";
