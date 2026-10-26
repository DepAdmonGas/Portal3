<?php

namespace App\Controllers;

use App\Services\MenuDptoOperativoService;
use App\Services\MenuBadgeService;
use App\Core\Auth;

/**
 * Menú lateral de Departamento Operativo.
 *
 * Espejo de MenuController, pero para /departamento-operativo: devuelve los
 * grupos del sidebar en JSON. Los permisos los resuelve
 * ModuloDptoOperativoService a través de MenuDptoOperativoService.
 */
class MenuDoController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $user = Auth::user();

            if (!$user || empty($user->id)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No autenticado',
                    'data'    => [],
                ]);

                return;
            }

            $menus = MenuDptoOperativoService::paraUsuario((int)$user->id);
            foreach ($menus as &$grupo) {
                foreach ($grupo['items'] ?? [] as &$item) {
                    $cantidad = MenuBadgeService::contar((string)($item['clave'] ?? ''), (int)$user->id);
                    if ($cantidad !== null) {
                        $item['badge_count'] = $cantidad;
                    }
                }
                unset($item);
            }
            unset($grupo);

            echo json_encode([
                'success' => true,
                'data'    => $menus,
            ]);

        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener el menú',
                'error'   => $e->getMessage(),
                'data'    => [],
            ]);
        }
    }
}
