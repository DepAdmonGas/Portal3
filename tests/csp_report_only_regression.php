<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
if ($source === false) {
    fwrite(STDERR, "Unable to read public/index.php\n");
    exit(1);
}

$expectedEnforced = "default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; img-src 'self' data: https: blob:; font-src 'self' https://cdn.jsdelivr.net data: https://cdn.ckeditor.com; connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com; frame-ancestors 'self';";
$expectedReportOnly = "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com 'unsafe-eval'; style-src 'self' https://cdn.jsdelivr.net https://cdn.ckeditor.com 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https://cdn.jsdelivr.net https://cdn.ckeditor.com; connect-src 'self' https://www.admongas.com.mx; object-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';";

$tests = [
    'enforced policy remains unchanged' => str_contains($source, '$enforcedCsp = "' . $expectedEnforced . '";'),
    'report-only header is present' => str_contains($source, "Content-Security-Policy-Report-Only: " . $expectedReportOnly),
    'report-only script sources are explicit' => str_contains($expectedReportOnly, "script-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com"),
    'report-only script-src omits unsafe-inline' => !preg_match("~script-src[^;]*'unsafe-inline'~", $expectedReportOnly),
    'report-only policy keeps temporary unsafe-eval documented' => str_contains($expectedReportOnly, "script-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com 'unsafe-eval'"),
    'report-only policy has no wildcard source' => !preg_match('~(?:^|[; ])(?:default-src|script-src|style-src|connect-src)[^;]*\*~', $expectedReportOnly),
    'report-only policy contains required boundary directives' => str_contains($expectedReportOnly, "object-src 'self';") && str_contains($expectedReportOnly, "base-uri 'self';") && str_contains($expectedReportOnly, "form-action 'self';") && str_contains($expectedReportOnly, "frame-ancestors 'none';"),
    'report-only policy retains temporary inline styles' => str_contains($expectedReportOnly, "style-src 'self' https://cdn.jsdelivr.net https://cdn.ckeditor.com 'unsafe-inline'"),
    'highlight initialization is externalized' => is_file(__DIR__ . '/../public/assets/js/core/highlight-init.js'),
    'layouts do not retain inline highlight initialization' => !preg_match('~hljs\.initHighlightingOnLoad\(\)~', implode('', array_map(static fn (string $path): string => (string) file_get_contents($path), glob(__DIR__ . '/../app/Views/layouts/{sgm,sasisopa}.php', GLOB_BRACE)))),
];

$passed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $passed += $ok ? 1 : 0;
}

printf("RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, count($tests) - $passed);
exit($passed === count($tests) ? 0 : 1);
