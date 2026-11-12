<?php

declare(strict_types=1);

use App\Services\WorkerWorkplaceAssignmentHistoryWriteGate;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ControlDocumentosPersonalController.php') ?: '';
$importer = file_get_contents($root . '/app/Services/WorkerWorkplaceAssignmentBaselineImportService.php') ?: '';
$gate = file_get_contents($root . '/app/Services/WorkerWorkplaceAssignmentHistoryWriteGate.php') ?: '';

$valid = [
    'app_env' => 'preproduction',
    'baseline_flag' => '1',
    'configured_driver' => 'mysql',
    'expected_db_host' => 'preprod-db.internal',
    'expected_server_hostname' => 'preprod-mariadb-01',
    'expected_db_port' => 3306,
    'configured_database' => 'bd_admongas_v2_preprod',
    'configured_db_host' => 'preprod-db.internal',
    'configured_db_port' => 3306,
    'database_name' => 'bd_admongas_v2_preprod',
    'server_hostname' => 'preprod-mariadb-01',
    'server_port' => 3306,
];

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $condition ? $passed++ : $failed++;
};
$deniedAfter = static function (array $overrides) use ($valid): bool {
    return !WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportContextAllows(array_replace($valid, $overrides));
};

$assert(WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportContextAllows($valid), 'matching preproduction flags, configured connection, and live database identity enable the baseline gate');
$assert($deniedAfter(['app_env' => 'production']) && $deniedAfter(['app_env' => 'prod']) && $deniedAfter(['app_env' => 'development']), 'production, prod, and development environments are denied');
$assert(WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportContextAllows(array_replace($valid, ['history_flag' => '0'])), 'baseline opt-in is isolated from the generic history gate');
$assert($deniedAfter(['baseline_flag' => '0']) && $deniedAfter(['baseline_flag' => null]), 'dedicated baseline gate must be explicitly on');
$assert($deniedAfter(['configured_database' => 'bd_admongas']) && $deniedAfter(['database_name' => 'bd_admongas']), 'local and unexpected databases are denied');
$assert($deniedAfter(['configured_db_host' => 'other-db.internal']) && $deniedAfter(['server_hostname' => 'other-db']), 'configured and live database hosts must match their explicit identity pins');
$assert($deniedAfter(['configured_driver' => 'sqlite']), 'non-MySQL connections are denied');
$assert($deniedAfter(['configured_db_port' => 3307]) && $deniedAfter(['server_port' => 3307]), 'configured and live database ports must match the pinned port');
$assert($deniedAfter(['expected_db_host' => '']) && $deniedAfter(['expected_server_hostname' => '']) && $deniedAfter(['expected_db_port' => null]), 'missing database identity pins fail closed');

$previewStart = strpos($controller, 'public function previewChecadorMembershipBaseline()');
$importStart = strpos($controller, 'public function importChecadorMembershipBaseline()');
$previewBody = $previewStart !== false && $importStart !== false && $importStart > $previewStart
    ? substr($controller, $previewStart, $importStart - $previewStart)
    : '';
$assert($previewBody !== ''
    && str_contains($previewBody, "'database_writes' => false")
    && !str_contains($previewBody, 'if (!WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled())'),
    'read-only preview remains available independently of the import write gate');
$assert(str_contains($importer, 'WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled()'), 'the import service enforces the preproduction gate even for direct callers');
$assert(str_contains($gate, "public const PREPRODUCTION_DATABASE = 'bd_admongas_v2_preprod'")
    && str_contains($gate, "'SELECT DATABASE() AS database_name, @@hostname AS server_hostname, @@port AS server_port'"),
    'runtime gate verifies the selected schema and live MariaDB identity without exposing credentials');

printf("CHECADOR_V2_PREPROD_BASELINE_WRITE_GATE: %d PASS / %d FAIL\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
