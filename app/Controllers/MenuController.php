<?php

namespace App\Controllers;

use App\Services\MenuService;
use App\Core\Auth;

class MenuController
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            // Usuario autenticado
            $user = Auth::user();

            if (!$user || empty($user->id)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No autenticado',
                    'data' => []
                ]);
                return;
            }

            // Módulo (limpio)
            $modulo = isset($_GET['modulo'])
                ? trim($_GET['modulo'])
                : null;

            if ($modulo === '') {
                $modulo = null;
            }

            // Obtener menú
            $menus = MenuService::getMenuByUsuario($user->id, $modulo);

            echo json_encode([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener menú',
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
    }
}
