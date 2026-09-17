<?php

declare(strict_types=1);

$tests = [
    'p0_security_regression.php',
    'csrf_post_login_regression.php',
    'data_valid_011_regression.php',
    'dep_test_013_a_axios_pinning_regression.php',
    'http_security_regression.php',
    'layout_csrf_logout_regression.php',
    'main_response_policy_regression.php',
    'privacy_logging_security_regression.php',
    'rate_limiter_security_regression.php',
    'session_post_login_regression.php',
    'session_security_regression.php',
    'telegram_webhook_security_regression.php',
    'csp_report_only_regression.php',
];

$passed = $failed = $skipped = 0;
foreach ($tests as $test) {
    $path = __DIR__ . '/' . $test;
    echo "RUN: {$test}\n";
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    echo implode("\n", $output) . "\n";
    $testOutput = implode("\n", $output);
    preg_match_all('/^PASS(?:\b|:)/m', $testOutput, $passMatches);
    preg_match_all('/^FAIL(?:\b|:)/m', $testOutput, $failMatches);
    preg_match_all('/^SKIP(?:PED)?(?:\b|:)/im', $testOutput, $skipMatches);
    $passed += count($passMatches[0]);
    $failed += count($failMatches[0]);
    $skipped += count($skipMatches[0]);
    if ($exitCode !== 0) {
        echo "FAIL: {$test} (exit {$exitCode})\n";
        $failed++;
    } else {
        echo "PASS: {$test}\n";
    }
}

printf("SECURITY_SUITE_RESULT: %d PASS / %d FAIL / %d SKIPPED\n", $passed, $failed, $skipped);
exit($failed === 0 ? 0 : 1);
