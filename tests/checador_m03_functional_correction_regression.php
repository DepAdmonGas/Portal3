<?php

declare(strict_types=1);

/**
 * Regression and verification suite for:
 * CHECADOR_V2_M03_CORRECCION_FUNCIONAL_DEFINITIVA
 */

session_start();
require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorWeeklyScheduleService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use Illuminate\Database\Capsule\Manager as Capsule;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();

function assertCheck(bool $condition, string $testName, string $details = ''): void {
    if (!$condition) {
        echo "[\033[31mFAIL\033[0m] {$testName} - {$details}\n";
        exit(1);
    }
    echo "[\033[32mPASS\033[0m] {$testName}\n";
}

echo "=== INICIANDO VALIDACIÓN: CHECADOR_V2_M03_CORRECCION_FUNCIONAL ===\n\n";

$weekStart = '2026-11-09';
$workplaceId = 1; // Interlomas
$timezone = 'America/Mexico_City';

// --- TEST 1: WORKER_1_MEMBERSHIP_RECOGNIZED ---
echo "--- Test 1: Reconocimiento de membresía del Trabajador ID 1 ---\n";
$membershipRow = Capsule::table('op_rh_personal_localidad_version')
    ->where('worker_id', 1)
    ->where('workplace_id', $workplaceId)
    ->first();

assertCheck($membershipRow !== null, 'Membresía existe en BD para trabajador 1');
assertCheck($membershipRow->recorded_by === null, 'recorded_by es NULL conforme a la situación reportada');
assertCheck(str_starts_with((string) $membershipRow->valid_from_utc, '2026-11-06 06:00:00'), 'valid_from_utc inicia el 2026-11-06 06:00:00');

$rosterService = new ChecadorAttendanceRosterService();
$roster = $rosterService->workersForScheduleWeek($workplaceId, $weekStart, $timezone, false);
assertCheck($roster['status'] === 'ready', 'Padrón de la semana está listo (ready)');

$worker1Roster = null;
foreach ($roster['workers'] as $w) {
    if ($w['worker_id'] === 1) {
        $worker1Roster = $w;
        break;
    }
}
assertCheck($worker1Roster !== null, 'Trabajador 1 está presente en el padrón de la semana');
assertCheck($worker1Roster['has_versioned_membership'] === true, 'has_versioned_membership es true para Trabajador 1');
assertCheck(count($worker1Roster['verified_dates']) === 7, 'Trabajador 1 tiene los 7 días verificados en la semana');
assertCheck(count($worker1Roster['versioned_verified_dates']) === 7, 'Trabajador 1 tiene 7 versioned_verified_dates');

// --- TEST 2: WORKER_1_ASSIGNMENT_READ_COMPLETE ---
echo "\n--- Test 2: Consulta autorizada y completa de asignaciones desde API V2 ---\n";
$reader = new ChecadorDailyReadService();
$encargadoUser = Usuario::find(33); // Encargado Interlomas

$apiAssignments1 = $reader->getShiftAssignments($encargadoUser, 1, $workplaceId, 1, 200);
assertCheck(($apiAssignments1['ok'] ?? false) === true, 'Consulta de asignaciones a API V2 exitosa (ok: true)');
assertCheck(is_array($apiAssignments1['data']['items'] ?? null), 'Respuesta contiene items');
assertCheck(count($apiAssignments1['data']['items']) === 0, 'Trabajador 1 tiene exactamente 0 asignaciones en la API');

// --- TEST 3: MATRIX PROJECTION AND WORKER_1_ASSIGN_HABITUAL_ACTION_VISIBLE ---
echo "\n--- Test 3: Proyección de matriz y menú de acciones por cobertura habitual ---\n";
$templatesRes = $reader->getShiftTemplates($encargadoUser, 1, 100, $workplaceId);
assertCheck(($templatesRes['ok'] ?? false) === true, 'Catálogo de plantillas disponible');
$templatesById = [];
foreach ($templatesRes['data']['items'] as $t) {
    $templatesById[(int)$t['id']] = $t;
}

// Build matrix for worker 1
$worker1Copy = $worker1Roster;
$worker1Copy['schedule_read_status'] = 'available';
$matrix = ChecadorWeeklyScheduleService::matrix([$worker1Copy], [1 => []], $templatesById, $weekStart);
$habitual1 = $matrix['habitual_workers'][0];

assertCheck($habitual1['habitual_coverage']['status'] === 'unassigned', 'Cobertura habitual de Trabajador 1 es unassigned');
assertCheck($habitual1['habitual_coverage']['label'] === 'Sin asignación', 'Etiqueta es "Sin asignación"');
assertCheck($habitual1['habitual_coverage']['covered_days'] === 0, 'Días cubiertos es 0/7');
assertCheck($habitual1['habitual_coverage']['assignable'] === true, 'assignable es true');

// Verify that cells show 'unassigned' rather than 'schedule_unavailable'
foreach ($habitual1['cells'] as $dayDate => $cell) {
    assertCheck($cell['status'] === 'unassigned', "Celda {$dayDate} tiene status unassigned");
    assertCheck($cell['label'] === 'Sin asignación visible' || $cell['label'] === 'Sin asignación', "Celda {$dayDate} no dice 'Horario no disponible'");
}

// --- TEST 4: READBACK ENDPOINT FOR MODAL ---
echo "\n--- Test 4: Endpoint de readback para el modal de asignación ---\n";
$reflAuth = new ReflectionClass(Auth::class);
$userProp = $reflAuth->getProperty('user');
$userProp->setAccessible(true);
$userProp->setValue(null, $encargadoUser);
Session::set('usuario', ['id' => 33]);

$readbackResult = $reader->getAllShiftAssignmentsSnapshot($encargadoUser, 1, $workplaceId, null, 200);
assertCheck(($readbackResult['ok'] ?? false) === true, 'getAllShiftAssignmentsSnapshot exitoso');
assertCheck(($readbackResult['data']['complete'] ?? false) === true, 'complete es true');
assertCheck(count($readbackResult['data']['items']) === 0, 'items es vacío para trabajador 1');

// --- TEST 5: DIRECCION READ ONLY ---
echo "\n--- Test 5: Dirección de Operaciones permanece en solo lectura ---\n";
$userProp->setValue(null, null);
Session::set('usuario', ['id' => 19]);
$dirAccess = ChecadorAccessService::authorizeScheduling($workplaceId, 'editar');
assertCheck(($dirAccess['allowed'] ?? false) === false, 'Dirección de Operaciones NO tiene permiso para editar programación');

// --- TEST 6: WORKER 1 CONTINUES WITHOUT ASSIGNMENTS ---
echo "\n--- Test 6: Trabajador 1 conserva 0 asignaciones en base de datos ---\n";
$w1DbCount = Capsule::table('op_rh_v2_turnos_asignaciones')
    ->where('id_personal', 1)
    ->where('id_estacion', $workplaceId)
    ->count();
assertCheck($w1DbCount === 0, 'Trabajador 1 tiene exactamente 0 asignaciones en MariaDB');

echo "\n=== TODAS LAS PRUEBAS DE REGRESIÓN PASARON EXITOSAMENTE ===\n";
