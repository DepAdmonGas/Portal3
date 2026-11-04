<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();
$_ENV['APP_TIMEZONE'] ??= 'America/Mexico_City';
App\Core\Bootstrap::init();
App\Core\Database::initialize();

try {
    $result = App\Services\RefaccionesReporteCorreoService::enviarReporteMensualAnterior();
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(($result['success'] ?? false) ? 0 : 1);
} catch (Throwable $error) {
    error_log('[RefaccionesReporteMensual] ' . $error->getMessage());
    fwrite(STDERR, "No se pudo procesar el reporte mensual de Refacciones. Revisa el log de errores.\n");
    exit(1);
}
