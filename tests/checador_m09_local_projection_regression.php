<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\ChecadorPunchReportProjection as Projection;
$check = static function(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; };
$zone = ['timezone_iana'=>'America/Mexico_City','valido_desde'=>'2020-01-01','valido_hasta'=>null];
$converted = Projection::localCapture('2026-11-26 04:30:00', [$zone]);
$check(($converted['local_datetime'] ?? null) === '2026-11-25 22:30:00', 'UTC conversion crosses the local calendar day');
$check(($converted['timezone'] ?? null) === 'America/Mexico_City' && ($converted['offset'] ?? null) === '-06:00', 'conversion retains timezone provenance');
$check(Projection::localCapture('2026-11-26 04:30:00', [])['status'] === 'unavailable', 'missing history remains unavailable');
$check(Projection::localCapture('2026-11-26 04:30:00', [$zone,$zone])['status'] === 'ambiguous', 'overlapping history is rejected');
$check(Projection::localCapture('bad', [$zone])['status'] === 'invalid', 'invalid UTC is rejected');
$check(Projection::localCapture('2026-11-26 04:30:00.123456', [$zone])['status'] === 'resolved', 'microsecond UTC timestamps are accepted');
$check(Projection::localCapture('2026-11-26 04:30:00', [['timezone_iana'=>'America/Mexico_City','valido_desde'=>'2026-99-99','valido_hasta'=>null]])['status'] === 'invalid', 'invalid version dates are rejected');
$previous = ['timezone_iana'=>'America/Cancun','valido_desde'=>'2010-01-01','valido_hasta'=>'2020-01-01'];
$check(Projection::localCapture('2019-06-01 10:00:00', [$previous,$zone])['timezone'] === 'America/Cancun', 'historical version follows its validity');
$window = Projection::localIntervals('2026-11-25','2026-11-25',[$zone]);
$check(($window['intervals'][0] ?? null) === ['2026-11-25 06:00:00','2026-11-26 06:00:00'], 'local dates resolve to half-open UTC boundaries');
$check(Projection::localIntervals('2026-11-25','2026-11-25',[])['status'] === 'unavailable', 'missing timezone denies local range');
$check(Projection::localIntervals('2026-11-25','2026-11-25',[$zone,$zone])['status'] === 'ambiguous', 'overlapping zones deny local range');
$changed = Projection::localIntervals('2019-12-31','2020-01-01',[$previous,$zone]);
$check(($changed['status'] ?? '') === 'resolved' && ($changed['intervals'][0][1] ?? '') === '2020-01-01 05:00:00'
    && ($changed['intervals'][1][0] ?? '') === '2020-01-01 06:00:00', 'historical timezone changes produce separate UTC intervals without inventing coverage');
$check(Projection::localIntervals('2020-01-01','2020-02-01',[$zone])['status'] === 'invalid', 'ranges above 31 days fail closed');
echo "RESULT: 13 PASS / 0 FAIL\n";
