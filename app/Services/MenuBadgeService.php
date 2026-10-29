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

    /**
     * Asigna los contadores de los módulos de Dirección de Operaciones a los
     * grupos del menú, usando el mismo cálculo para /home y /departamento-operativo.
     *
     * @param array<int, array<string, mixed>> $grupos
     * @return array<int, array<string, mixed>>
     */
    public static function aplicarContadores(array $grupos, int $idUsuario, string $campoBadge): array
    {
        foreach ($grupos as &$grupo) {
            if (!isset($grupo['items']) || !is_array($grupo['items'])) {
                continue;
            }

            foreach ($grupo['items'] as &$item) {
                $clave = (string)($item['clave'] ?? '');
                if ($clave === '') {
                    continue;
                }

                $cantidad = self::contar($clave, $idUsuario);
                if ($cantidad !== null) {
                    $item[$campoBadge] = $cantidad;
                }
            }
            unset($item);
        }
        unset($grupo);

        return $grupos;
    }

    public static function contar(int|string $menuId, int $idUsuario): ?int
    {
        if ($idUsuario <= 0) {
            return null;
        }

        if (is_string($menuId)) {
            $cantidad = match ($menuId) {
                'solicitud-cheque' => SolicitudChequeService::getPendientesFirmaCard(),
                'aclaracion-voucher' => AclaracionVoucherService::getPendientesMesActual(),
                'formatos' => FormatosService::getPendientesFirmaCard(),
                'bitacora-rrhh' => BitacoraRrhhService::getPendientesCardMesActual(),
                'pedido-pinturas' => PedidoPinturasService::getPendientesFirmaVoBoCard(),
                'pedido-papeleria' => PedidoPapeleriaService::getPendientesFirmaVoBoCard(),
                'pedido-articulos-limpieza' => PedidoArticulosLimpiezaService::getPendientesFirmaVoBoCard(),
                'pedido-aditivo' => PedidoAditivoService::getPendientesFirmaVoBoCard(),
                'orden-mantenimiento' => count(OrdenMantenimientoService::pendingSignatureItems($idUsuario)),
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
