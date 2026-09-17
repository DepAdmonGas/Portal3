<?php

declare(strict_types=1);

$policy = (string) file_get_contents(__DIR__ . '/../public/assets/js/core/main-response-policy.js');
$main = (string) file_get_contents(__DIR__ . '/../app/Views/layouts/main.php');

$tests = [
    'main response policy is externalized' => str_contains($main, 'js/core/main-response-policy.js') && !preg_match('/<script>(?s:.*?)axios\.interceptors\.response/', $main),
    'single registration guard exists' => str_contains($policy, '__portal3MainResponsePolicyInitialized') && substr_count($policy, 'interceptors.request.use') === 1 && substr_count($policy, 'interceptors.response.use') === 1,
    '419 reloads without replay' => str_contains($policy, 'error.response.status === 419') && str_contains($policy, 'window.location.reload()') && !str_contains($policy, 'return axios(error.config)'),
    'non-419 errors propagate' => str_contains($policy, 'return Promise.reject(error)'),
];

$passed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $passed += $ok ? 1 : 0;
}

printf("RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, count($tests) - $passed);
exit($passed === count($tests) ? 0 : 1);
