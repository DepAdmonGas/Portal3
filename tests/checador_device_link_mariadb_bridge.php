<?php

declare(strict_types=1);
if (!in_array((string)getenv('DB_HOST'), ['127.0.0.1','localhost'], true) || getenv('DB_NAME') !== 'bd_admongas_v2_biometric_roster_test_20261124') { fwrite(STDERR, "Refusing non-disposable database.\n"); exit(2); }
require_once __DIR__ . '/../vendor/autoload.php';
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Usuario;
use App\Services\ChecadorDeviceLinkSqlService;
$c = new Capsule();
$c->addConnection(['driver'=>'mysql','host'=>getenv('DB_HOST'),'port'=>getenv('DB_PORT') ?: 3306,'database'=>getenv('DB_NAME'),'username'=>getenv('DB_USER'),'password'=>getenv('DB_PASS'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>''], 'default');
$c->setAsGlobal(); $c->bootEloquent();
$identity = Capsule::connection('default')->selectOne('SELECT DATABASE() AS db');
if (($identity->db ?? '') !== 'bd_admongas_v2_biometric_roster_test_20261124') exit(2);
$actor = new Usuario(); $actor->id = 1;
$service = new ChecadorDeviceLinkSqlService(static fn(): bool => true, static fn(int $id): ?int => $id === 1001 ? 21 : null);
$result = $service->issueToken($actor, 1001, 1);
if (empty($result['ok']) || !is_string($result['token'] ?? null)) { fwrite(STDERR, "Synthetic issuance failed.\n"); exit(1); }
echo $result['token'];
