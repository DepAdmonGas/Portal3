<?php

declare(strict_types=1);

$workflow = (string) file_get_contents(__DIR__ . '/../.github/workflows/security.yml');
$views = array_values(array_filter(glob(__DIR__ . '/../app/Views/**/*.php') ?: [], static fn (string $path): bool => !str_ends_with($path, '/layouts/departamento-operativo.php')));
$cdn = implode('', array_map(static fn (string $path): string => (string) file_get_contents($path), $views ?: []));
$tests = [
    'workflow exists' => $workflow !== '',
    'read-only permissions' => str_contains($workflow, "contents: read") && !str_contains($workflow, 'contents: write'),
    'push and pull_request triggers' => str_contains($workflow, 'push:') && str_contains($workflow, 'pull_request:'),
    'composer install from lock' => str_contains($workflow, 'composer install --no-interaction --prefer-dist --no-progress') && !str_contains($workflow, 'composer update'),
    'security runner and audit present' => str_contains($workflow, 'php tests/run_security_suite.php') && str_contains($workflow, 'composer audit --locked'),
    'no deployment capability' => !preg_match('/\b(ssh|scp|rsync|ftp|deploy|Plesk|migrations?)\b/i', $workflow),
    'exact CDN dependencies have SRI' => substr_count($cdn, 'integrity="sha384-') === 20 && substr_count($cdn, 'crossorigin="anonymous"') === 20,
    'Alpine is exact-pinned in scope and deferred department reference remains' => substr_count($cdn, 'https://unpkg.com/alpinejs@3.17.3/dist/cdn.min.js') === 5 && !str_contains($cdn, 'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'),
];
$passed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $passed += $ok ? 1 : 0;
}
printf("RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, count($tests) - $passed);
exit($passed === count($tests) ? 0 : 1);
