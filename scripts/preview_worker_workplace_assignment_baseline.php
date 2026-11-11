<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\WorkerWorkplaceAssignmentBaselinePreviewService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || $argc !== 2 || filter_var($argv[1] ?? null, FILTER_VALIDATE_INT) === false || (int) $argv[1] <= 0) {
    fwrite(STDERR, "Usage: php scripts/preview_worker_workplace_assignment_baseline.php <authorized-locality-id>\n");
    fwrite(STDERR, "A locality ID is mandatory; an omitted scope never means all localities.\n");
    exit(2);
}

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$host = strtolower(trim((string) ($_ENV['DB_HOST'] ?? '')));
$database = trim((string) ($_ENV['DB_DATABASE'] ?? ''));
$environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? '')));
if ($environment !== 'dev'
    || strtolower(trim((string) ($_ENV['DB_CONNECTION'] ?? ''))) !== 'mysql'
    || $database !== 'bd_admongas'
    || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "REFUSED: expected the local dev connection to bd_admongas; no data was read.\n");
    exit(2);
}

try {
    Database::initialize();
    $connection = Capsule::connection();
    $identity = $connection->selectOne('SELECT DATABASE() AS database_name, VERSION() AS server_version, @@port AS server_port');
    if ((string) $identity->database_name !== 'bd_admongas'
        || (int) $identity->server_port !== 3306
        || !str_starts_with((string) $identity->server_version, '11.8.8-MariaDB')) {
        fwrite(STDERR, "REFUSED: live server identity did not match the verified local development target.\n");
        exit(2);
    }

    $preview = (new WorkerWorkplaceAssignmentBaselinePreviewService())->preview((int) $argv[1]);
    $preview['database_environment'] = [
        'app_env' => $environment,
        'database' => (string) $identity->database_name,
        'server_version' => (string) $identity->server_version,
        'server_port' => (int) $identity->server_port,
        'host_is_local' => true,
    ];
    echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'PREVIEW_FAILED: ' . get_class($exception) . PHP_EOL);
    exit(1);
}
