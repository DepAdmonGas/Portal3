<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/Controllers/ChecadorController.php');
$reader = (string) file_get_contents($root . '/app/Services/ChecadorRawPunchReadService.php');
$roster = (string) file_get_contents($root . '/app/Services/ChecadorAttendanceRosterService.php');
$access = (string) file_get_contents($root . '/app/Services/ChecadorAccessService.php');
$route = (string) file_get_contents($root . '/routes/web.php');
$assertions = [
    'current roster returns only active canonical workers and validates membership at the selected date' =>
        str_contains($roster, 'function workersForAttendanceFilter(')
        && str_contains($roster, "where('estado', 1)")
        && str_contains($roster, 'RhPersonalLocalidadVersion')
        && str_contains($roster, 'resolveAssignments($workerId, $effectiveAt)'),
    'ambiguous station/locality mappings fail closed through the existing unique bridge' =>
        str_contains($access, 'function resolveActiveStationIdForLocality(')
        && str_contains($access, 'MultiestacionService::convertIds(')
        && str_contains($access, 'mapStationIdsToUniqueLocalities([$stationId])')
        && str_contains($access, 'count($mappedLocalities) !== 1')
        && str_contains($access, '!== $localityId'),
    'historical names are included only when persisted canonical punch attribution matches that locality' =>
        str_contains($reader, 'function searchReportWorkers(')
        && str_contains($reader, 'punches.id_estacion_resuelta = ?')
        && str_contains($reader, 'punches.id_personal_resuelto = person.id')
        && str_contains($reader, 'function findReportWorker('),
    'report and CSV revalidate the selected worker against the current authorized roster or persisted attribution' =>
        str_contains($controller, 'punchReportAuthorizedRoster(')
        && substr_count($controller, 'findReportWorker(') >= 3
        && str_contains($controller, '$roster[\'worker_ids\']'),
    'worker search endpoint remains authenticated and the raw reader stays local-development-only and read-only' =>
        str_contains($route, "'/recursos-humanos/checador/reporte/colaboradores'")
        && str_contains($route, "Route::auth(['ChecadorController', 'punchReportWorkers'])")
        && str_contains($reader, "['dev', 'development', 'local']")
        && !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|GRANT|REVOKE)\b/i', $reader),
    'M02 roster lookup has no weekly schedule or M03 service dependency' =>
        !str_contains(substr($roster, strpos($roster, 'function workersForAttendanceFilter('), strpos($roster, 'function currentRoster(') - strpos($roster, 'function workersForAttendanceFilter(')), 'ChecadorWeeklyScheduleService')
        && !str_contains($reader, 'ChecadorWeeklyScheduleService'),
];
$failed = 0;
foreach ($assertions as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passed) $failed++;
}
printf("M02_ROSTER_SCOPE_RESULT: %d PASS / %d FAIL\n", count($assertions) - $failed, $failed);
exit($failed === 0 ? 0 : 1);
