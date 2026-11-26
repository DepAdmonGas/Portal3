<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonResponse;
use App\Core\Logger;
use App\Services\TutorialBienvenidaService;
use Throwable;

final class TutorialBienvenidaController
{
    public function complete(): never
    {
        $idUsuario = Auth::id();
        if ($idUsuario === null || $idUsuario <= 0) {
            JsonResponse::unauthorized();
        }

        try {
            if (!TutorialBienvenidaService::completar($idUsuario)) {
                JsonResponse::error('No se pudo registrar la finalización del tutorial.', 500);
            }

            JsonResponse::success('Tutorial completado.', ['completed' => true]);
        } catch (Throwable $e) {
            Logger::error('No se pudo guardar la finalización del tutorial de bienvenida.', [
                'id_usuario' => $idUsuario,
                'exception_class' => $e::class,
            ]);

            JsonResponse::error('No se pudo guardar tu avance. Inténtalo de nuevo.', 500);
        }
    }
}
