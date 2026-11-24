<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;
use App\Controllers\ChecadorComodinesController;
use App\Core\Database;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorV2PermissionService;
use App\Services\RolComodinesPortalWriterService;
use App\Services\ChecadorRolComodinesService;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

require_once __DIR__ . '/../app/Services/ChecadorRolComodinesService.php';
require_once __DIR__ . '/../app/Controllers/ChecadorComodinesController.php';

$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$service = $read('app/Services/ChecadorRolComodinesService.php');
$writer = $read('app/Services/RolComodinesPortalWriterService.php');
$controller = $read('app/Controllers/ChecadorComodinesController.php');
$checadorController = $read('app/Controllers/ChecadorController.php');
$permissions = $read('app/Services/ChecadorV2PermissionService.php');
$access = $read('app/Services/ChecadorAccessService.php');
$database = $read('app/Core/Database.php');
$routes = $read('routes/web.php');
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};
$constantList = static function (string $source, string $constant): array {
    if (preg_match('/public const ' . preg_quote($constant, '/') . ' = \[([^\]]*)\];/', $source, $matches) !== 1) return [];
    return array_map('intval', array_filter(array_map('trim', explode(',', $matches[1])), static fn(string $value): bool => $value !== ''));
};

$requiredRoutes = [
    "'/recursos-humanos/checador/rol-comodines', Route::auth(['ChecadorController', 'rolComodines'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['ChecadorComodinesController', 'coverageList'])",
    "'/recursos-humanos/checador/rol-comodines/trabajadores', Route::auth(['ChecadorComodinesController', 'searchCoverageWorkers'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid:[A-Za-z0-9-]{1,64}}', Route::auth(['ChecadorComodinesController', 'coverageDetail'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['ChecadorComodinesController', 'requestCoverage'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid:[A-Za-z0-9-]{1,64}}/autorizar', Route::auth(['ChecadorComodinesController', 'authorizeCoverage'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid:[A-Za-z0-9-]{1,64}}/cancelar', Route::auth(['ChecadorComodinesController', 'cancelCoverage'])",
];
$routesResolve = str_contains($routes, "addGroup('/departamento-operativo'");
foreach ($requiredRoutes as $route) $routesResolve = $routesResolve && str_contains($routes, $route);
$dispatcher = simpleDispatcher(require $root . '/routes/web.php');
$dispatches = [
    $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/rol-comodines'),
    $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas'),
    $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/rol-comodines/trabajadores'),
    $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas/00000000-0000-4000-8000-000000000000'),
    $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas'),
    $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas/00000000-0000-4000-8000-000000000000/autorizar'),
    $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas/00000000-0000-4000-8000-000000000000/cancelar'),
];
$routesResolve = $routesResolve && !in_array(false, array_map(
    static fn(array $route): bool => $route[0] === Dispatcher::FOUND,
    $dispatches
), true);
$assert($routesResolve, 'the authenticated page and coverage routes dispatch under the existing department route group');

$assert(class_exists(ChecadorController::class)
    && class_exists(ChecadorComodinesController::class)
    && class_exists(ChecadorV2PermissionService::class)
    && class_exists(RolComodinesPortalWriterService::class)
    && method_exists(ChecadorController::class, 'rolComodines')
    && method_exists(ChecadorComodinesController::class, 'coverageList')
    && method_exists(ChecadorComodinesController::class, 'requestCoverage')
    && method_exists(ChecadorComodinesController::class, 'authorizeCoverage')
    && method_exists(ChecadorComodinesController::class, 'cancelCoverage')
    && method_exists(ChecadorAccessService::class, 'authorizeBiometricosScheduling')
    && str_contains($checadorController, 'public function rolComodines(): void')
    && str_contains($controller, 'public function coverageList(): void')
    && str_contains($controller, 'public function requestCoverage(): void')
    && str_contains($controller, 'public function authorizeCoverage(string $coverageUuid): void')
    && str_contains($controller, 'public function cancelCoverage(string $coverageUuid): void')
    && str_contains($access, 'public static function authorizeBiometricosScheduling('),
    'the functional entry point and action handlers exist in this branch');
$assert(ChecadorRolComodinesService::COVERAGE_WORKER_POSITION_ID === 14
    && ChecadorRolComodinesService::COVERAGE_WORKER_ALLOWED_STATES === [0, 1]
    && ChecadorRolComodinesService::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS === [1, 2, 3, 4, 5, 6, 7, 14]
    && str_contains($service, "->where('puesto', self::COVERAGE_WORKER_POSITION_ID)")
    && str_contains($service, "->where('p.puesto', self::COVERAGE_WORKER_POSITION_ID)")
    && str_contains($service, "->whereIn('p.estado', self::COVERAGE_WORKER_ALLOWED_STATES)")
    && str_contains($service, "->whereIn('estado', self::COVERAGE_WORKER_ALLOWED_STATES)")
    && str_contains($writer, 'ChecadorRolComodinesService::COVERAGE_WORKER_ALLOWED_STATES')
    && str_contains($writer, 'puesto=? AND estado IN (?,?)')
    && $constantList($service, 'ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS') === [1, 2, 3, 4, 5, 6, 7, 14],
    'search and transactional revalidation use RH puesto 14 and estados 0/1 only for Comodines; the destination allowlist remains intact');
$assert(!str_contains($service, 'AUTHORIZED_COVERAGE_WORKER_IDS')
    && str_contains($service, "->where('p.nombre_completo', 'LIKE', \$pattern)")
    && str_contains($service, "CAST(p.no_colaborador AS CHAR)")
    && str_contains($service, "CAST(p.id AS CHAR)"),
    'the fixed worker roster is removed and searches still cover name, employee number, and RH ID');
$assert(str_contains($service, "->whereIn('estado', self::COVERAGE_WORKER_ALLOWED_STATES)")
    && str_contains($writer, '$this->assertHomeMembership($request)')
    && str_contains($writer, 'function assertHomeMembership(array $request)')
    && !str_contains($writer, 'INSERT INTO op_rh_personal_localidad_version')
    && !str_contains($writer, 'WorkerWorkplaceAssignmentVersionService::'),
    'Comodines source validation uses only owner-approved states and never creates canonical memberships');
$assert(str_contains($controller, '$positionId !== 13')
    && str_contains($controller, 'allowsComodines(')
    && str_contains($checadorController, '$positionId !== 13')
    && str_contains($permissions, 'if ($positionId !== 13')
    && str_contains($permissions, 'self::COMODINES_CANCEL'),
    'server handlers cap all Comodines operations to Dirección and deny Encargado');
$assert(str_contains($writer, "'REQUESTED'")
    && str_contains($writer, "'AUTHORIZED'")
    && str_contains($writer, "'CANCELLED'")
    && str_contains($writer, 'idempotency_key_hash')
    && str_contains($writer, 'FOR UPDATE')
    && str_contains($writer, 'readback'),
    'requests, explicit authorization, cancellation, idempotency, locks and readback are present');
$assert(!method_exists(RolComodinesPortalWriterService::class, 'writesEnabled')
    && !str_contains($writer, 'CHECADOR_COMODINES_PORTAL_WRITES')
    && !str_contains($writer, 'isLocalDevelopmentDatabaseConnection')
    && !str_contains($writer, 'assertLocalDevelopmentDatabase')
    && !str_contains($checadorController, 'coverageWriterEnabled')
    && !str_contains($checadorController, 'isLocalDevelopmentDatabaseConnection')
    && !str_contains($database, 'COMODINES_DB_'),
    'canonical writer and capability UI do not depend on development flags or local-database identity');
$assert(str_contains($checadorController, 'COMODINES_REQUEST')
    && str_contains($checadorController, 'COMODINES_APPROVE')
    && str_contains($checadorController, 'COMODINES_CANCEL')
    && str_contains($controller, 'authorizeCoverageActor($user, ChecadorV2PermissionService::COMODINES_REQUEST)')
    && str_contains($writer, 'assertActor($actorId, ChecadorV2PermissionService::COMODINES_REQUEST)')
    && str_contains($writer, 'assertScope(')
    && str_contains($writer, 'assertHomeMembership('),
    'request, approval and cancellation retain independent server permissions, authorized scope, and canonical membership checks');
$assert(!str_contains($checadorController, 'ChecadorWorkplaceCoverageApiService')
    && !str_contains($controller, 'ChecadorWorkplaceCoverageApiService')
    && !str_contains($writer, 'http://')
    && !str_contains($writer, 'https://'),
    'Portal3 Comodines does not add an HTTP API dependency');

printf("CHECADOR_V2_COMODINES_CONTRACT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
