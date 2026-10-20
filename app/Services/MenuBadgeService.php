<?php

namespace App\Services;

/**
 * Contadores que se muestran como badge en el menú del Home.
 *
 * Centraliza el cálculo para que MenuService no dependa de cada módulo.
 * Devuelve null cuando el menú no tiene contador asociado.
 */
class MenuBadgeService
{
    /** Id del menú de Comunicados en el Home. */
    private const MENU_COMUNICADOS = 9;

    public static function contar(int $menuId, int $idUsuario): ?int
    {
        if ($idUsuario <= 0) {
            return null;
        }

        if ($menuId === self::MENU_COMUNICADOS) {
            return ComunicadosDoService::contarPendientes($idUsuario);
        }

        return null;
    }
}
