<?php

declare(strict_types=1);

$paths = [
    __DIR__ . '/../app/Views/layouts/main.php',
    __DIR__ . '/../app/Views/layouts/sgm.php',
    __DIR__ . '/../app/Views/layouts/sasisopa.php',
    __DIR__ . '/../app/Views/layouts/configuracion.php',
    __DIR__ . '/../app/Views/layouts/auth.php',
];
$contents = array_map(static fn (string $path): string => (string) file_get_contents($path), $paths);
$joined = implode('', $contents);
$deferred = (string) file_get_contents(__DIR__ . '/../app/Views/layouts/departamento-operativo.php');

$tests = [
    'Exactly five in-scope Axios references are exact-pinned' => substr_count($joined, 'https://cdn.jsdelivr.net/npm/axios@1.7.9/dist/axios.min.js') === 5,
    'No unversioned Axios URL remains in scope' => !str_contains($joined, 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js'),
    'Departamento-operativo remains unchanged and deferred' => str_contains($deferred, "asset('libs/axios/dist/axios.min.js')") && str_contains($deferred, 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js'),
    'HTTP security loads after Axios' => count(array_filter($contents, static fn (string $html): bool => strpos($html, 'axios@1.7.9') < strpos($html, 'js/core/http-security.js'))) === 4,
    'No duplicate Axios loads in modified layouts' => count(array_filter($contents, static fn (string $html): bool => substr_count($html, 'axios@1.7.9') > 1)) === 0,
];

$passed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $passed += $ok ? 1 : 0;
}

printf("RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, count($tests) - $passed);
exit($passed === count($tests) ? 0 : 1);
