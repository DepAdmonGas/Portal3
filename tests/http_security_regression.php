<?php

declare(strict_types=1);

$helper = (string) file_get_contents(__DIR__ . '/../public/assets/js/core/http-security.js');
$layouts = implode('', array_map(static fn (string $path): string => (string) file_get_contents($path), glob(__DIR__ . '/../app/Views/layouts/{main,sgm,sasisopa,configuracion}.php', GLOB_BRACE)));
$scroll = (string) file_get_contents(__DIR__ . '/../public/assets/js/sgm/scroll-state.js');

$tests = [
    'CSRF meta source used' => str_contains($helper, 'meta[name="csrf-token"]'),
    'Axios default CSRF header configured' => str_contains($helper, "defaults.headers.common['X-CSRF-TOKEN']"),
    'No hardcoded token or automatic retry in helper' => !preg_match('/csrf-token\s*=\s*[\'\"](?!meta)/i', $helper) && !str_contains($helper, 'interceptors'),
    'Shared helper and SGM scroll assets are loaded externally' => substr_count($layouts, "js/core/http-security.js") === 4 && str_contains($layouts, 'js/sgm/scroll-state.js') && str_contains($scroll, 'DOMContentLoaded'),
];

$passed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $passed += $ok ? 1 : 0;
}

printf("RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, count($tests) - $passed);
exit($passed === count($tests) ? 0 : 1);
