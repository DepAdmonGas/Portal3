<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorM01IncidenceReadService;

$_ENV['APP_ENV'] = 'test';
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passes++ : $fails++;
};

$actor = new Usuario();
$actor->id = 77;
$page = static function (array $items, int $total, int $requestedPage = 1, ?int $reportedTotalPages = null): array {
    $pages = $reportedTotalPages ?? ($total === 0 ? 0 : (int) ceil($total / 100));
    return ['ok' => true, 'data' => [
        'items' => $items,
        'pagination' => [
            'page' => $requestedPage,
            'per_page' => 100,
            'total_records' => $total,
            'total_pages' => $pages,
        ],
    ]];
};
$incidence = [
    'uuid_incidencia' => '8f730192-4ca9-5a44-9a32-0a75c176a451',
    'worker_id' => 565,
    'workplace_id' => 1,
    'category' => 'PERMISSION',
    'start_date' => '2026-11-26',
    'end_date' => '2026-11-26',
    'current_status' => 'PENDING_APPROVAL',
    'current_revision' => 2,
    'reason' => 'must not escape M06 boundary',
    'evidence_reference' => 'private',
    'revisions' => [['reason' => 'private']],
];

$reader = static function (callable $respond, ?callable $authorize = null): ChecadorM01IncidenceReadService {
    return new ChecadorM01IncidenceReadService(
        static fn(Usuario $user, int $locality, array $filters, int $pageNumber, int $perPage): array => $respond($user, $locality, $filters, $pageNumber, $perPage),
        $authorize ?? static fn(Usuario $user, int $locality): bool => true
    );
};

$captured = null;
$zeroReader = $reader(static function (Usuario $user, int $locality, array $filters, int $pageNumber, int $perPage) use ($page, &$captured): array {
    $captured = [$locality, $filters, $pageNumber, $perPage];
    return $page([], 0);
});
$zero = $zeroReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($zero['ok'] ?? false) && ($zero['data']['complete'] ?? false) && ($zero['data']['count'] ?? -1) === 0,
    'successful complete M06 query distinguishes confirmed zero incidences');
$assert($captured === [1, ['worker_id' => 565, 'start_date' => '2026-11-26', 'end_date' => '2026-11-26'], 1, 100],
    'M01 invokes the existing reader with canonical locality, exact worker and inclusive date filters');

$oneReader = $reader(static fn(Usuario $user, int $locality, array $filters, int $pageNumber, int $perPage): array => $page([$incidence], 1));
$one = $oneReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($one['ok'] ?? false) && count($one['data']['items'] ?? []) === 1
    && !array_key_exists('reason', $one['data']['items'][0])
    && !array_key_exists('revisions', $one['data']['items'][0]),
    'one persisted incidence is projected without exposing M06 reason, evidence or revision history');

$deniedReader = $reader(static fn(): array => ['ok' => false, 'error' => 'permission_denied']);
$denied = $deniedReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($denied['error']['kind'] ?? '') === 'not_authorized', 'M06 ACL denial remains distinct from SQL unavailability');

$unavailableReader = $reader(static fn(): array => ['ok' => false, 'error' => 'sql_read_failed']);
$unavailable = $unavailableReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($unavailable['error']['kind'] ?? '') === 'read_unavailable', 'M06 SQL failure is not represented as an empty result');

$incompleteReader = $reader(static fn(): array => $page([], 1, 1, 1));
$incomplete = $incompleteReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($incomplete['error']['kind'] ?? '') === 'pagination_incomplete', 'pagination totals inconsistent with returned rows fail closed');

$foreignCalled = false;
$foreignReader = $reader(static function () use (&$foreignCalled): array {
    $foreignCalled = true;
    return ['ok' => false, 'error' => 'unexpected'];
});
$foreign = $foreignReader->readForWorker($actor, 2, '2026-11-26', 565, [1]);
$assert(($foreign['error']['kind'] ?? '') === 'not_authorized' && !$foreignCalled,
    'unauthorized locality is rejected before calling M06');

$wrongWorkerReader = $reader(static fn(Usuario $user, int $locality, array $filters, int $pageNumber, int $perPage): array => $page([array_merge($incidence, ['worker_id' => 999])], 1));
$wrongWorker = $wrongWorkerReader->readForWorker($actor, 1, '2026-11-26', 565, [1]);
$assert(($wrongWorker['error']['kind'] ?? '') === 'persisted_data_inconsistent',
    'M06 result for a different worker is rejected instead of crossing M01 row scope');

echo "RESULT {$passes} PASS {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);
