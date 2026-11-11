<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\RolComodinesPortalWriterService;
use App\Models\Usuario;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$portalController = file_get_contents(__DIR__ . '/../app/Controllers/RolComodinesController.php');
$pageController = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$writer = file_get_contents(__DIR__ . '/../app/Services/RolComodinesPortalWriterService.php');
$apiProxy = file_get_contents(__DIR__ . '/../app/Services/ChecadorWorkplaceCoverageApiService.php');

$oldAppEnv = $_ENV['APP_ENV'] ?? null;
$oldGate = $_ENV['CHECADOR_COMODINES_PORTAL_WRITES'] ?? null;
$oldProcessAppEnv = getenv('APP_ENV');
$oldProcessGate = getenv('CHECADOR_COMODINES_PORTAL_WRITES');
$_ENV['APP_ENV'] = 'dev';
unset($_ENV['CHECADOR_COMODINES_PORTAL_WRITES']);
putenv('CHECADOR_COMODINES_PORTAL_WRITES');
$assert(RolComodinesPortalWriterService::writesEnabled() === false,
    'Portal3 coverage writer remains closed by default in development');
$blockedCreate = (new RolComodinesPortalWriterService())->createRequest(13, [], [], 'idempotency-key-not-used');
$blockedTransition = (new RolComodinesPortalWriterService())->transition(
    'daaa2c2b-f936-4b0e-bf7e-20376cb90739', 'AUTHORIZE', 13, [], 'No operation', 'idempotency-key-not-used'
);
$assert(($blockedCreate['code'] ?? '') === 'feature_disabled' && ($blockedCreate['http_status'] ?? 0) === 503,
    'create request fails closed before database access while the Portal3 gate is off');
$assert(($blockedTransition['code'] ?? '') === 'feature_disabled' && ($blockedTransition['http_status'] ?? 0) === 503,
    'coverage transition fails closed before database access while the Portal3 gate is off');
$assert(RolComodinesPortalWriterService::writesEnabled('production', '1') === false,
    'the writer cannot be enabled outside development environments');

$assert(is_string($portalController) && str_contains($portalController, '->listVisible(')
    && str_contains($portalController, '->createRequest(')
    && str_contains($portalController, '->transition('),
    'Portal3 controller routes coverage reads and all mutations through its canonical DB service');
$assert(is_string($pageController) && str_contains($pageController, '$coverageWriter->listVisible($authorizedWorkplaceIds)')
    && str_contains($pageController, '->templateCatalog()')
    && !str_contains($pageController, '->listAll(Auth::user())'),
    'the M07 page no longer depends on the API HTTP reader for coverage lists');
$assert(is_string($writer) && str_contains($writer, 'Capsule::connection()->transaction(')
    && str_contains($writer, 'isLocalDevelopmentDatabaseConnection()')
    && str_contains($writer, 'UTC_TIMESTAMP(6)') && str_contains($writer, 'idempotency_key_hash')
    && str_contains($writer, 'id_cobertura_version')
    && str_contains($writer, 'op_rh_v2_turnos_asignaciones')
    && !str_contains($writer, 'op_rh_v2_comodines_assignments_v1'),
    'the Portal3 writer uses the institutional local PDO, canonical tables, transaction, UTC and idempotency');
$assert(is_string($apiProxy) && str_contains($apiProxy, 'public function listAll('),
    'the separate API read client remains available for API-owned reads outside the administration writer');
$proxyQueue = new MockHandler();
$proxy = new \App\Services\ChecadorWorkplaceCoverageApiService(
    new Client(['handler' => HandlerStack::create($proxyQueue), 'http_errors' => false]),
    static fn(Usuario $user): string => 'delegated-test-token',
    'https://api.example.test/'
);
$actor = new Usuario();
$actor->id = 13;
$actor->id_puesto = 13;
$proxyRequest = $proxy->requestCoverage($actor, [], 'idempotency-key-not-used');
$proxyTransition = $proxy->transition($actor, 'daaa2c2b-f936-4b0e-bf7e-20376cb90739', 'authorize', 'No operation', 'idempotency-key-not-used');
$assert(($proxyRequest['http_status'] ?? 0) === 410 && ($proxyRequest['code'] ?? '') === 'coverage_writer_portal3_only'
    && ($proxyTransition['http_status'] ?? 0) === 410 && ($proxyTransition['code'] ?? '') === 'coverage_writer_portal3_only'
    && count($proxyQueue) === 0,
    'the retired Portal3 HTTP mutation proxy returns a stable denial without sending requests');

if ($oldAppEnv === null) unset($_ENV['APP_ENV']); else $_ENV['APP_ENV'] = $oldAppEnv;
if ($oldGate === null) unset($_ENV['CHECADOR_COMODINES_PORTAL_WRITES']); else $_ENV['CHECADOR_COMODINES_PORTAL_WRITES'] = $oldGate;
if ($oldProcessAppEnv === false) putenv('APP_ENV'); else putenv('APP_ENV=' . $oldProcessAppEnv);
if ($oldProcessGate === false) putenv('CHECADOR_COMODINES_PORTAL_WRITES'); else putenv('CHECADOR_COMODINES_PORTAL_WRITES=' . $oldProcessGate);

printf("CHECADOR_V2_COMODINES_PORTAL_WRITER: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
