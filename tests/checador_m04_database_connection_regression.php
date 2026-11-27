<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;
use App\Services\ChecadorShiftTemplateSqlWriteService;
use Illuminate\Database\Capsule\Manager as Capsule;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS {$message}\n";
};

$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'bd_admongas';
$_ENV['DB_USERNAME'] = 'm04-test-user';
$_ENV['DB_PASSWORD'] = 'test-only-placeholder';
Database::initialize();
$default = Capsule::connection('default')->getConfig();

$assert(ChecadorShiftTemplateSqlWriteService::CONNECTION_NAME === 'default',
    'M04 uses Portal3’s existing default connection');
$assert(($default['database'] ?? null) === 'bd_admongas'
    && ($default['username'] ?? null) === 'm04-test-user'
    && ($default['host'] ?? null) === '127.0.0.1',
    'M04 does not register or change a second connection');

$writer = file_get_contents(__DIR__ . '/../app/Services/ChecadorShiftTemplateSqlWriteService.php') ?: '';
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$extract = static function (string $source, string $signature): string {
    $start = strpos($source, $signature);
    if ($start === false) return '';
    $end = strpos($source, 'private function ', $start + strlen($signature));
    return substr($source, $start, $end === false ? null : $end - $start);
};
$save = $extract($controller, 'private function saveScheduleTemplateJson(');
$render = $extract($controller, 'private function renderScheduleHistory(');

foreach ([
    'CHECADOR_M04_SQL_WRITER_ENABLED', 'CHECADOR_M04_API_WRITER_CLOSED',
    'CHECADOR_M04_SQL_GRANTS_VERIFIED', 'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
    'writeGateOpen(', 'isOperationallyReady(', 'mariadbSchemaReady(', 'hasRequiredM04Grants(',
    'isApprovedPleskTarget(', 'APP_ENV', 'CURRENT_USER()',
] as $removedGate) {
    $assert(!str_contains($writer, $removedGate), "writer has no M04 environment/preflight gate: {$removedGate}");
}
$assert($save !== '' && !str_contains($save, 'writeGateOpen()')
    && !str_contains($save, 'isOperationallyReady()')
    && !str_contains($save, 'CHECADOR_M04_')
    && str_contains($save, 'CsrfToken::validate($csrf)')
    && str_contains($save, 'authorizeStationTemplateAdministration($workplaceId)'),
    'M04 POST retains CSRF and effective locality authorization without environment or API-cut gates');
$assert($render !== '' && !str_contains($render, 'isOperationallyReady()')
    && !str_contains($render, 'writeGateOpen()')
    && str_contains($render, 'authorizeStationTemplateAdministration($workplaceId)'),
    'M04 controls depend on the authorized user, not SQL readiness attestations');
$assert(str_contains($writer, '$connection->transaction(')
    && str_contains($writer, 'findIdempotentResult(')
    && str_contains($writer, 'readExactWithinTransaction(')
    && str_contains($writer, 'recorded_at_utc')
    && str_contains($writer, "new ChecadorShiftTemplateSqlReadService(null, self::CONNECTION_NAME)"),
    'M04 retains transactional writes, audit, idempotency and SQL readback');
