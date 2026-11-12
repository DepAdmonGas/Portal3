<?php

declare(strict_types=1);

/**
 * Independent API ↔ Portal3 canonical-contract check.
 *
 * Run only with an authorized local API checkout, for example:
 * CHECADOR_API_CONTRACT_REPO=/workspace/portal-api php tests/checador_contract_parity.php
 *
 * This file is intentionally not part of run_security_suite.php: the public
 * Portal3 CI runner does not check out the private API repository.
 */
$apiRoot = getenv('CHECADOR_API_CONTRACT_REPO');
if (!is_string($apiRoot) || trim($apiRoot) === '') {
    fwrite(STDOUT, "CROSS_REPOSITORY_PARITY: NOT_VERIFIED (authorized API checkout path not provided)\n");
    fwrite(STDOUT, "Set CHECADOR_API_CONTRACT_REPO to an authorized API checkout and run this script separately.\n");
    exit(2);
}

$apiRoot = realpath($apiRoot);
if ($apiRoot === false || !is_dir($apiRoot)) {
    fwrite(STDERR, "CROSS_REPOSITORY_PARITY: FAIL (configured API checkout is unavailable)\n");
    exit(1);
}

$contracts = [
    'P3F journey API contract' => [
        'docs/v2/CHECADOR_V2_JOURNEY_ON_DEMAND_API_CONTRACT_V1.1.md',
        '7f71372d9deceb6820e6e71e1be38dadd12e87cd5bb4a5f69bcd250ee85c6f3f',
    ],
    'P3H event classification API contract' => [
        'docs/v2/CHECADOR_V2_P3H_EVENT_CLASSIFICATION_PUBLIC_PAYLOAD_CONTRACT_V1.1.md',
        'df6b0bec7c6977cb0c12bf5409c8243528af145036474aa3adcf237b21f4711c',
    ],
    'P3G administrative API contract' => [
        'docs/v2/CHECADOR_V2_P3G_ADMINISTRATIVE_API_BOUNDARY_CONTRACT_V1.0.md',
        'e418687501fbdfdf89bcc6f11aab60e49226f265411c4590a39a5fa7799c374a',
    ],
    'Checador Architecture Freeze' => [
        'docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md',
        '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85',
    ],
];

$failures = 0;
foreach ($contracts as $label => [$relativePath, $expectedHash]) {
    $path = $apiRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $actualHash = is_file($path) ? hash_file('sha256', $path) : false;
    $valid = is_string($actualHash) && hash_equals($expectedHash, $actualHash);
    if (!$valid) {
        $failures++;
    }
    printf("%s %s (%s)\n", $valid ? 'PASS' : 'FAIL', $label, $relativePath);
}

$p3hContract = $apiRoot . '/docs/v2/CHECADOR_V2_P3H_EVENT_CLASSIFICATION_PUBLIC_PAYLOAD_CONTRACT_V1.1.md';
$routeDeclared = is_file($p3hContract)
    && str_contains((string) file_get_contents($p3hContract), 'POST /api/v2/journeys/evaluate');
if (!$routeDeclared) {
    $failures++;
}
echo ($routeDeclared ? 'PASS' : 'FAIL') . " P3H canonical API contract declares the journey evaluation endpoint\n";

if ($failures > 0) {
    printf("CROSS_REPOSITORY_PARITY: FAIL (%d mismatch(es); local behavior tests remain independent)\n", $failures);
    exit(1);
}

echo "CROSS_REPOSITORY_PARITY: PASS (canonical files and expected hashes verified)\n";
