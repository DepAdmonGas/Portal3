<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\ChecadorPayrollComparisonReadService as Comparison;
$check = static function(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; };
$first = ['id'=>1,'context'=>['fecha_local'=>'2026-11-26'],'schedule'=>['payload_segmentos_json'=>'[]']];
$second = ['id'=>2,'context'=>['fecha_local'=>'2026-11-26'],'schedule'=>['payload_segmentos_json'=>'[]']];
$check(Comparison::uniqueJourneySnapshot([$first]) === $first, 'one persisted journey can supply a snapshot');
$check(Comparison::uniqueJourneySnapshot([]) === null, 'missing journey has no snapshot');
$check(Comparison::uniqueJourneySnapshot([$first,$second]) === null, 'multiple journeys do not silently select one');
$check(Comparison::uniqueJourneySnapshot([null]) === null, 'invalid journey does not supply a snapshot');
$check(Comparison::combinedLocalityIds(true,true,[1,2],[2,3]) === [2], 'combined scope remains an intersection');
$check(!Comparison::workerIdentityMatches(1,2,1,1), 'worker identity must match exactly');
echo "RESULT: 6 PASS / 0 FAIL\n";
