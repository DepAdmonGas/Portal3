<?php

namespace App\Middleware;

use App\Services\ModuloDptoOperativoService;

class DptoOperativoActivoMiddleware
{
    public function handle(): void
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = '/' . trim((string) $path, '/');

        if ($path !== '/departamento-operativo'
            && !str_starts_with($path, '/departamento-operativo/')) {
            return;
        }

        if (ModuloDptoOperativoService::rutaCatalogoActiva($requestUri)) {
            return;
        }

        http_response_code(404);

        if (str_contains($path, '/data') || str_contains($path, '/create')
            || str_contains($path, '/editar') || str_contains($path, '/delete')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'El módulo o submódulo no está disponible.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo 'No encontrado';
        exit;
    }
}
