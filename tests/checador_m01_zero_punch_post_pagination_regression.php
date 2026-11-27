<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$reader = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyAttendanceSqlReadService.php') ?: '';
$fixtureTest = file_get_contents(__DIR__ . '/checador_m01_daily_attendance_sql_read_regression.php') ?: '';
$helperStart = strpos($controller, 'private static function readDailySqlAttendance(');
$helperEnd = $helperStart === false ? false : strpos($controller, 'private static function m06AdministrativeIncidenceSqlService()', $helperStart);
$helper = $helperStart !== false && $helperEnd !== false ? substr($controller, $helperStart, $helperEnd - $helperStart) : '';
$checks = [
    'M01 zero-event processing uses the SQL reader and not the legacy API client' =>
        str_contains($controller, 'self::readDailySqlAttendance(')
        && !str_contains($helper, 'ChecadorDailyReadService')
        && !str_contains($helper, 'readObservedPunches(')
        && str_contains($helper, 'readDay($workplaceId, $date, $authorizedWorkplaceIds, $requestedWorkerId)'),
    'zero matching rows are explicitly a successful empty observed set in isolated fixtures' =>
        str_contains($fixtureTest, 'zero matching SQL rows are a successful empty observed-event result')
        && str_contains($reader, "'events' => \$events")
        && str_contains($reader, "'source_completeness' => 'UNKNOWN'"),
    'M01 keeps the persisted daily window and never infers journey results from raw events' =>
        str_contains($reader, "'from_utc' =>")
        && str_contains($reader, "'to_utc' =>")
        && str_contains($helper, "'persisted_schedule_projection_unavailable'")
        && str_contains($helper, "'journey_evaluation_state' => 'not_evaluated'"),
];
$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) $failures++;
}
printf("CHECADOR_M01_ZERO_PUNCH_POST_PAGINATION: %d PASS / %d FAIL\n", count($checks) - $failures, $failures);
exit($failures === 0 ? 0 : 1);
