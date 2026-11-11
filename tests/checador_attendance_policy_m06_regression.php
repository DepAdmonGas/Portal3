<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Services/ChecadorDailyReadService.php';

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $name) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "PASS $name\n");
    } else {
        $failed++;
        fwrite(STDOUT, "FAIL $name\n");
    }
};

$names = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', 'CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', 'CHECADOR_P3G_B_POSTS_ENABLED'];
$original = [];
foreach ($names as $name) {
    $original[$name] = getenv($name);
}
$set = static function (string $name, ?string $value): void {
    if ($value === null) {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
};

try {
    $set('APP_ENV', 'development');
    $set('CHECADOR_API_REAL_POSTS_ENABLED', '0');
    $set('CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', '1');
    $set('CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', '1');
    $set('CHECADOR_P3G_B_POSTS_ENABLED', '0');
    $assert(\App\Services\ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(), 'M11 writer uses its dedicated development gate');
    $assert(!\App\Services\ChecadorDailyReadService::p3gBPostOperationsEnabled(), 'M11 gate does not turn on M06 incidence writes');
    $assert(!\App\Services\ChecadorDailyReadService::realApiPostOperationsEnabled(), 'M11 gate does not enable the global API POST gate');
    $assert(\App\Services\ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(), 'local M11 configuration writer uses an independent development-only gate');

    $set('CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', '0');
    $set('CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', '0');
    $set('CHECADOR_P3G_B_POSTS_ENABLED', '1');
    $assert(!\App\Services\ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(), 'M06 gate does not turn on M11 policy writes');
    $assert(\App\Services\ChecadorDailyReadService::p3gBPostOperationsEnabled(), 'M06 writer uses its separate development gate');
    $assert(!\App\Services\ChecadorDailyReadService::realApiPostOperationsEnabled(), 'M06 gate does not enable the global API POST gate');
    $assert(!\App\Services\ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(), 'M06 gate does not enable M11 local configuration writes');

    $set('APP_ENV', 'production');
    $set('CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', '1');
    $set('CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', '1');
    $set('CHECADOR_P3G_B_POSTS_ENABLED', '1');
    $assert(!\App\Services\ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(), 'M11 gate is disabled outside local development');
    $assert(!\App\Services\ChecadorDailyReadService::p3gBPostOperationsEnabled(), 'M06 gate is disabled outside local development');
    $assert(!\App\Services\ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(), 'M11 local configuration gate is disabled outside local development');
} finally {
    foreach ($original as $name => $value) {
        $set($name, $value === false ? null : $value);
    }
}

$assert(str_contains(file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php'), 'check_date_mismatch'), 'Portal3 requires the declared check date to match the selected daily query');
$assert(str_contains(file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php'), 'check_reference'), 'incidence detail renders the persisted check trace');

fwrite(STDOUT, "RESULT {$passed} PASS / {$failed} FAIL\n");
exit($failed === 0 ? 0 : 1);
