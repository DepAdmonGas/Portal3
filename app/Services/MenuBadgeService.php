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

    public static function contar(int|string $menuId, int $idUsuario): ?int
    {
        if ($idUsuario <= 0) {
            return null;
        }

        if (is_string($menuId)) {
            $cantidad = match ($menuId) {
                'solicitud-cheque' => SolicitudChequeService::getPendientesFirmaCard(),
                'pedido-pinturas' => PedidoPinturasService::getPendientesFirmaVoBoCard(),
                'pedido-papeleria' => PedidoPapeleriaService::getPendientesFirmaVoBoCard(),
                'pedido-articulos-limpieza' => PedidoArticulosLimpiezaService::getPendientesFirmaVoBoCard(),
                'pedido-aditivo' => PedidoAditivoService::getPendientesFirmaVoBoCard(),
                default => 0,
            };

            return $cantidad > 0 ? (int)$cantidad : null;
        }

        if ($menuId === self::MENU_COMUNICADOS) {
            return ComunicadosDoService::contarPendientes($idUsuario);
        }

        return null;
    }
}
