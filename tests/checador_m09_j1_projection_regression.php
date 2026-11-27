<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use Illuminate\Database\Connection;
use App\Services\ChecadorRawPunchReadService;

final class M09FakeQuery
{
    public function __construct(private array $rows) {}
    public function join(...$args): self { return $this; }
    public function leftJoin(...$args): self { return $this; }
    public function whereIn(...$args): self { return $this; }
    public function get(...$args): \Illuminate\Support\Collection { return collect(array_map(static fn(array $x): object => (object)$x, $this->rows)); }
}
final class M09FakeConnection extends Connection
{
    public function __construct(public array $associations, public array $revisions) {}
    public function table($table, $as = null) { return new M09FakeQuery(str_contains($table, 'as a') ? $this->associations : $this->revisions); }
}
$event = ['id'=>10,'id_personal_resuelto'=>7,'id_estacion_resuelta'=>1];
$assoc = ['id_marcacion_cruda'=>10,'rol_marcacion'=>'ENTRY','id_pareo_segmento'=>5,'pairing_id'=>11,'id_jornada_programada'=>2,'id_personal'=>7,'id_estacion'=>1,'fecha_jornada_local'=>'2026-11-26','journey_id'=>2,'journey_worker'=>7,'journey_locality'=>1,'journey_date'=>'2026-11-26','segment_pairing'=>11,'id_marcacion_entrada'=>10,'id_marcacion_salida'=>null];
$head = ['id'=>11,'id_jornada_programada'=>2,'id_personal'=>7,'id_estacion'=>1,'revision_numero'=>2,'supercedido_por_id'=>null];
$old = ['id'=>9,'id_jornada_programada'=>2,'id_personal'=>7,'id_estacion'=>1,'revision_numero'=>1,'supercedido_por_id'=>11];
$method = new ReflectionMethod(ChecadorRawPunchReadService::class, 'readPersistedClassifications');
$method->setAccessible(true);
$run = static fn(array $a,array $v): string => $method->invoke(new ChecadorRawPunchReadService(),new M09FakeConnection($a,$v),[$event],1)[10];
$assert = static function(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; };
$assert($run([$assoc],[$old,$head]) === 'entry','persisted current entry');
$exit = array_replace($assoc,['rol_marcacion'=>'EXIT','id_marcacion_entrada'=>null,'id_marcacion_salida'=>10]);
$assert($run([$exit],[$old,$head]) === 'exit','persisted current exit');
$extra = array_replace($assoc,['rol_marcacion'=>'SURPLUS','id_pareo_segmento'=>null,'segment_pairing'=>null]);
$assert($run([$extra],[$old,$head]) === 'surplus','persisted surplus remains additional');
$stale = array_replace($assoc,['pairing_id'=>9,'segment_pairing'=>9]);
$assert($run([$stale],[$old,$head]) === 'pending','superseded association is not used');
$assert($run([],[]) === 'pending','absence remains pending');
$assert($run([$assoc,$exit],[$old,$head]) === 'ambiguous','duplicate conflicting associations are ambiguous');
$assert($run([array_replace($assoc,['journey_locality'=>2])],[$old,$head]) === 'ambiguous','cross locality relation is rejected');
$assert($run([$assoc],[$old,array_replace($head,['supercedido_por_id'=>9])]) === 'ambiguous','invalid revision chain remains ambiguous');
$assert($run([array_replace($assoc,['id_marcacion_entrada'=>44])],[$old,$head]) === 'ambiguous','segment must reference the entry event');
echo "RESULT: 9 PASS / 0 FAIL\n";
