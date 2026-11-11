<?php

declare(strict_types=1);

/**
 * Regression and verification suite for:
 * CHECADOR_V2_AUTOLAVADO_CONFIGURACION_FUNCIONAL
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
use App\Services\ChecadorConfigurationReadService;
use App\Services\BiometricosService;
use App\Services\ChecadorV2PermissionService;
use App\Controllers\ChecadorController;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();

function assertCheck(bool $condition, string $testName, string $details = ''): void {
    if (!$condition) {
        echo "[\033[31mFAIL\033[0m] {$testName} - {$details}\n";
        exit(1);
    }
    echo "[\033[32mPASS\033[0m] {$testName}\n";
}

function setTestSessionUser(int $userId): Usuario {
    Session::set('usuario', ['id' => $userId]);
    $reflAuth = new ReflectionClass(Auth::class);
    $userProp = $reflAuth->getProperty('user');
    $userProp->setAccessible(true);
    $userProp->setValue(null, null);
    $user = Usuario::find($userId);
    if (!$user) {
        throw new RuntimeException("Usuario {$userId} no encontrado");
    }
    return $user;
}

echo "=== INICIANDO VALIDACIÓN: CHECADOR_V2_AUTOLAVADO_CONFIGURACION_FUNCIONAL ===\n\n";

// =========================================================================
// TEST 1: Configuración de asistencia - Autolavado (9) vs Palo Solo (2)
// =========================================================================
echo "--- Test 1: Configuración de asistencia autorizada para Encargado Palo Solo (User 52) ---\n";
$user52 = setTestSessionUser(52);

// User 52 on Autolavado (9)
$policyRead9 = ChecadorAccessService::authorizeAttendancePolicy(9, 'leer');
assertCheck(($policyRead9['allowed'] ?? false) === true, 'Lectura de configuración para Autolavado (9) autorizada para Encargado');
assertCheck((int) ($policyRead9['workplaces'][0]['id'] ?? 0) === 9, 'Workplace autorizado es Autolavado (9)');

$policyEdit9 = ChecadorAccessService::authorizeAttendancePolicy(9, 'editar');
assertCheck(($policyEdit9['allowed'] ?? false) === true, 'Edición de configuración para Autolavado (9) autorizada para Encargado');

// User 52 on Palo Solo (2)
$policyRead2 = ChecadorAccessService::authorizeAttendancePolicy(2, 'leer');
assertCheck(($policyRead2['allowed'] ?? false) === true, 'Lectura de configuración para Palo Solo (2) autorizada');
$policyEdit2 = ChecadorAccessService::authorizeAttendancePolicy(2, 'editar');
assertCheck(($policyEdit2['allowed'] ?? false) === true, 'Edición de configuración para Palo Solo (2) autorizada');

// =========================================================================
// TEST 2: Perfil del lector - Referencia compartida sin credenciales editables
// =========================================================================
echo "\n--- Test 2: Perfil del lector - Referencia compartida sin credenciales editables ---\n";
// Read reader profile on Autolavado
$readerRead9 = ChecadorAccessService::authorizeReaderProfiles(9, 'leer');
assertCheck(($readerRead9['allowed'] ?? false) === true, 'Lectura de perfil del lector en Autolavado autorizada');

// Edit reader profile on Autolavado must be DENIED (shared reader edit not authorized)
$readerEdit9 = ChecadorAccessService::authorizeReaderProfiles(9, 'editar');
assertCheck(($readerEdit9['allowed'] ?? false) === false, 'Edición de credenciales de lector compartido desde Autolavado DENEGADA');
assertCheck(($readerEdit9['reason'] ?? '') === 'shared_reader_edit_not_authorized', 'Razón es shared_reader_edit_not_authorized');

// Profile content for Autolavado: references Palo Solo profile
$profilesAutolavado = BiometricosService::readM11ReaderProfiles(9);
assertCheck(($profilesAutolavado['status'] ?? '') === 'shared_station_reference', 'Estado de perfiles en Autolavado es shared_station_reference');
assertCheck(count($profilesAutolavado['items'] ?? []) >= 1, 'Autolavado consulta al menos 1 perfil activo de la estación anfitriona');
assertCheck(($profilesAutolavado['items'][0]['usuario'] ?? '') === 'Sensor6007', 'Usuario del lector es Sensor6007 (Palo Solo)');
assertCheck(($profilesAutolavado['items'][0]['shared'] ?? false) === true, 'El perfil está explícitamente marcado como shared = true');
assertCheck(($profilesAutolavado['items'][0]['shared_from'] ?? '') === 'Palo Solo', 'shared_from es Palo Solo');
assertCheck(!isset($profilesAutolavado['items'][0]['password']), 'No se expone password');
assertCheck(!isset($profilesAutolavado['items'][0]['token']), 'No se expone token');

// Profile content for Palo Solo: station_scoped, shared = false
$profilesPaloSolo = BiometricosService::readM11ReaderProfiles(2);
assertCheck(($profilesPaloSolo['status'] ?? '') === 'station_scoped', 'Estado de perfiles en Palo Solo es station_scoped');
assertCheck(($profilesPaloSolo['items'][0]['shared'] ?? false) === false, 'Palo Solo tiene shared = false');

// =========================================================================
// TEST 3: Fail-closed para usuario no autorizado (User 33 - Interlomas)
// =========================================================================
echo "\n--- Test 3: Restricción para usuario no autorizado (User 33) ---\n";
$user33 = setTestSessionUser(33);

$user33Policy9 = ChecadorAccessService::authorizeAttendancePolicy(9, 'leer');
assertCheck(($user33Policy9['allowed'] ?? false) === false, 'User 33 DENEGADO para consultar configuración de Autolavado (9)');
assertCheck(($user33Policy9['reason'] ?? '') === 'requested_station_out_of_scope', 'Razón es requested_station_out_of_scope');

$user33Reader9 = ChecadorAccessService::authorizeReaderProfiles(9, 'leer');
assertCheck(($user33Reader9['allowed'] ?? false) === false, 'User 33 DENEGADO para consultar perfil de lector de Autolavado');

// =========================================================================
// TEST 4: Aislamiento total de configuración de asistencia e historial
// =========================================================================
echo "\n--- Test 4: Aislamiento total de configuración de asistencia e historial ---\n";
setTestSessionUser(52);
$dailyReader = new ChecadorDailyReadService();

// Autolavado attendance policy
$autolavadoCurrent = $dailyReader->readCurrentAttendancePolicy($user52, 9);
assertCheck(($autolavadoCurrent['ok'] ?? false) === true, 'Lectura de política de Autolavado en API V2 exitosa');
assertCheck((int) ($autolavadoCurrent['data']['workplace_id'] ?? 0) === 9, 'Workplace ID es 9');
assertCheck(($autolavadoCurrent['data']['latest_scheduled_policy'] ?? null) !== null, 'Autolavado tiene próxima configuración programada');
assertCheck((int) ($autolavadoCurrent['data']['latest_scheduled_policy']['retardo_tolerance_minutes'] ?? 0) === 15, 'Tolerancia de Autolavado es 15 min');
assertCheck((int) ($autolavadoCurrent['data']['latest_scheduled_policy']['incidence_deadline_days'] ?? 0) === 5, 'Plazo de incidencias de Autolavado es 5 días');

$autolavadoEffective = $dailyReader->readAttendancePolicy($user52, 9, '2026-11-07');
assertCheck(($autolavadoEffective['ok'] ?? false) === true, 'Lectura de política de Autolavado para fecha de vigencia exitosa');
assertCheck(($autolavadoEffective['data']['status'] ?? '') === 'VERSIONED', 'Autolavado tiene status VERSIONED para su fecha de vigencia');

// Palo Solo attendance policy
$paloSoloCurrent = $dailyReader->readCurrentAttendancePolicy($user52, 2);
assertCheck(($paloSoloCurrent['ok'] ?? false) === true, 'Lectura de política de Palo Solo en API V2 exitosa');
assertCheck((int) ($paloSoloCurrent['data']['workplace_id'] ?? 0) === 2, 'Workplace ID es 2');
assertCheck(($paloSoloCurrent['data']['status'] ?? '') === 'LEGACY_UNVERSIONED', 'Palo Solo conserva su estado independiente (LEGACY_UNVERSIONED)');
assertCheck(($paloSoloCurrent['data']['latest_scheduled_policy'] ?? null) === null, 'Palo Solo NO tiene la versión de Autolavado');

// =========================================================================
// TEST 5: Administración de dispositivos (fuente física conservada)
// =========================================================================
echo "\n--- Test 5: Administración de dispositivos - Fuente física conservada ---\n";
// Palo Solo query
$_GET['workplace_id'] = '2';
ob_start();
$ctrl = new ChecadorController();
$ctrl->administrativeDevicesData();
$jsonDevices2 = json_decode((string) ob_get_clean(), true);

assertCheck(($jsonDevices2['status'] ?? '') === 'OK', 'Respuesta de dispositivos para Palo Solo es OK');
assertCheck(count($jsonDevices2['data'] ?? []) >= 1, 'Palo Solo contiene al menos 1 dispositivo físico');
assertCheck(($jsonDevices2['data'][0]['device']['name'] ?? '') === 'Biometrico 100', 'Dispositivo es Biometrico 100');
assertCheck((int) ($jsonDevices2['data'][0]['physical_station']['id'] ?? 0) === 2, 'Estación física es Palo Solo (2)');
assertCheck(($jsonDevices2['data'][0]['workplace']['name'] ?? '') === 'Palo Solo', 'Nombre de workplace es Palo Solo');

// Autolavado query
$_GET['workplace_id'] = '9';
ob_start();
$ctrl->administrativeDevicesData();
$jsonDevices9 = json_decode((string) ob_get_clean(), true);

assertCheck(($jsonDevices9['status'] ?? '') === 'OK', 'Respuesta de dispositivos para Autolavado es OK');
assertCheck(count($jsonDevices9['data'] ?? []) >= 1, 'Autolavado muestra referencia al dispositivo compartido');
assertCheck(($jsonDevices9['data'][0]['device']['name'] ?? '') === 'Biometrico 100', 'Dispositivo es el mismo físico (Biometrico 100)');
assertCheck((int) ($jsonDevices9['data'][0]['physical_station']['id'] ?? 0) === 2, 'Estación física permanece estrictamente Palo Solo (2), no inventada ni movida');
assertCheck(strpos((string) ($jsonDevices9['data'][0]['workplace']['name'] ?? ''), 'Lector compartido') !== false, 'Workplace indica Lector compartido con Autolavado');

// =========================================================================
// TEST 6: Dirección de Operaciones (User 19) - Solo lectura conforme a permisos
// =========================================================================
echo "\n--- Test 6: Dirección de Operaciones (User 19) - Solo lectura conforme a permisos ---\n";
setTestSessionUser(19);

// Dirección can read Autolavado
$dirPolicy9 = ChecadorAccessService::authorizeAttendancePolicy(9, 'leer');
assertCheck(($dirPolicy9['allowed'] ?? false) === true, 'Dirección autorizada para consultar configuración de Autolavado');

$dirReaderRead = ChecadorAccessService::authorizeReaderProfiles(9, 'leer');
assertCheck(($dirReaderRead['allowed'] ?? false) === true, 'Dirección autorizada para consultar perfil de lector en Autolavado');

// Dirección CANNOT edit reader profiles
$dirReaderEdit = ChecadorAccessService::authorizeReaderProfiles(9, 'editar');
assertCheck(($dirReaderEdit['allowed'] ?? false) === false, 'Dirección NO tiene permiso para editar perfiles de lector (solo lectura)');

echo "\n=== TODAS LAS PRUEBAS DE REGRESIÓN PASARON EXITOSAMENTE ===\n";
