<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;
use App\Controllers\RolComodinesController;
use App\Core\Database;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorV2PermissionService;
use App\Services\RolComodinesPortalWriterService;
use App\Services\RolComodinesService;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$service = $read('app/Services/RolComodinesService.php');
$writer = $read('app/Services/RolComodinesPortalWriterService.php');
$controller = $read('app/Controllers/RolComodinesController.php');
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
    "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['RolComodinesController', 'coverageList'])",
    "'/recursos-humanos/checador/rol-comodines/trabajadores', Route::auth(['RolComodinesController', 'searchCoverageWorkers'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid}', Route::auth(['RolComodinesController', 'coverageDetail'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['RolComodinesController', 'requestCoverage'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid}/authorize', Route::auth(['RolComodinesController', 'authorizeCoverage'])",
    "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid}/cancel', Route::auth(['RolComodinesController', 'cancelCoverage'])",
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
    $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas/00000000-0000-4000-8000-000000000000/authorize'),
    $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas/00000000-0000-4000-8000-000000000000/cancel'),
];
$routesResolve = $routesResolve && !in_array(false, array_map(
    static fn(array $route): bool => $route[0] === Dispatcher::FOUND,
    $dispatches
), true);
$assert($routesResolve, 'the authenticated page and coverage routes dispatch under the existing department route group');

$assert(class_exists(ChecadorController::class)
    && class_exists(RolComodinesController::class)
    && class_exists(ChecadorV2PermissionService::class)
    && class_exists(RolComodinesPortalWriterService::class)
    && method_exists(ChecadorController::class, 'rolComodines')
    && method_exists(RolComodinesController::class, 'coverageList')
    && method_exists(RolComodinesController::class, 'requestCoverage')
    && method_exists(RolComodinesController::class, 'authorizeCoverage')
    && method_exists(RolComodinesController::class, 'cancelCoverage')
    && method_exists(ChecadorAccessService::class, 'authorizeBiometricosScheduling')
    && method_exists(Database::class, 'isLocalDevelopmentDatabaseConnection')
    && str_contains($checadorController, 'public function rolComodines(): void')
    && str_contains($controller, 'public function coverageList(): void')
    && str_contains($controller, 'public function requestCoverage(): void')
    && str_contains($controller, 'public function authorizeCoverage(string $coverageUuid): void')
    && str_contains($controller, 'public function cancelCoverage(string $coverageUuid): void')
    && str_contains($access, 'public static function authorizeBiometricosScheduling(')
    && str_contains($database, 'public static function isLocalDevelopmentDatabaseConnection()'),
    'the functional entry point, action handlers and narrowly required shared methods exist in this branch');
$assert(RolComodinesService::AUTHORIZED_COVERAGE_WORKER_IDS === [96, 100, 102, 310, 432, 452, 472, 491, 500, 512]
    && RolComodinesService::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS === [1, 2, 3, 4, 5, 6, 7, 14]
    && $constantList($service, 'AUTHORIZED_COVERAGE_WORKER_IDS') === [96, 100, 102, 310, 432, 452, 472, 491, 500, 512]
    && $constantList($service, 'ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS') === [1, 2, 3, 4, 5, 6, 7, 14],
    'the exact authorized worker roster and eight destination allowlist remain intact');
$assert(str_contains($service, "->where('estado', 1)")
    && str_contains($writer, '$this->assertHomeMembership($request)')
    && str_contains($writer, 'function assertHomeMembership(array $request)')
    && !str_contains($writer, 'INSERT INTO op_rh_personal_localidad_version')
    && !str_contains($writer, 'WorkerWorkplaceAssignmentVersionService::'),
    'workers must be active and have canonical membership; Comodines never creates memberships');
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
$assert(RolComodinesPortalWriterService::writesEnabled('dev', '1') === true
    && RolComodinesPortalWriterService::writesEnabled('production', '1') === false
    && RolComodinesPortalWriterService::writesEnabled('dev', '0') === false
    && str_contains($writer, "in_array(\$environment, ['dev', 'development', 'local'], true)")
    && str_contains($writer, "trim(\$flag) === '1'")
    && str_contains($database, "['localhost', '127.0.0.1', '::1']")
    && str_contains($database, "'bd_admongas'"),
    'Portal3 writer stays gated to explicit local development configuration and verified local bd_admongas');
$assert(!str_contains($checadorController, 'ChecadorWorkplaceCoverageApiService')
    && !str_contains($controller, 'ChecadorWorkplaceCoverageApiService')
    && !str_contains($writer, 'http://')
    && !str_contains($writer, 'https://'),
    'Portal3 Comodines does not add an HTTP API dependency');

printf("CHECADOR_V2_COMODINES_CONTRACT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
