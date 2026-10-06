<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Additive migration for the Portal3 Workplace timezone history.
 *
 * Effective time is an absolute UTC instant and uses [valid_from_utc,
 * valid_to_utc), with a null end meaning open-ended. recorded_at_utc is the
 * UTC instant Portal3 recorded a fact. Corrections insert a new row whose
 * supersedes_id points to the prior row; existing row content is not updated.
 *
 * The migration is deliberately a local/demo-only CLI operation. By default
 * the script is a dry run; --apply creates the table and --down --apply only
 * drops it when it contains no history.
 */
final class WorkplaceTimezoneVersionsMigration
{
    public const TABLE = 'op_rh_localidad_timezone_version';

    public static function up(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            throw new RuntimeException('Timezone migration table already exists; refusing to assume its state.');
        }
        if (!$schema->hasTable('op_rh_localidades')) {
            throw new RuntimeException('Canonical Workplace table op_rh_localidades is missing.');
        }

        $driver = Capsule::connection()->getDriverName();
        $schema->create(self::TABLE, static function (Blueprint $table) use ($driver): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            // Signed INT matches the inspected op_rh_localidades.id INT(11).
            $table->integer('workplace_id');
            $timezone = $table->string('timezone_iana', 64);
            if ($driver === 'mysql') {
                // IANA identifiers are ASCII and case-sensitive identifiers.
                $timezone->charset('ascii')->collation('ascii_bin');
            }
            $table->dateTime('valid_from_utc', 6);
            $table->dateTime('valid_to_utc', 6)->nullable();
            $table->dateTime('recorded_at_utc', 6);
            $table->integer('recorded_by')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('supersedes_id')->nullable();

            $table->index(
                ['workplace_id', 'valid_from_utc', 'valid_to_utc'],
                'op_rh_loc_tz_workplace_effective_idx'
            );
            $table->index(
                ['workplace_id', 'recorded_at_utc'],
                'op_rh_loc_tz_workplace_recorded_idx'
            );
            $table->index(
                ['workplace_id', 'supersedes_id'],
                'op_rh_loc_tz_supersession_idx'
            );
            // Required unique target for the composite same-Workplace lineage FK.
            $table->unique(['workplace_id', 'id'], 'op_rh_loc_tz_workplace_id_uq');

            $table->foreign('workplace_id', 'op_rh_loc_tz_workplace_fk')
                ->references('id')->on('op_rh_localidades')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['workplace_id', 'supersedes_id'], 'op_rh_loc_tz_supersedes_fk')
                ->references(['workplace_id', 'id'])->on(self::TABLE)
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
            throw new RuntimeException('Refusing to drop Workplace timezone history; export and review it first.');
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
                'migration' => WorkplaceTimezoneVersionsMigration::TABLE,
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
        $parent = $connection->selectOne(
            'SELECT t.ENGINE AS engine, c.COLUMN_TYPE AS column_type, c.DATA_TYPE AS data_type, '
            . 'c.COLUMN_KEY AS column_key, c.EXTRA AS extra '
            . 'FROM information_schema.TABLES t '
            . 'JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME '
            . 'WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = ? AND c.COLUMN_NAME = ?',
            ['op_rh_localidades', 'id']
        );
        if (!$parent
            || strtoupper((string)$parent->engine) !== 'INNODB'
            || strtolower((string)$parent->data_type) !== 'int'
            || str_contains(strtolower((string)$parent->column_type), 'unsigned')
            || (string)$parent->column_key !== 'PRI') {
            throw new RuntimeException('Canonical Workplace PK/engine differs from the verified local schema.');
        }

        if ($down) {
            WorkplaceTimezoneVersionsMigration::down();
            $action = 'DOWN';
        } else {
            WorkplaceTimezoneVersionsMigration::up();
            $action = 'UP';
        }

        echo json_encode([
            'mode' => 'APPLIED_LOCAL',
            'action' => $action,
            'database_changed' => true,
            'server_version' => $version,
            'canonical_workplace_table' => 'op_rh_localidades',
            'canonical_workplace_pk' => (string)$parent->column_type,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MIGRATION_REFUSED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
