<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/** Additive migration for bitemporal canonical worker-Workplace memberships. */
final class WorkerWorkplaceAssignmentVersionsMigration
{
    public const TABLE = 'op_rh_personal_localidad_version';

    public static function up(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            throw new RuntimeException('Worker-Workplace assignment table already exists; refusing to assume its state.');
        }
        foreach (['op_rh_personal', 'op_rh_localidades'] as $parent) {
            if (!$schema->hasTable($parent)) {
                throw new RuntimeException('Canonical parent table is missing: ' . $parent . '.');
            }
        }

        $schema->create(self::TABLE, static function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            // Both signed INT parent keys were inspected on MariaDB 11.8.8.
            $table->integer('worker_id');
            $table->integer('workplace_id');
            $table->dateTime('valid_from_utc', 6);
            $table->dateTime('valid_to_utc', 6)->nullable();
            $table->dateTime('recorded_at_utc', 6);
            $table->integer('recorded_by')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('supersedes_id')->nullable();

            $table->index(
                ['worker_id', 'workplace_id', 'valid_from_utc', 'valid_to_utc'],
                'op_rh_pwa_worker_workplace_effective_idx'
            );
            $table->index(['worker_id', 'recorded_at_utc'], 'op_rh_pwa_worker_recorded_idx');
            $table->index(['workplace_id'], 'op_rh_pwa_workplace_idx');
            // Composite keys support same-worker correction lineage and a single successor per row.
            $table->unique(['worker_id', 'id'], 'op_rh_pwa_worker_id_uq');
            $table->unique(['worker_id', 'supersedes_id'], 'op_rh_pwa_worker_supersedes_uq');

            $table->foreign('worker_id', 'op_rh_pwa_worker_fk')
                ->references('id')->on('op_rh_personal')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('workplace_id', 'op_rh_pwa_workplace_fk')
                ->references('id')->on('op_rh_localidades')
                ->restrictOnDelete()->restrictOnUpdate();
            // Corrections may change Workplace, but must remain in the same worker's lineage.
            $table->foreign(['worker_id', 'supersedes_id'], 'op_rh_pwa_supersedes_fk')
                ->references(['worker_id', 'id'])->on(self::TABLE)
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public static function down(): void
    {
        $schema = Capsule::schema();
        if (!$schema->hasTable(self::TABLE)) {
            return;
        }
        if (Capsule::table(self::TABLE)->exists()) {
            throw new RuntimeException('Refusing to drop worker-Workplace assignment history; export and review it first.');
        }

        $schema->drop(self::TABLE);
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
            throw new RuntimeException('--down requires the explicit --apply flag.');
        }
        if (!$apply) {
            echo json_encode([
                'mode' => 'DRY_RUN',
                'migration' => WorkerWorkplaceAssignmentVersionsMigration::TABLE,
                'action' => $down ? 'DOWN' : 'UP',
                'database_changed' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(0);
        }

        $host = strtolower(trim((string)($_ENV['DB_HOST'] ?? '')));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Apply is restricted to a local/demo database host.');
        }
        if (strtolower((string)($_ENV['DB_CONNECTION'] ?? '')) !== 'mysql') {
            throw new RuntimeException('Apply requires the inspected MySQL/MariaDB connection.');
        }

        require_once dirname(__DIR__) . '/app/Core/Database.php';
        App\Core\Database::initialize();
        $connection = Capsule::connection();
        $version = (string)$connection->selectOne('SELECT VERSION() AS version')->version;
        $parentKeys = $connection->select(
            'SELECT t.TABLE_NAME AS table_name, t.ENGINE AS engine, c.COLUMN_TYPE AS column_type, '
            . 'c.DATA_TYPE AS data_type, c.COLUMN_KEY AS column_key '
            . 'FROM information_schema.TABLES t '
            . 'JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME '
            . 'WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME IN (?,?) AND c.COLUMN_NAME=?',
            ['op_rh_personal', 'op_rh_localidades', 'id']
        );
        $validParents = count($parentKeys) === 2;
        foreach ($parentKeys as $parent) {
            $validParents = $validParents
                && strtoupper((string)$parent->engine) === 'INNODB'
                && strtolower((string)$parent->data_type) === 'int'
                && !str_contains(strtolower((string)$parent->column_type), 'unsigned')
                && (string)$parent->column_key === 'PRI';
        }
        if (!$validParents) {
            throw new RuntimeException('Canonical worker/Workplace PK types or engines differ from the inspected local schema.');
        }

        if ($down) {
            WorkerWorkplaceAssignmentVersionsMigration::down();
            $action = 'DOWN';
        } else {
            WorkerWorkplaceAssignmentVersionsMigration::up();
            $action = 'UP';
        }

        echo json_encode([
            'mode' => 'APPLIED_LOCAL',
            'action' => $action,
            'database_changed' => true,
            'server_version' => $version,
            'worker_pk' => 'op_rh_personal.id',
            'workplace_pk' => 'op_rh_localidades.id',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MIGRATION_REFUSED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
