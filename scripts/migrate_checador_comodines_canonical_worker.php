<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Isolated-only candidate migration for canonical Comodín identities.
 *
 * It preserves every legacy id_usuario value, permits NULL for new workers
 * without Portal3 accounts, and adds an optional canonical worker FK. This
 * script never reads DB_* settings and refuses every target except the
 * disposable local bd_checador_v2_comodines_test database on port 33308.
 */
final class ChecadorComodinesCanonicalWorkerMigration
{
    public const TABLE = 'op_rh_comodines_dia';
    public const FK_NAME = 'op_rh_comodines_dia_personal_fk';
    public const INDEX_NAME = 'op_rh_comodines_dia_personal_idx';

    /** @return array{ready:bool,errors:list<string>,database:string,changes:list<string>} */
    public static function preflight(): array
    {
        $connection = Capsule::connection();
        $database = (string) $connection->getDatabaseName();
        $errors = [];

        $tables = $connection->select(
            'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?)',
            [self::TABLE, 'op_rh_personal', 'op_rh_rol_comodines']
        );
        $engines = [];
        foreach ($tables as $table) {
            $engines[(string) $table->TABLE_NAME] = strtoupper((string) $table->ENGINE);
        }
        foreach ([self::TABLE, 'op_rh_personal', 'op_rh_rol_comodines'] as $requiredTable) {
            if (!isset($engines[$requiredTable]) || $engines[$requiredTable] !== 'INNODB') {
                $errors[] = 'Required table missing or not InnoDB: ' . $requiredTable;
            }
        }

        $columns = $connection->select(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY '
            . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND ((TABLE_NAME = ? AND COLUMN_NAME IN (?, ?, ?, ?)) '
            . 'OR (TABLE_NAME = ? AND COLUMN_NAME = ?) '
            . 'OR (TABLE_NAME = ? AND COLUMN_NAME = ?))',
            [self::TABLE, 'id', 'id_reporte', 'id_usuario', 'id_personal', 'op_rh_personal', 'id', 'op_rh_rol_comodines', 'id']
        );
        $byKey = [];
        foreach ($columns as $column) {
            $byKey[(string) $column->TABLE_NAME . '.' . (string) $column->COLUMN_NAME] = $column;
        }

        foreach ([self::TABLE . '.id', self::TABLE . '.id_reporte', self::TABLE . '.id_usuario', 'op_rh_personal.id', 'op_rh_rol_comodines.id'] as $key) {
            if (!isset($byKey[$key])) {
                $errors[] = 'Required column missing: ' . $key;
            }
        }

        foreach (['op_rh_personal.id', 'op_rh_rol_comodines.id'] as $key) {
            $column = $byKey[$key] ?? null;
            if ($column !== null
                && (strtolower((string) $column->COLUMN_TYPE) !== 'int(11)'
                    || (string) $column->COLUMN_KEY !== 'PRI')) {
                $errors[] = 'Parent primary key differs from signed INT(11): ' . $key;
            }
        }

        $childId = $byKey[self::TABLE . '.id'] ?? null;
        $reportId = $byKey[self::TABLE . '.id_reporte'] ?? null;
        $legacyUserId = $byKey[self::TABLE . '.id_usuario'] ?? null;
        foreach ([self::TABLE . '.id' => $childId, self::TABLE . '.id_reporte' => $reportId, self::TABLE . '.id_usuario' => $legacyUserId] as $key => $column) {
            if ($column !== null && strtolower((string) $column->COLUMN_TYPE) !== 'int(11)') {
                $errors[] = 'Expected signed INT(11): ' . $key;
            }
        }
        if ($childId !== null && (string) $childId->COLUMN_KEY !== 'PRI') {
            $errors[] = 'Expected primary key on ' . self::TABLE . '.id';
        }
        $canonicalColumn = $connection->selectOne(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLE, 'id_personal']
        );
        $alreadyApplied = false;
        if ($canonicalColumn !== null) {
            if ($legacyUserId !== null && (string) $legacyUserId->IS_NULLABLE !== 'YES') {
                $errors[] = 'id_usuario must remain nullable after the candidate migration.';
            }
            $indexColumn = $connection->selectOne(
                'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND SEQ_IN_INDEX = 1',
                [self::TABLE, self::INDEX_NAME]
            );
            $foreignKey = $connection->selectOne(
                'SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
                . 'FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND COLUMN_NAME = ?',
                [self::TABLE, self::FK_NAME, 'id_personal']
            );
            $alreadyApplied = strtolower((string) $canonicalColumn->COLUMN_TYPE) === 'int(11)'
                && (string) $canonicalColumn->IS_NULLABLE === 'YES'
                && (string) ($indexColumn->COLUMN_NAME ?? '') === 'id_personal'
                && (string) ($foreignKey->REFERENCED_TABLE_NAME ?? '') === 'op_rh_personal'
                && (string) ($foreignKey->REFERENCED_COLUMN_NAME ?? '') === 'id';
            if (!$alreadyApplied) {
                $errors[] = 'id_personal exists but does not match the candidate nullable signed INT, index, and FK.';
            }
        } else {
            if ($legacyUserId !== null && (string) $legacyUserId->IS_NULLABLE !== 'NO') {
                $errors[] = 'Legacy id_usuario nullability differs from the inspected pre-migration schema.';
            }
            $conflicts = $connection->selectOne(
                'SELECT '
                . '(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME = ?) AS index_conflicts, '
                . '(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?) AS constraint_conflicts',
                [self::INDEX_NAME, self::FK_NAME]
            );
            if ((int) ($conflicts->index_conflicts ?? 0) !== 0 || (int) ($conflicts->constraint_conflicts ?? 0) !== 0) {
                $errors[] = 'Candidate index or foreign-key name already exists.';
            }
        }

        return [
            'ready' => $errors === [],
            'applicable' => $errors === [] && !$alreadyApplied,
            'already_applied' => $alreadyApplied,
            'errors' => $errors,
            'database' => $database,
            'changes' => [
                'make op_rh_comodines_dia.id_usuario nullable without changing existing values',
                'add nullable op_rh_comodines_dia.id_personal signed INT(11)',
                'add index op_rh_comodines_dia_personal_idx(id_personal)',
                'add restrictive FK id_personal -> op_rh_personal.id',
            ],
        ];
    }

    /** Apply only after the caller has connected to the guarded disposable test DB. */
    public static function up(): void
    {
        $preflight = self::preflight();
        if (empty($preflight['ready']) || empty($preflight['applicable'])) {
            throw new RuntimeException(implode('; ', $preflight['errors']) ?: 'Candidate migration is already applied or not applicable.');
        }
        if ((string) Capsule::connection()->getDatabaseName() !== 'bd_checador_v2_comodines_test') {
            throw new RuntimeException('Refusing DDL outside bd_checador_v2_comodines_test.');
        }

        Capsule::connection()->statement(
            'ALTER TABLE op_rh_comodines_dia '
            . 'MODIFY COLUMN id_usuario INT(11) NULL, '
            . 'ADD COLUMN id_personal INT(11) NULL AFTER id_usuario, '
            . 'ADD INDEX ' . self::INDEX_NAME . ' (id_personal), '
            . 'ADD CONSTRAINT ' . self::FK_NAME . ' FOREIGN KEY (id_personal) '
            . 'REFERENCES op_rh_personal (id) ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $targetHost = (string) (getenv('CHECADOR_COMODINES_TEST_DB_HOST') ?: '');
    $targetPort = (int) (getenv('CHECADOR_COMODINES_TEST_DB_PORT') ?: 0);
    $targetDatabase = (string) (getenv('CHECADOR_COMODINES_TEST_DB_DATABASE') ?: '');
    $targetUsername = (string) (getenv('CHECADOR_COMODINES_TEST_DB_USERNAME') ?: '');
    $targetPassword = (string) (getenv('CHECADOR_COMODINES_TEST_DB_PASSWORD') ?: '');

    if ($targetHost !== '127.0.0.1' || $targetPort !== 33308
        || $targetDatabase !== 'bd_checador_v2_comodines_test'
        || $targetUsername === '' || $targetPassword === '') {
        fwrite(STDERR, "REFUSED: set only the guarded local isolated database connection (127.0.0.1:33308/bd_checador_v2_comodines_test).\n");
        exit(2);
    }

    $apply = in_array('--apply', $argv, true);
    if (count($argv) > 2 || ($argv[1] ?? null) !== ($apply ? '--apply' : '--inspect')) {
        fwrite(STDERR, "Usage: php scripts/migrate_checador_comodines_canonical_worker.php --inspect|--apply\n");
        exit(2);
    }

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $targetHost,
        'port' => $targetPort,
        'database' => $targetDatabase,
        'username' => $targetUsername,
        'password' => $targetPassword,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    try {
        $identity = Capsule::connection()->selectOne(
            'SELECT DATABASE() AS database_name, VERSION() AS server_version, @@port AS server_port'
        );
        if ((string) $identity->database_name !== $targetDatabase
            || (int) $identity->server_port !== 3306
            || !str_starts_with((string) $identity->server_version, '11.8.8-MariaDB')) {
            throw new RuntimeException('Server identity did not match the disposable MariaDB 11.8.8 target.');
        }

        $preflight = ChecadorComodinesCanonicalWorkerMigration::preflight();
        if ($apply) {
            ChecadorComodinesCanonicalWorkerMigration::up();
            $preflight = ChecadorComodinesCanonicalWorkerMigration::preflight();
            if (empty($preflight['ready'])) {
                throw new RuntimeException('Applied schema did not pass its post-migration verification.');
            }
        }

        echo json_encode([
            'mode' => $apply ? 'APPLIED_ISOLATED_TEST' : 'DRY_RUN_ISOLATED_TEST',
            'database_changed' => $apply,
            'database' => (string) $identity->database_name,
            'server_version' => (string) $identity->server_version,
            'server_port' => (int) $identity->server_port,
            'preflight' => $preflight,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MIGRATION_REFUSED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
