<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
$host = strtolower(trim((string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '')));
$database = trim((string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ''));
if (!in_array($environment, ['dev', 'development', 'local'], true)
    || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || $database !== 'bd_admongas') {
    fwrite(STDERR, "BLOCKED: M11 ACL grant requires a loopback development connection to bd_admongas.\n");
    exit(2);
}

\App\Core\Database::initialize();
$connection = Capsule::connection();
$actual = $connection->selectOne('SELECT DATABASE() AS database_name');
if (($actual->database_name ?? null) !== 'bd_admongas') {
    fwrite(STDERR, "BLOCKED: connected database is not bd_admongas.\n");
    exit(2);
}

$permission = 'checador-v2-dispositivos.leer';
$principals = [6, 13];
$users = $connection->table('tb_usuarios')->whereIn('id', [33, 19])->get(['id', 'id_puesto', 'estatus']);
$identityMap = [];
foreach ($users as $user) $identityMap[(int) $user->id] = $user;
if (!isset($identityMap[33], $identityMap[19])
    || (int) $identityMap[33]->id_puesto !== 6 || (int) $identityMap[19]->id_puesto !== 13
    || (int) $identityMap[33]->estatus !== 0 || (int) $identityMap[19]->estatus !== 0) {
    fwrite(STDERR, "BLOCKED: institutional development identities do not match the approved positions.\n");
    exit(2);
}

$connection->beginTransaction();
try {
    foreach ([33, 19] as $userId) {
        $direct = $connection->table('op_rh_v2_permission_grants')
            ->where('permission_key', $permission)->where('principal_type', 'USER')->where('principal_id', $userId)->first();
        if ($direct !== null && ((int) $direct->active !== 1 || (int) $direct->granted !== 1)) {
            throw new RuntimeException('An explicit user-level revocation must not be overridden.');
        }
    }
    foreach ($principals as $positionId) {
        $existing = $connection->table('op_rh_v2_permission_grants')
            ->where('permission_key', $permission)->where('principal_type', 'POSITION')->where('principal_id', $positionId)->first();
        if ($existing !== null && ((int) $existing->active !== 1 || (int) $existing->granted !== 1)) {
            throw new RuntimeException('An existing position-level revocation must not be reactivated.');
        }
        if ($existing === null) {
            $connection->table('op_rh_v2_permission_grants')->insert([
                'permission_key' => $permission,
                'principal_type' => 'POSITION',
                'principal_id' => $positionId,
                'granted' => 1,
                'active' => 1,
                'reason' => 'M11 read-only device inventory; only the existing authorized station/locality scope.',
                'source_task' => 'CHECADOR_V2_M11_DEVICE_ADMIN_READ_IMPLEMENTATION_V1',
                'updated_by_user_id' => null,
            ]);
        }
    }
    foreach ($principals as $positionId) {
        $row = $connection->table('op_rh_v2_permission_grants')
            ->where('permission_key', $permission)->where('principal_type', 'POSITION')->where('principal_id', $positionId)->first();
        if ($row === null || (int) $row->active !== 1 || (int) $row->granted !== 1) {
            throw new RuntimeException('Grant readback did not confirm the requested capability.');
        }
    }
    $connection->commit();
} catch (Throwable $exception) {
    $connection->rollBack();
    fwrite(STDERR, 'BLOCKED: ' . $exception->getMessage() . "\n");
    exit(2);
}

echo "M11_DEVICE_READ_ACL=APPLIED\nDATABASE=bd_admongas\nPOSITION_6=active,granted\nPOSITION_13=active,granted\nUSER_LEVEL_DENY=none\n";
