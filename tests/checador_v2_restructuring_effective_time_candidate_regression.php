<?php

declare(strict_types=1);

use App\Services\RestructuringEffectiveInstantPolicyCandidate;

require_once __DIR__ . '/../vendor/autoload.php';

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
};
$version = static fn(int $id, string $timezone, string $from = '2020-01-01T00:00:00Z', ?string $to = null): array => [
    'id' => $id,
    'timezone_iana' => $timezone,
    'valid_from_utc' => $from,
    'valid_to_utc' => $to,
];

$cityA = RestructuringEffectiveInstantPolicyCandidate::resolve(
    '2026-10-26', '09:00', [$version(1, 'America/Mexico_City')]
);
$cityB = RestructuringEffectiveInstantPolicyCandidate::resolve(
    '2026-10-26', '09:00', [$version(2, 'America/Tijuana')]
);
$assert(
    $cityA['status'] === 'RESOLVED'
        && $cityA['effective_at_utc'] === '2026-10-26T15:00:00.000000Z'
        && $cityA['timezone_version_id'] === 1
        && $cityB['status'] === 'RESOLVED'
        && $cityB['effective_at_utc'] !== $cityA['effective_at_utc'],
    'the same explicit local date/time resolves differently under each Workplace IANA zone version'
);

$gap = RestructuringEffectiveInstantPolicyCandidate::resolve(
    '2026-03-08', '02:30:00', [$version(3, 'America/New_York')]
);
$fold = RestructuringEffectiveInstantPolicyCandidate::resolve(
    '2026-11-01', '01:30:00', [$version(4, 'America/New_York')]
);
$assert($gap['status'] === 'TIME_NONEXISTENT', 'a daylight-saving spring gap fails closed');
$assert(
    $fold['status'] === 'TIME_AMBIGUOUS'
        && count($fold['candidates'] ?? []) === 2,
    'a daylight-saving fall overlap fails closed with both candidate instants'
);

$zoneChange = RestructuringEffectiveInstantPolicyCandidate::resolve('2026-06-01', '09:00:00', [
    $version(5, 'America/Mexico_City', '2020-01-01T00:00:00Z', '2026-06-01T16:30:00Z'),
    $version(6, 'America/Tijuana', '2026-06-01T15:00:00Z'),
]);
$assert(
    $zoneChange['status'] === 'TIME_AMBIGUOUS'
        && count($zoneChange['candidates'] ?? []) === 2,
    'overlapping timezone versions cannot silently choose a restructuring instant'
);

$assert(
    RestructuringEffectiveInstantPolicyCandidate::resolve('2026-02-30', '09:00:00', [$version(7, 'UTC')])['status'] === 'DATA_INVALID'
        && RestructuringEffectiveInstantPolicyCandidate::resolve('2026-10-26', '09:00:00', [$version(8, 'Not/AZone')])['status'] === 'DATA_INVALID'
        && RestructuringEffectiveInstantPolicyCandidate::resolve('2026-10-26', '', [$version(9, 'UTC')])['status'] === 'DATA_INVALID',
    'invalid civil dates, local times, and non-IANA zones are rejected'
);

$assert(
    RestructuringEffectiveInstantPolicyCandidate::resolve('2026-10-26', '09:00:00', [])['status'] === 'TIMEZONE_UNRESOLVED',
    'a missing Workplace timezone version remains unresolved'
);

printf("CHECADOR_V2_RESTRUCTURING_TIME_CANDIDATE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
