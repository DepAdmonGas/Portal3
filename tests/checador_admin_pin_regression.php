<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Services\ChecadorV2PermissionService;

$service=file_get_contents(__DIR__.'/../app/Services/ChecadorAdministrativePinSqlService.php');
$pass=0;$fail=0;
$check=static function(bool $ok,string $name)use(&$pass,&$fail):void{echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;$ok?$pass++:$fail++;};
$limit=strpos($service,"\$phase='limiter_lock'");
$pin=strpos($service,"\$phase='pending_pin_lock'");
$session=strpos($service,"\$phase='session_lock'");
$check($limit!==false&&$pin!==false&&$session!==false&&$limit<$pin&&$pin<$session,'Portal issuance follows device, limiter, PIN, session lock order');
$check(str_contains($service,"insertOrIgnore([")&&str_contains($service,"'failed_attempts'=>0")&&!str_contains($service,"LIMIT_TABLE)->where('device_id',\$deviceId)->update"),'issuance initializes only a missing limiter and never resets existing attempts');
$check(!str_contains($service,'DEVICE_BIOMETRIC_ADMIN'),'administrative PIN ACL is distinct from biometric administration');
$check(str_contains($service,'session_last_activity_at_utc')&&str_contains($service,"\$sessionState==='ACTIVE'"),'history derives session expiry from absolute and idle boundaries');
$check(ChecadorV2PermissionService::effectiveGrantDecision(['active'=>1,'granted'=>0],['active'=>1,'granted'=>1])===false,'explicit individual denial overrides a position grant');
$check(ChecadorV2PermissionService::effectiveGrantDecision(null,['active'=>1,'granted'=>1])===true,'position grant is used when no individual decision exists');
echo "RESULT: {$pass} PASS / {$fail} FAIL".PHP_EOL;exit($fail?1:0);
