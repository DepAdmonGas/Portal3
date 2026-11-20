<?php

declare(strict_types=1);

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$root = __DIR__ . '/..';
$access = file_get_contents($root . '/app/Services/ChecadorAccessService.php') ?: '';
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$moduleStation = file_get_contents($root . '/app/Services/ModuleStationService.php') ?: '';
$moduleContext = file_get_contents($root . '/app/Controllers/ModuleContextController.php') ?: '';
$writer = file_get_contents($root . '/app/Services/ChecadorShiftTemplateSqlWriteService.php') ?: '';
$weeklyWriter = file_get_contents($root . '/app/Services/ChecadorWeeklyScheduleSqlWriteService.php') ?: '';

$adminStart = strpos($access, 'public static function authorizeStationTemplateAdministration');
$adminEnd = strpos($access, '/** Dedicated M11 attendance-policy capability', $adminStart ?: 0);
$adminMethod = $adminStart !== false && $adminEnd !== false ? substr($access, $adminStart, $adminEnd - $adminStart) : '';
$requestStart = strpos($controller, 'private function requestM04TemplateWorkplaceId');
$requestEnd = strpos($controller, 'private function sessionWorkplaceId', $requestStart ?: 0);
$requestMethod = $requestStart !== false && $requestEnd !== false ? substr($controller, $requestStart, $requestEnd - $requestStart) : '';

$assert($adminMethod !== ''
    && str_contains($adminMethod, 'self::authorize($autolavadoViaPaloSolo ? self::PALO_SOLO_LOCALITY_ID : $requestedLocalityId)')
    && str_contains($adminMethod, 'Usuario::query()->where(\'id\', (int) $authenticated->id)->where(\'estatus\', 0)')
    && str_contains($adminMethod, 'self::ENCARGADO_PUESTO_ID')
    && str_contains($adminMethod, 'ChecadorV2PermissionService::STATION_TEMPLATE_ADMIN')
    && str_contains($adminMethod, 'count($ids) !== 1 || $ids[0] !== self::PALO_SOLO_LOCALITY_ID')
    && str_contains($adminMethod, 'self::isDepartmentLocality(self::AUTOLAVADO_LOCALITY_ID'),
    'Only active Encargado with effective station-template permission and exact Palo Solo locality 2 scope can use the M04 exception');
$assert($adminMethod !== '' && str_contains($adminMethod, '$requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID')
    && str_contains($adminMethod, 'RhLocalidad::query()->whereKey(self::AUTOLAVADO_LOCALITY_ID)'),
    'The exception target is canonical Autolavado 9 and requires its configured department row');
$assert($requestMethod !== ''
    && str_contains($requestMethod, 'getChecadorM04TemplateWorkplaceContext()')
    && str_contains($requestMethod, '$requestedId !== ChecadorAccessService::AUTOLAVADO_LOCALITY_ID')
    && str_contains($requestMethod, 'authorizeStationTemplateAdministration($departmentId)'),
    'M04 writes require the selected server-side Autolavado 9 context and repeat authorization');
$assert(str_contains($moduleStation, 'setChecadorM04TemplateWorkplaceContext')
    && str_contains($moduleContext, 'authorizeStationTemplateAdministration($idDepto)')
    && str_contains($moduleContext, 'setChecadorM04TemplateWorkplaceContext($idDepto)'),
    'The selector persists department 9 only through the dedicated M04 authorization gate');
$assert(str_contains($controller, '$scheduleHistoryMode')
    && str_contains($controller, '$requestedWorkplace === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID')
    && str_contains($controller, 'authorizeShiftTemplateCatalogRead($requestedWorkplace)')
    && str_contains($controller, "'moduleStationExtraDepartments' =>"),
    'Only the M04 history route adds and accepts the Autolavado selector option');
$assert(str_contains($writer, "'id_estacion' => \$workplaceId")
    && str_contains($writer, "'id_estacion' => \$workplaceId, 'source_global_clave'")
    && str_contains($writer, "'id_usuario_actor' => (int) \$actor->id, 'id_estacion' => \$workplaceId")
    && str_contains($writer, 'ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)'),
    'Template versions, lineage and audit use the authorized canonical target locality');
$assert(str_contains($weeklyWriter, 'authorizeScheduling($workplaceId, \'editar\')')
    && !str_contains($weeklyWriter, 'authorizeStationTemplateAdministration'),
    'M03 weekly assignment writes retain their independent scheduling authorization');

echo "\n{$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);
