<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\SolicitudesFirmaDoService;

/**
 * Datos de "Solicitudes pendientes (Firma)" del sidebar de Departamento
 * Operativo. La consulta está reservada a los usuarios 2 y 19.
 */
class SolicitudesFirmaDoController extends BaseController
{
    public function data(): void
    {
        if (!SolicitudesFirmaDoService::autorizado()) {
            JsonResponse::forbidden('No tienes permiso para consultar las solicitudes pendientes de firma.');
        }

        $usuario = Auth::user();
        $idUsuario = (int) ($usuario->id ?? 0);

        $tipo = (string) Request::input('tipo', SolicitudesFirmaDoService::TIPO_GENERAL);

        if (!SolicitudesFirmaDoService::tipoValido($idUsuario, $tipo)) {
            $tipo = SolicitudesFirmaDoService::TIPO_GENERAL;
        }

        $resultado = SolicitudesFirmaDoService::listar($idUsuario, $tipo);

        JsonResponse::custom([
            'success' => true,
            'tipo' => $tipo,
            'total' => $resultado['total'],
            'items' => $resultado['items'],
            'tipos' => SolicitudesFirmaDoService::tipos($idUsuario),
        ]);
    }
}
