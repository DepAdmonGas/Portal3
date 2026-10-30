<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/support/portal3_autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/solicitud-tarjetas/update-reporte-formulario') {
    http_response_code(404);
    echo json_encode(['success' => false, 'type' => 'not_found']);
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'type' => 'method_not_allowed']);
    return;
}

$controller = (new ReflectionClass(App\Controllers\TarjetasController::class))
    ->newInstanceWithoutConstructor();
$controller->updateReporteFormulario();
