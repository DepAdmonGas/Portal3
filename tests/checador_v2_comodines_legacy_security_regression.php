<?php
declare(strict_types=1);

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/routes/web.php');
$legacyController = file_get_contents($root . '/app/Controllers/RolComodinesController.php');
$legacyService = file_get_contents($root . '/app/Services/RolComodinesService.php');
$checadorController = file_get_contents($root . '/app/Controllers/ChecadorComodinesController.php');
$checadorService = file_get_contents($root . '/app/Services/ChecadorRolComodinesService.php');

$legacyRoutes = [
    '/recursos-humanos/rol-comodines', '/recursos-humanos/rol-comodines/{id:\\d+}',
    '/recursos-humanos/rol-comodines/data', '/recursos-humanos/rol-comodines/detail',
    '/recursos-humanos/rol-comodines/add', '/recursos-humanos/rol-comodines/edit-assignment',
    '/recursos-humanos/rol-comodines/save-dates', '/recursos-humanos/rol-comodines/finalize',
    '/recursos-humanos/rol-comodines/delete', '/recursos-humanos/rol-comodines/pdf',
    '/recursos-humanos/rol-comodines/pdf/{id}',
];
$legacyRoutesPreserved = true;
foreach ($legacyRoutes as $path) {
    $legacyRoutesPreserved = $legacyRoutesPreserved
        && str_contains($routes, "'" . $path . "', Route::auth(['RolComodinesController'");
}
$assert($legacyRoutesPreserved, 'existing legacy URLs retain their authenticated controller and route mappings');

$legacyActionsPreserved = str_contains($legacyController, 'public function index()')
    && str_contains($legacyController, 'public function add()')
    && str_contains($legacyController, 'RolComodinesService::agregar()')
    && str_contains($legacyController, 'RolComodinesService::editarAsignacion(')
    && str_contains($legacyService, 'public static function agregar(): int')
    && str_contains($legacyService, 'RhRolComodines::')
    && !str_contains($legacyService, 'CANONICAL_API_WRITER_AVAILABLE');
$assert($legacyActionsPreserved, 'the pre-existing legacy implementation remains separate and was not replaced by Checador compatibility stubs');

$checadorRoutesIsolated = str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['ChecadorComodinesController', 'coverageList'])")
    && str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['ChecadorComodinesController', 'requestCoverage'])")
    && str_contains($checadorController, 'public function coverageList(): void')
    && str_contains($checadorController, 'public function requestCoverage(): void')
    && str_contains($checadorService, 'public const AUTHORIZED_COVERAGE_WORKER_IDS');
$assert($checadorRoutesIsolated, 'new Checador Comodines operations use their own controller and service boundary');

printf("CHECADOR_V2_COMODINES_LEGACY_ISOLATION: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
