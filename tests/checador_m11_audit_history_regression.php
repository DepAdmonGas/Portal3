<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorConfigurationReadService;
use App\Services\ChecadorV2PermissionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::schema()->create('op_rh_v2_m11_configuration_audit', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('actor_portal_user_id')->nullable();
    $table->string('permission_key');
    $table->unsignedInteger('workplace_id')->nullable();
    $table->string('entity_type');
    $table->unsignedInteger('entity_id')->nullable();
    $table->string('action');
    $table->string('created_at_utc');
});

$insert = static function (string $entity, string $action, ?int $workplace, string $permission, string $timestamp, int $actor = 7): void {
    Capsule::table('op_rh_v2_m11_configuration_audit')->insert([
        'actor_portal_user_id' => $actor,
        'permission_key' => $permission,
        'workplace_id' => $workplace,
        'entity_type' => $entity,
        'entity_id' => 11,
        'action' => $action,
        'created_at_utc' => $timestamp,
    ]);
};

for ($index = 1; $index <= 4; $index++) {
    $insert('POSITION', $index === 1 ? 'CREATE' : 'UPDATE', null,
        ChecadorV2PermissionService::POSITION_CATALOG_EDIT, sprintf('2026-11-18 10:%02d:00', $index));
}
for ($index = 1; $index <= 12; $index++) {
    $insert('READER_PROFILE', $index === 1 ? 'UPDATE_USERNAME' : 'ROTATE_OR_UPDATE', 1,
        ChecadorV2PermissionService::READER_PROFILE_EDIT,
        sprintf('2026-11-18 11:%02d:00', min($index, 11)));
}
$insert('READER_PROFILE', 'UPDATE_USERNAME', 2, ChecadorV2PermissionService::READER_PROFILE_EDIT, '2026-11-18 12:00:00');
$insert('POSITION', 'UPDATE', 1, ChecadorV2PermissionService::POSITION_CATALOG_EDIT, '2026-11-18 13:00:00');
$insert('READER_PROFILE', 'UPDATE_USERNAME', 2, ChecadorV2PermissionService::READER_PROFILE_EDIT, '2026-11-18 13:01:00');
$insert('READER_PROFILE', 'UPDATE_USERNAME', 1, ChecadorV2PermissionService::POSITION_CATALOG_EDIT, '2026-11-18 13:02:00');
$insert('attendance_policy', 'CREATE_VERSION', 1, ChecadorV2PermissionService::ATTENDANCE_POLICY_EDIT, '2026-11-18 13:03:00');
$insert('attendance_policy', 'CREATE_VERSION', 2, ChecadorV2PermissionService::ATTENDANCE_POLICY_EDIT, '2026-11-18 13:04:00');

$reader = new ReflectionMethod(ChecadorConfigurationReadService::class, 'readM11AuditHistory');
$reader->setAccessible(true);
$read = static fn(int $workplace, int $page, bool $positions, bool $profiles, bool $policy = false): array => $reader->invoke(null, $workplace, $page, $positions, $profiles, $policy);

$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationReadService.php') ?: '';
$assert(str_contains($serviceSource, "authorizePositionCatalog(\$workplaceId, 'leer')")
    && str_contains($serviceSource, "authorizeReaderProfiles(\$workplaceId, 'leer')")
    && str_contains($serviceSource, 'self::readM11AuditHistory('), 'history reader is reached only through independent effective read authorizers');
$assert(str_contains($serviceSource, "->whereNull('workplace_id')")
    && str_contains($serviceSource, "->where('workplace_id', \$workplaceId)")
    && str_contains($serviceSource, "->where('entity_type', 'attendance_policy')")
    && str_contains($serviceSource, "->orderByDesc('created_at_utc')->orderByDesc('id')"), 'query scopes global and local events, including policy versions, and orders deterministically');

$direction = $read(1, 1, true, true, true);
$assert(($direction['status'] ?? '') === 'ready'
    && ($direction['pagination']['total_records'] ?? 0) === 17
    && count($direction['items'] ?? []) === 15
    && ($direction['pagination']['total_pages'] ?? 0) === 2,
    'Dirección sees global positions plus only the selected locality, with bounded server pagination');
$assert(($direction['items'][0]['module'] ?? '') === 'Configuración de asistencia'
    && ($direction['items'][0]['action'] ?? '') === 'Nueva vigencia'
    && ($direction['items'][0]['id'] ?? 0) > ($direction['items'][1]['id'] ?? PHP_INT_MAX),
    'timestamp ties use descending IDs and policy-version events are labeled accurately');
$assert(($direction['items'][0]['module'] ?? '') === 'Configuración de asistencia'
    && ($direction['items'][0]['action'] ?? '') === 'Nueva vigencia', 'authorized history shows real policy-version events from the shared M11 audit table');
$directionPageTwo = $read(1, 2, true, true, true);
$assert(count($directionPageTwo['items'] ?? []) === 2
    && ($directionPageTwo['pagination']['has_previous'] ?? false) === true
    && ($directionPageTwo['pagination']['has_next'] ?? true) === false,
    'second history page returns the remaining event and correct navigation state');

$encargado = $read(1, 1, false, true, true);
$assert(($encargado['status'] ?? '') === 'ready'
    && ($encargado['pagination']['total_records'] ?? 0) === 13
    && count(array_filter($encargado['items'], static fn(array $item): bool => $item['module'] === 'Puestos')) === 0,
    'Encargado sees only local reader events and never global position events');
$otherLocality = $read(2, 1, false, true, true);
$assert(($otherLocality['pagination']['total_records'] ?? 0) === 3
    && ($otherLocality['items'][0]['timestamp_utc'] ?? '') === '2026-11-18 13:04:00',
    'changing locality returns only events for the newly authorized locality');
$denied = $read(1, 1, false, false);
$assert(($denied['status'] ?? '') === 'permission_denied' && ($denied['items'] ?? []) === [],
    'no effective read grant returns denied without querying or showing events');

Capsule::table('op_rh_v2_m11_configuration_audit')->delete();
$empty = $read(1, 1, true, true);
$assert(($empty['status'] ?? '') === 'ready' && ($empty['items'] ?? null) === []
    && ($empty['pagination']['total_records'] ?? -1) === 0, 'authorized empty table is distinct from a read failure');
Capsule::schema()->drop('op_rh_v2_m11_configuration_audit');
$failure = $read(1, 1, true, true);
$assert(($failure['status'] ?? '') === 'unavailable' && ($failure['items'] ?? null) === [],
    'missing or failed audit source is reported unavailable without synthetic rows');

printf("CHECADOR_M11_AUDIT_HISTORY: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
