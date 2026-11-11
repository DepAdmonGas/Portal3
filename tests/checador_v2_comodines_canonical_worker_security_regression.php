<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$routes = $read('routes/web.php');
$controller = $read('app/Controllers/RolComodinesController.php');
$checadorController = $read('app/Controllers/ChecadorController.php');
$service = $read('app/Services/RolComodinesService.php');
$writer = $read('app/Services/RolComodinesPortalWriterService.php');
$apiService = $read('app/Services/ChecadorWorkplaceCoverageApiService.php');
$layout = $read('app/Views/layouts/departamento-operativo.php');
$checadorIndex = $read('app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$migration = $read('scripts/migrate_checador_comodines_canonical_worker.php');
$view = $read('app/Views/departamento-operativo/2-recursos-humanos/checador/role-comodines.php');
$attendance = $read('app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$assert(str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['RolComodinesController', 'coverageList'])")
    && str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas', Route::auth(['RolComodinesController', 'requestCoverage'])")
    && str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid}/authorize', Route::auth(['RolComodinesController', 'authorizeCoverage'])")
    && str_contains($routes, "'/recursos-humanos/checador/rol-comodines/coberturas/{coverageUuid}/cancel', Route::auth(['RolComodinesController', 'cancelCoverage'])"),
    'Portal3 coverage routes remain authenticated and CSRF-protected');
$assert(str_contains($controller, 'public function coverageList(): void')
    && str_contains($controller, 'public function coverageDetail(string $coverageUuid): void')
    && str_contains($controller, 'private function authorizeCoverageActor(')
    && str_contains($controller, '$positionId !== 13')
    && str_contains($controller, 'allowsComodines(')
    && !str_contains($controller, "(int) (\$user->id_puesto ?? 0) !== 6")
    && str_contains($controller, '$this->portalScopeReady()')
    && str_contains($controller, 'if ($authorizedWorkplaceIds === [])')
    && !str_contains($controller, 'in_array(9, $authorizedWorkplaceIds, true)')
    && str_contains($controller, 'in_array((int) $homeWorkplaceId, $this->authorizedWorkplaceIds(), true)')
    && str_contains($controller, '$this->authorizedWorkplaceIds()')
    && str_contains($writer, '$this->assertHomeMembership($request)')
    && str_contains($writer, 'self::assertScope($scope, $request[\'id_home_workplace\'], $request[\'id_coverage_workplace\'])')
    && str_contains($checadorController, '$coverageWriter->listVisible($authorizedWorkplaceIds)')
    && !str_contains($checadorController, '->listAll(Auth::user())'),
    'Portal3 applies role ceilings, validates canonical origin against server scope, and reads the shared canonical database directly');
$comodinesCardStart = strpos($checadorController, "'label' => 'Rol de comodines'");
$comodinesCardEnd = $comodinesCardStart === false ? false : strpos($checadorController, "            ],", $comodinesCardStart);
$comodinesCard = $comodinesCardStart !== false && $comodinesCardEnd !== false
    ? substr($checadorController, $comodinesCardStart, $comodinesCardEnd - $comodinesCardStart)
    : '';
$sidebarStart = strpos($layout, "href=\"/departamento-operativo/recursos-humanos/checador/rol-comodines\"");
$sidebarStart = $sidebarStart === false ? false : strrpos(substr($layout, 0, $sidebarStart), '<?php if');
$sidebarEnd = $sidebarStart === false ? false : strpos($layout, '?>', $sidebarStart);
$sidebarGuard = $sidebarStart !== false && $sidebarEnd !== false ? substr($layout, $sidebarStart, $sidebarEnd - $sidebarStart) : '';
$roleRouteStart = strpos($checadorController, 'public function rolComodines(): void');
$roleRouteEnd = $roleRouteStart === false ? false : strpos($checadorController, '$coverageRead =', $roleRouteStart);
$roleRouteGuard = $roleRouteStart !== false && $roleRouteEnd !== false
    ? substr($checadorController, $roleRouteStart, $roleRouteEnd - $roleRouteStart)
    : '';
$assert($comodinesCard !== ''
    && str_contains($comodinesCard, "'available' => \$comodinesRead")
    && !str_contains($comodinesCard, 'isEnabled()')
    && str_contains($checadorController, '$coverageWriterEnabled = \\App\\Services\\RolComodinesPortalWriterService::writesEnabled()')
    && str_contains($view, 'Las operaciones administrativas de cobertura están desactivadas en este entorno.')
    && str_contains($sidebarGuard, 'id_puesto ?? 0) === 13')
    && str_contains($sidebarGuard, 'COMODINES_READ')
    && !str_contains($sidebarGuard, 'isEnabled()')
    && str_contains($checadorController, "if (!\$comodinesRead)")
    && str_contains($checadorController, "\$entry['label'] ?? '') !== 'Rol de comodines'")
    && str_contains($roleRouteGuard, 'COMODINES_READ')
    && !str_contains($roleRouteGuard, 'isEnabled()')
    && str_contains($checadorIndex, 'data-checador-module-card'),
    'Direction card and sidebar depend on read authorization, not the writer gate; gate-off state remains visible');
$comodinesKeys = [
    \App\Services\ChecadorV2PermissionService::COMODINES_READ,
    \App\Services\ChecadorV2PermissionService::COMODINES_REQUEST,
    \App\Services\ChecadorV2PermissionService::COMODINES_APPROVE,
    \App\Services\ChecadorV2PermissionService::COMODINES_CANCEL,
];
$assert(array_reduce($comodinesKeys, static fn(bool $denied, string $key): bool =>
        $denied && !\App\Services\ChecadorV2PermissionService::allowsComodines(52, 6, $key), true)
    && !\App\Services\ChecadorV2PermissionService::allowsComodines(52, 13, 'checador.comodines.unknown')
    && !\App\Services\ChecadorV2PermissionService::effectiveGrantDecision(
        ['active' => 0, 'granted' => 0], ['active' => 1, 'granted' => 1]
    ), 'position 6 is denied every Comodines capability and a direct revocation overrides a position grant');
$writerStart = strpos($service, 'public static function createCanonicalProgramming(');
$writerEnd = strpos($service, 'public static function getCanonicalScheduledWorkersForDate(', max(0, $writerStart));
$retiredWriter = $writerStart !== false && $writerEnd !== false ? substr($service, $writerStart, $writerEnd - $writerStart) : '';
$legacyMutationStart = strpos($service, 'public static function agregar(): int');
$legacyMutationEnd = strpos($service, 'public static function notificarCreacion(', max(0, $legacyMutationStart));
$legacyMutators = $legacyMutationStart !== false && $legacyMutationEnd !== false ? substr($service, $legacyMutationStart, $legacyMutationEnd - $legacyMutationStart) : '';
$assert($retiredWriter !== '' && str_contains($retiredWriter, "'reason' => 'canonical_api_required'")
    && !str_contains($retiredWriter, 'Capsule::connection()->transaction')
    && $legacyMutators !== '' && !str_contains($legacyMutators, 'RhRolComodines::create')
    && !str_contains($legacyMutators, 'RhComodinesDia::create') && !str_contains($legacyMutators, '->save()')
    && !str_contains($legacyMutators, '->delete()'),
    'Legacy RolComodinesService writes remain retired and its historical handlers contain no direct persistence');
$assert(str_contains($apiService, "public function requestCoverage(")
    && str_contains($apiService, "public function transition(")
    && str_contains($apiService, "return self::failure('coverage_writer_portal3_only', 410)")
    && str_contains($controller, 'new \\App\\Services\\RolComodinesPortalWriterService())->createRequest(')
    && str_contains($controller, 'new \\App\\Services\\RolComodinesPortalWriterService())->transition('),
    'the old Portal3 HTTP mutation proxy is retired and active administration uses the Portal3 DB writer');
$assert(str_contains($service, "->where('numlista', \$numlista)->count() !== 1")
    && str_contains($service, "'station_mapping_unavailable'"),
    'workplace-to-station mapping fails closed unless numlista is unique');
$assert(str_contains($view, 'Coberturas registradas')
    && str_contains($checadorController, '\\App\\Services\\RolComodinesPortalWriterService::writesEnabled()')
    && str_contains($checadorController, '$coverageWriter->templateCatalog()')
    && str_contains($view, '<?php if ($canRequestCoverage): ?>')
    && str_contains($view, 'id="checador-comodines-create-modal"')
    && str_contains($view, 'data-create-url=')
    && !str_contains($routes, "'/recursos-humanos/checador/rol-comodines/programaciones'")
    && str_contains($routes, "'/recursos-humanos/checador/rol-comodines/trabajadores', Route::auth(['RolComodinesController', 'searchCoverageWorkers'])")
    && str_contains($controller, 'public function searchCoverageWorkers(): void')
    && str_contains($controller, 'COMODINES_REQUEST'),
    'canonical request UI is Portal3-writer-gated and permission-conditional while worker search keeps its server-side ACL');
$assert(str_contains($checadorController, 'RolComodinesService::authorizeCanonicalProgramming(')
    && str_contains($checadorController, 'getCanonicalScheduledWorkersForDate(')
    && str_contains($attendance, 'Comodín programado · autorización no acreditada')
    && str_contains($attendance, 'Marcación observada'),
    'daily attendance integrates validated programs while keeping observed marks distinct');
$assert(str_contains($checadorController, 'foreach ($scheduledComodines[\'workers\'] ?? [] as $scheduledProfile)')
    && str_contains($checadorController, '$profiles[$scheduledWorkerId] ??= $scheduledProfile;')
    && str_contains($checadorController, '$attendanceRows[(int) $workerId]'),
    'scheduled and observed workers enter one canonical-ID keyed attendance row without duplication');
$assert(str_contains($migration, "'bd_checador_v2_comodines_test'")
    && str_contains($migration, "'127.0.0.1'")
    && str_contains($migration, "ADD COLUMN id_personal INT(11) NULL AFTER id_usuario")
    && str_contains($migration, 'ON DELETE RESTRICT ON UPDATE RESTRICT'),
    'candidate migration is restricted to its disposable database and preserves the legacy identity field');
$assert(!str_contains($read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.datatable.init.js'), 'api/v2/workplace-coverages')
    && str_contains($read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.datatable.init.js'), 'DataTable'),
    'the browser remains a Portal3 client and does not call API V2 directly');
$assert(str_contains($controller, "public function index(): void")
    && str_contains($controller, '$this->closeLegacyWrite(')
    && !str_contains($controller, 'RolComodinesService::add('),
    'legacy read and write endpoints remain closed');

printf("CHECADOR_V2_COMODINES_CANONICAL_WORKER_SECURITY: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
