<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/** Additive, bitemporal personal-schedule domain migration. */
final class PersonalScheduleVersionsMigration
{
    public const HEADER_TABLE = 'op_rh_personal_horario_version';
    public const DAY_TABLE = 'op_rh_personal_horario_version_dia';
    public const SEGMENT_TABLE = 'op_rh_personal_horario_version_segmento';

    public static function up(): void
    {
        $schema = Capsule::schema();
        foreach ([self::HEADER_TABLE, self::DAY_TABLE, self::SEGMENT_TABLE] as $table) {
            if ($schema->hasTable($table)) {
                throw new RuntimeException('Schedule version table already exists; refusing to assume its state: ' . $table . '.');
            }
        }
        foreach (['op_rh_personal', 'op_rh_localidades'] as $parent) {
            if (!$schema->hasTable($parent)) {
                throw new RuntimeException('Canonical schedule parent table is missing: ' . $parent . '.');
            }
        }

        $schema->create(self::HEADER_TABLE, static function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->integer('worker_id');
            $table->integer('workplace_id');
            $table->date('valid_from_local_date');
            $table->date('valid_to_local_date')->nullable();
            $table->dateTime('recorded_at_utc', 6);
            $table->integer('recorded_by')->nullable();
            $table->string('record_source', 64)->default('PORTAL3');
            $table->unsignedSmallInteger('definition_version')->default(1);
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('supersedes_id')->nullable();

            $table->index(
                ['worker_id', 'workplace_id', 'valid_from_local_date', 'valid_to_local_date'],
                'op_rh_phv_worker_workplace_effective_idx'
            );
            $table->index(['worker_id', 'recorded_at_utc'], 'op_rh_phv_worker_recorded_idx');
            $table->index(['workplace_id', 'recorded_at_utc'], 'op_rh_phv_workplace_recorded_idx');
            $table->unique(['worker_id', 'id'], 'op_rh_phv_worker_id_uq');
            $table->unique(['worker_id', 'supersedes_id'], 'op_rh_phv_worker_supersedes_uq');

            $table->foreign('worker_id', 'op_rh_phv_worker_fk')
                ->references('id')->on('op_rh_personal')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('workplace_id', 'op_rh_phv_workplace_fk')
                ->references('id')->on('op_rh_localidades')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['worker_id', 'supersedes_id'], 'op_rh_phv_supersedes_fk')
                ->references(['worker_id', 'id'])->on(self::HEADER_TABLE)
                ->restrictOnDelete()->restrictOnUpdate();
        });

        $driver = Capsule::connection()->getDriverName();
        $schema->create(self::DAY_TABLE, static function (Blueprint $table) use ($driver): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('schedule_version_id');
            $table->unsignedTinyInteger('weekday_iso');
            $dayState = $table->string('day_state', 16);
            if ($driver === 'mysql') {
                $dayState->charset('ascii')->collation('ascii_bin');
            }
            $table->unique(
                ['schedule_version_id', 'weekday_iso'],
                'op_rh_phv_day_version_weekday_uq'
            );
            $table->foreign('schedule_version_id', 'op_rh_phv_day_version_fk')
                ->references('id')->on(self::HEADER_TABLE)
                ->restrictOnDelete()->restrictOnUpdate();
        });

        $schema->create(self::SEGMENT_TABLE, static function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('day_definition_id');
            $table->unsignedInteger('sequence_number');
            $table->time('start_local_time');
            $table->time('end_local_time');
            // No business maximum is frozen; unsigned INT is the storage bound.
            $table->unsignedInteger('end_day_offset');
            $table->unique(
                ['day_definition_id', 'sequence_number'],
                'op_rh_phv_segment_day_sequence_uq'
            );
            $table->foreign('day_definition_id', 'op_rh_phv_segment_day_fk')
                ->references('id')->on(self::DAY_TABLE)
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public static function down(): void
    {
        $schema = Capsule::schema();
        foreach ([self::SEGMENT_TABLE, self::DAY_TABLE, self::HEADER_TABLE] as $table) {
            if ($schema->hasTable($table) && Capsule::table($table)->exists()) {
                throw new RuntimeException('Refusing to drop populated schedule history table: ' . $table . '.');
            }
        }

        foreach ([self::SEGMENT_TABLE, self::DAY_TABLE, self::HEADER_TABLE] as $table) {
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        }
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
                'migration' => [
                    PersonalScheduleVersionsMigration::HEADER_TABLE,
                    PersonalScheduleVersionsMigration::DAY_TABLE,
                    PersonalScheduleVersionsMigration::SEGMENT_TABLE,
                ],
                'action' => $down ? 'DOWN' : 'UP',
                'database_changed' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(0);
        }

        $host = strtolower(trim((string)($_ENV['DB_HOST'] ?? '')));
        $database = (string)($_ENV['DB_DATABASE'] ?? '');
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || $database !== 'bd_portal3_p3_l3_3'
            || strtolower((string)($_ENV['DB_CONNECTION'] ?? '')) !== 'mysql') {
            throw new RuntimeException('Apply is restricted to local MariaDB schema bd_portal3_p3_l3_3.');
        }

        require_once dirname(__DIR__) . '/app/Core/Database.php';
        App\Core\Database::initialize();
        $connection = Capsule::connection();
        $version = (string)$connection->selectOne('SELECT VERSION() AS version')->version;
        if (!str_starts_with($version, '11.8.8-MariaDB')) {
            throw new RuntimeException('Apply requires the verified MariaDB 11.8.8 disposable engine.');
        }
        $parents = $connection->select(
            'SELECT t.TABLE_NAME AS table_name, t.ENGINE AS engine, c.COLUMN_TYPE AS column_type, '
            . 'c.DATA_TYPE AS data_type, c.COLUMN_KEY AS column_key '
            . 'FROM information_schema.TABLES t '
            . 'JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME '
            . 'WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME IN (?,?) AND c.COLUMN_NAME=?',
            ['op_rh_personal', 'op_rh_localidades', 'id']
        );
        $valid = count($parents) === 2;
        foreach ($parents as $parent) {
            $valid = $valid
                && strtoupper((string)$parent->engine) === 'INNODB'
                && strtolower((string)$parent->data_type) === 'int'
                && !str_contains(strtolower((string)$parent->column_type), 'unsigned')
                && (string)$parent->column_key === 'PRI';
        }
        if (!$valid) {
            throw new RuntimeException('Canonical worker/Workplace keys differ from the verified signed INT InnoDB schema.');
        }

        if ($down) {
            PersonalScheduleVersionsMigration::down();
            $action = 'DOWN';
        } else {
            PersonalScheduleVersionsMigration::up();
            $action = 'UP';
        }
        echo json_encode([
            'mode' => 'APPLIED_LOCAL',
            'action' => $action,
            'database_changed' => true,
            'database' => $database,
            'server_version' => $version,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MIGRATION_REFUSED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
