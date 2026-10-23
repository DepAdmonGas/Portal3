<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/support/portal3_autoload.php';

use App\Services\SeguroService;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) !== '/validate') {
    http_response_code(404);
    exit('not found');
}

// This test-only loopback endpoint invokes only the production validator.
// It deliberately does not call SeguroService::crear() or touch storage/DB.
$validator = new ReflectionMethod(SeguroService::class, 'validarArchivo');
$result = $validator->invoke(null, $_FILES['upload'] ?? null);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_THROW_ON_ERROR);
