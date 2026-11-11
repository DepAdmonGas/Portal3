<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorWeeklyScheduleService;
use App\Services\ModuleStationService;
use Illuminate\Database\Capsule\Manager as Capsule;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();
if (session_status() === PHP_SESSION_NONE) session_start();

echo "=== INICIANDO VALIDACIÓN: CHECADOR_V2_M03_AUTOLAVADO_IMPLEMENTACION_FUNCIONAL ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCheck(bool $condition, string $label): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $label\n";
        $passCount++;
    } else {
        echo "[FAIL] $label\n";
        $failCount++;
    }
}

$setAuthUser = function(int $userId): Usuario {
    Auth::forget();
    Session::set('usuario', ['id' => $userId]);
    $user = Usuario::find($userId);
    Session::set('usuario', ['id' => $user->id, 'id_estacion' => $user->id_gas, 'id_puesto' => $user->id_puesto]);
    Session::set('id_estacion', $user->id_gas);
    return $user;
};

// =========================================================================
// 1. AUTORIZACIÓN Y ALCANCE (ACL)
// =========================================================================
echo "--- 1. Autorización y Alcance (ACL) ---\n";
// User 52: Encargado Palo Solo + Autolavado
$u52 = $setAuthUser(52);
$auth52Read = ChecadorAccessService::authorizeScheduling(9, 'leer');
$auth52Edit = ChecadorAccessService::authorizeScheduling(9, 'editar');
$auth52PaloSolo = ChecadorAccessService::authorizeScheduling(2, 'editar');

assertCheck(($auth52Read['allowed'] ?? false) === true, 'Encargado Palo Solo (52) autorizado para LEER M03 en Autolavado (9)');
assertCheck(($auth52Edit['allowed'] ?? false) === true, 'Encargado Palo Solo (52) autorizado para EDITAR M03 en Autolavado (9)');
assertCheck(($auth52PaloSolo['allowed'] ?? false) === true, 'Encargado Palo Solo (52) autorizado para EDITAR M03 en Palo Solo (2)');

// User 19: Dirección de Operaciones -> Solo lectura
$u19 = $setAuthUser(19);
$auth19Read = ChecadorAccessService::authorizeScheduling(9, 'leer');
$auth19Edit = ChecadorAccessService::authorizeScheduling(9, 'editar');

assertCheck(($auth19Read['allowed'] ?? false) === true, 'Dirección de Operaciones (19) autorizada para LEER M03 en Autolavado (9)');
assertCheck(($auth19Edit['allowed'] ?? false) === false, 'Dirección de Operaciones (19) DENEGADA para editar M03 en Autolavado (solo lectura)');
assertCheck(($auth19Edit['reason'] ?? '') === 'scheduling_permission_denied', 'Razón para Dirección en edición es scheduling_permission_denied');

// User 33: Encargado Interlomas -> Sin acceso a Autolavado
$u33 = $setAuthUser(33);
$auth33Read = ChecadorAccessService::authorizeScheduling(9, 'leer');
assertCheck(($auth33Read['allowed'] ?? false) === false, 'Encargado Interlomas (33) DENEGADO para Autolavado (9)');
assertCheck(($auth33Read['reason'] ?? '') === 'requested_station_out_of_scope', 'Razón para User 33 es requested_station_out_of_scope');

// =========================================================================
// 2. PADRÓN DE TRABAJADORES DE AUTOLAVADO
// =========================================================================
echo "\n--- 2. Padrón de Trabajadores de Autolavado ---\n";
$setAuthUser(52);
$rosterService = new ChecadorAttendanceRosterService();
$roster9 = $rosterService->workersForScheduleWeek(9, '2026-11-09', 'America/Mexico_City', false);

assertCheck(in_array(($roster9['status'] ?? ''), ['ready', 'partial'], true), 'Estado de padrón de Autolavado es válido');
$workersCount = count($roster9['workers'] ?? []);
assertCheck($workersCount >= 18, "Se encontraron $workersCount colaboradores adscritos a Autolavado (>= 18)");

// Verify workers belong strictly to Autolavado
$workerIds = array_column($roster9['workers'] ?? [], 'worker_id');
assertCheck(in_array(37, $workerIds, true), 'Incluye a Fredy Noe Ramirez Antonio (ID 37, Encargado Autolavado)');
assertCheck(in_array(39, $workerIds, true), 'Incluye a Gerardo Lorenzo Lopez (ID 39, Lavador de Autos)');
assertCheck(in_array(49, $workerIds, true), 'Incluye a Bryan Ramirez Gonzales (ID 49, Lavador de Autos)');
assertCheck(in_array(51, $workerIds, true), 'Incluye a Emanuel García Ayala (ID 51, Lavador de Autos)');
assertCheck(in_array(1009, $workerIds, true), 'Incluye al colaborador QA 1009 con membresía versionada');

// =========================================================================
// 3. AISLAMIENTO CON PALO SOLO (CERO MEZCLA)
// =========================================================================
echo "\n--- 3. Aislamiento con Palo Solo ---\n";
$roster2 = $rosterService->workersForScheduleWeek(2, '2026-11-09', 'America/Mexico_City', false);
$paloSoloIds = array_column($roster2['workers'] ?? [], 'worker_id');

assertCheck(!in_array(39, $paloSoloIds, true), 'Gerardo Lorenzo Lopez (ID 39) de Autolavado NO está en Palo Solo');
assertCheck(!in_array(49, $paloSoloIds, true), 'Bryan Ramirez Gonzales (ID 49) de Autolavado NO está en Palo Solo');
assertCheck(!in_array(51, $paloSoloIds, true), 'Emanuel García Ayala (ID 51) de Autolavado NO está en Palo Solo');
assertCheck(in_array(471, $paloSoloIds, true), 'Palo Solo conserva su propio personal independiente (v.gr. ID 471)');

// =========================================================================
// 4. CATÁLOGO DE TURNOS DE AUTOLAVADO
// =========================================================================
echo "\n--- 4. Catálogo de Turnos de Autolavado ---\n";
$dailyReader = new ChecadorDailyReadService();
$templatesResult = $dailyReader->getShiftTemplates($u52, 1, 100, 9);
assertCheck(($templatesResult['ok'] ?? false) === true, 'Lectura del catálogo de turnos de Autolavado en API V2 exitosa');
$templateItems = $templatesResult['data']['items'] ?? [];
$templateKeys = array_column($templateItems, 'clave');

assertCheck(in_array('LOC_9_T_1_07001600', $templateKeys, true), 'Contiene Turno 1 propio de Autolavado (LOC_9_T_1_07001600)');
assertCheck(in_array('LOC_9_T_2_10001900', $templateKeys, true), 'Contiene Turno 2 propio de Autolavado (LOC_9_T_2_10001900)');
assertCheck(in_array('LOC_9_T_4_18000300', $templateKeys, true), 'Contiene Turno 4 propio de Autolavado (LOC_9_T_4_18000300)');

foreach ($templateItems as $t) {
    assertCheck((int) ($t['id_estacion'] ?? 0) === 9, "Turno {$t['clave']} pertenece estrictamente a id_estacion 9");
}

// Verify Palo Solo templates are isolated
$templatesPaloSolo = $dailyReader->getShiftTemplates($u52, 1, 100, 2);
$paloSoloKeys = array_column($templatesPaloSolo['data']['items'] ?? [], 'clave');
assertCheck(!in_array('LOC_9_T_1_07001600', $paloSoloKeys, true), 'Turnos de Autolavado NO aparecen en el catálogo de Palo Solo');

// =========================================================================
// 5. ASIGNACIONES, READBACK Y MODIFICACIONES
// =========================================================================
echo "\n--- 5. Asignaciones, Readback y Modificaciones (QA Worker 1009) ---\n";
$db = Capsule::connection();
$assignments1009 = $db->table('op_rh_v2_turnos_asignaciones')->where('id_personal', 1009)->get();
assertCheck(count($assignments1009) >= 1, 'Trabajador QA 1009 tiene asignaciones registradas en Autolavado');

// Readback from API V2
$apiAssignments = $dailyReader->getShiftAssignments($u52, 1009, 9, 1, 200);
assertCheck(($apiAssignments['ok'] ?? false) === true, 'Lectura de asignaciones de trabajador 1009 en API V2 exitosa');
$items = $apiAssignments['data']['items'] ?? [];
assertCheck(count($items) >= 1, 'API V2 devuelve las asignaciones de Autolavado');

// Check habitual and temporary assignments
$habitualItems = array_filter($items, static fn($i): bool => ($i['tipo_asignacion'] ?? '') === 'HABITUAL');
$temporaryItems = array_filter($items, static fn($i): bool => ($i['tipo_asignacion'] ?? '') === 'TEMPORAL');
assertCheck(count($habitualItems) >= 1, 'Contiene asignación HABITUAL confirmada');
assertCheck(count($temporaryItems) >= 1, 'Contiene cambio TEMPORAL confirmado');

// Check that no ordinary worker was modified
$ordinaryTouched = $db->table('op_rh_v2_turnos_asignaciones')
    ->whereIn('id_personal', [37, 39, 45, 49, 51, 206, 262, 286, 330, 344, 354, 399, 405, 479, 480, 481, 483, 484])
    ->count();
assertCheck($ordinaryTouched === 0, 'Cero asignaciones creadas o alteradas para colaboradores ordinarios de Autolavado');

// =========================================================================
// 6. SQL DE INCORPORACIÓN INICIAL GENERADO
// =========================================================================
echo "\n--- 6. SQL de Incorporación Inicial ---\n";
$sqlFile = dirname(__DIR__) . '/sql/incorporacion_inicial_autolavado_membresias.sql';
assertCheck(file_exists($sqlFile), 'Archivo sql/incorporacion_inicial_autolavado_membresias.sql generado');
$sqlContent = file_get_contents($sqlFile);
assertCheck(strpos($sqlContent, '37, 9') !== false, 'SQL incluye trabajador 37');
assertCheck(strpos($sqlContent, '484, 9') !== false, 'SQL incluye trabajador 484');

echo "\n=== RESUMEN: $passCount PASS / $failCount FAIL ===\n";
exit($failCount === 0 ? 0 : 1);
