<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();
use App\Services\ChecadorTemporaryAccessSqlService as M08;
$check = static function(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; };
unset($_ENV['CHECADOR_M08_HUMAN_WRITER']);
putenv('CHECADOR_M08_HUMAN_WRITER');
$check(M08::writerMode() === 'API', 'absent gate retains API writer and disables Portal3 SQL writer');
foreach (['API'=>'API','PORTAL3'=>'PORTAL3','invalid'=>'INVALID',''=>'INVALID'] as $input=>$expected) {
    $_ENV['CHECADOR_M08_HUMAN_WRITER'] = $input;
    $check(M08::writerMode() === $expected, 'writer mode ' . ($input === '' ? 'empty' : $input));
}
$pin = str_pad('42', 6, '0', STR_PAD_LEFT);
$hash = password_hash($pin, PASSWORD_BCRYPT);
$check($pin === '000042' && password_verify($pin, $hash) && !password_verify('42', $hash), 'leading-zero PIN is compatible with API bcrypt verification');
$source = file_get_contents(__DIR__ . '/../app/Services/ChecadorTemporaryAccessSqlService.php');
$readPart = substr($source, strpos($source, 'public function listForLocality'), strpos($source, 'public function action') - strpos($source, 'public function listForLocality'));
$check(!str_contains($readPart, 'pin_hash') && !str_contains($readPart, 'ChecadorDailyReadService') && !str_contains($readPart, 'http'), 'SQL list excludes hash and HTTP');
\App\Core\Session::remove('usuario');
\App\Core\Auth::forget();
$actor = new \App\Models\Usuario();
$actor->id = 123;
$service = new M08();
$check(($service->historyForLocality($actor, 1)['error']['kind'] ?? '') === 'permission_denied', 'history rejects an unauthenticated actor before SQL');
$_ENV['CHECADOR_M08_HUMAN_WRITER'] = 'PORTAL3';
$check(($service->action($actor, 1, '12345678-1234-4234-8234-123456789abc', 'authorize')['error']['kind'] ?? '') === 'permission_denied', 'SQL writer rejects an unauthenticated actor before transaction');
$_ENV['CHECADOR_M08_HUMAN_WRITER'] = 'API';
$check(($service->action($actor, 1, '12345678-1234-4234-8234-123456789abc', 'cancel')['error']['kind'] ?? '') === 'writer_disabled', 'API mode closes the SQL writer before transaction');
unset($_ENV['CHECADOR_M08_HUMAN_WRITER']);
echo "RESULT: 10 PASS / 0 FAIL\n";
