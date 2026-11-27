<?php
declare(strict_types=1);
namespace App\Models\Operativo {
 class RhPersonal { public static function query(){return new class {function whereKey($id){return $this;} function exists(){return true;}};} }
 class RhPersonalLocalidadVersion { public static array $rows=[]; public static function query(){return new class {function where(...$args){return $this;} function orderBy(...$args){return $this;} function get(){return RhPersonalLocalidadVersion::$rows;}};} }
}
namespace {
 require __DIR__.'/../vendor/autoload.php';
 use App\Models\Operativo\RhPersonalLocalidadVersion as Rows;
 use App\Services\WorkerWorkplaceAssignmentResolver;
 use App\Services\ChecadorJourneySqlReadService as Journey;
 $row=static fn($id,$loc,$from,$to=null)=>(object)['id'=>$id,'worker_id'=>565,'workplace_id'=>$loc,'valid_from_utc'=>$from,'valid_to_utc'=>$to,'recorded_at_utc'=>'2026-11-26 01:38:22.446473','recorded_by'=>null,'reason'=>null,'supersedes_id'=>null];
 $resolver=new WorkerWorkplaceAssignmentResolver();
 $run=static fn($effective,$recorded)=>$resolver->resolveAssignments(565,$effective,$recorded);
 $assert=static function($ok,$label){if(!$ok)throw new \RuntimeException($label);echo "PASS $label\n";};
 Rows::$rows=[$row(248,1,'2026-11-26 01:38:22.413536')];
 $assert($run('2026-11-27 06:00:00.000000','2026-11-27 12:00:00.123456')['status']==='DATA_INVALID','SQL timestamps rejected');
 $resolved=$run('2026-11-27T06:00:00.000000Z','2026-11-27T12:00:00.123456Z');
 $assert($resolved['status']==='RESOLVED_SINGLE' && Journey::membershipConfirmsWorkplace($resolved,1),'RFC3339 resolves historical membership');
 $assert(str_contains($resolved['recorded_as_of_utc'],'123456') && str_contains($resolved['effective_at_utc'],'06:00:00.000000'),'UTC and microseconds preserved');
 $assert(!Journey::membershipConfirmsWorkplace($resolved,2),'wrong locality denied');
 Rows::$rows=[]; $assert($run('2026-11-27T06:00:00Z','2026-11-27T12:00:00Z')['status']==='RESOLVED_NONE','missing membership');
 Rows::$rows=[$row(248,1,'2026-11-26 01:38:22.413536','2026-11-27 05:00:00.000000')]; $assert($run('2026-11-27T06:00:00Z','2026-11-27T12:00:00Z')['status']==='RESOLVED_NONE','expired membership');
 Rows::$rows=[$row(248,1,'2026-11-26 01:38:22.413536'),$row(249,2,'2026-11-26 01:38:22.413536')]; $assert($run('2026-11-27T06:00:00Z','2026-11-27T12:00:00Z')['status']==='RESOLVED_MULTIPLE','multiple memberships denied');
 echo "RESULT 7 PASS\n";
}
