<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

/** Additive idempotency metadata for direct RH worker creation. */
final class RhDirectCreateIdempotencyMigration
{
    public const TABLE = 'op_rh_personal';
    public const KEY_HASH = 'rh_direct_create_idempotency_key_hash';
    public const REQUEST_FINGERPRINT = 'rh_direct_create_request_fingerprint';
    public const UNIQUE_INDEX = 'op_rh_personal_direct_create_key_uq';

    public static function up(): void
    {
        $connection = Capsule::connection();
        $table = $connection->selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
            [self::TABLE]
        );
        if ($table === null || strtoupper((string) $table->engine) !== 'INNODB') {
            throw new RuntimeException('op_rh_personal must exist as InnoDB before applying this migration.');
        }

        $columns = $connection->select(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_NULLABLE AS nullable '
            . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN (?,?)',
            [self::TABLE, self::KEY_HASH, self::REQUEST_FINGERPRINT]
        );
        if ($columns !== []) {
            throw new RuntimeException('Idempotency columns already exist; refusing to assume a partial migration state.');
        }

        $index = $connection->selectOne(
            'SELECT INDEX_NAME AS name FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
            [self::TABLE, self::UNIQUE_INDEX]
        );
        if ($index !== null) {
            throw new RuntimeException('Idempotency unique index already exists without the expected columns.');
        }

        $connection->statement(
            'ALTER TABLE op_rh_personal '
            . 'ADD COLUMN rh_direct_create_idempotency_key_hash BINARY(32) NULL, '
            . 'ADD COLUMN rh_direct_create_request_fingerprint BINARY(32) NULL, '
            . 'ADD UNIQUE KEY op_rh_personal_direct_create_key_uq (rh_direct_create_idempotency_key_hash)'
        );
    }

    public static function down(): void
    {
        $connection = Capsule::connection();
        $columns = $connection->select(
            'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN (?,?)',
            [self::TABLE, self::KEY_HASH, self::REQUEST_FINGERPRINT]
        );
        if ($columns === []) {
            return;
        }
        if (count($columns) !== 2) {
            throw new RuntimeException('Partial idempotency migration detected; refusing rollback.');
        }
        if ($connection->table(self::TABLE)->whereNotNull(self::KEY_HASH)->exists()
            || $connection->table(self::TABLE)->whereNotNull(self::REQUEST_FINGERPRINT)->exists()) {
            throw new RuntimeException('Refusing to remove persisted idempotency results.');
        }

        $connection->statement(
            'ALTER TABLE op_rh_personal '
            . 'DROP INDEX op_rh_personal_direct_create_key_uq, '
            . 'DROP COLUMN rh_direct_create_idempotency_key_hash, '
            . 'DROP COLUMN rh_direct_create_request_fingerprint'
        );
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    try {
        if (!isset($_ENV['DB_CONNECTION'])) {
            Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
        }
        $apply = in_array('--apply', $argv, true);
        $down = in_array('--down', $argv, true);
        if ($down && !$apply) {
            throw new RuntimeException('--down requires --apply.');
        }
        if (!$apply) {
            echo json_encode([
                'mode' => 'DRY_RUN',
                'table' => RhDirectCreateIdempotencyMigration::TABLE,
                'columns' => [RhDirectCreateIdempotencyMigration::KEY_HASH, RhDirectCreateIdempotencyMigration::REQUEST_FINGERPRINT],
                'unique_index' => RhDirectCreateIdempotencyMigration::UNIQUE_INDEX,
                'action' => $down ? 'DOWN' : 'UP',
                'database_changed' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(0);
        }

        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? '')));
        $connectionName = strtolower(trim((string) ($_ENV['DB_CONNECTION'] ?? '')));
        $host = strtolower(trim((string) ($_ENV['DB_HOST'] ?? '')));
        $database = (string) ($_ENV['DB_DATABASE'] ?? '');
        if ($environment !== 'dev' || $connectionName !== 'mysql' || $database !== 'bd_admongas'
            || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Apply is restricted to APP_ENV=dev and local bd_admongas.');
        }

        require_once dirname(__DIR__) . '/app/Core/Database.php';
        App\Core\Database::initialize();
        $connection = Capsule::connection();
        $identity = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@hostname AS host, @@port AS port');
        if ((string) $identity->db !== 'bd_admongas'
            || strtolower((string) $identity->host) !== 'fedora'
            || (int) $identity->port !== 3306
            || !str_starts_with((string) $identity->version, '11.8.8-MariaDB')) {
            throw new RuntimeException('Connected server identity does not match the verified local development MariaDB.');
        }

        if ($down) {
            RhDirectCreateIdempotencyMigration::down();
            $action = 'DOWN';
        } else {
            RhDirectCreateIdempotencyMigration::up();
            $action = 'UP';
        }
        echo json_encode([
            'mode' => 'APPLIED_LOCAL_DEV',
            'action' => $action,
            'database' => (string) $identity->db,
            'server_version' => (string) $identity->version,
            'database_changed' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MIGRATION_REFUSED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
