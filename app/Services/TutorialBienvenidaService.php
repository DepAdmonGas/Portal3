<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\TutorialUsuario;
use Illuminate\Database\QueryException;
use Throwable;

final class TutorialBienvenidaService
{
    private const IMAGE_SLOTS = [
        'bienvenida',
        'navegacion-sidebar',
        'breadcrumbs-periodo',
        'cards-componentes',
        'alertas-notificaciones',
        'dropdowns-botones-iconos',
        'datatables',
        'estatus-indicadores',
        'modo-oscuro',
        'perfil-foto-telegram',
        'perfil-datos',
        'perfil-telegram',
        'perfil-firma',
        'multiestacion',
        'multiestacion-todas',
        'checador',
    ];

    public static function capturasDisponibles(): array
    {
        $directorio = PUBLIC_PATH . '/assets/images/tutorial-bienvenida/';

        return array_values(array_filter(
            self::IMAGE_SLOTS,
            static fn (string $slot): bool => is_file($directorio . $slot . '.webp')
                || (in_array($slot, ['navegacion-sidebar', 'breadcrumbs-periodo', 'cards-componentes', 'dropdowns-botones-iconos', 'alertas-notificaciones', 'datatables', 'estatus-indicadores', 'modo-oscuro', 'checador', 'multiestacion', 'multiestacion-todas', 'perfil-foto-telegram', 'perfil-datos', 'perfil-telegram', 'perfil-firma'], true)
                    && is_file($directorio . $slot . '.gif'))
        ));
    }

    public static function capturasAnimadas(): array
    {
        $directorio = PUBLIC_PATH . '/assets/images/tutorial-bienvenida/';
        $animaciones = ['navegacion-sidebar', 'breadcrumbs-periodo', 'cards-componentes', 'dropdowns-botones-iconos', 'alertas-notificaciones', 'datatables', 'estatus-indicadores', 'modo-oscuro', 'checador', 'multiestacion', 'multiestacion-todas', 'perfil-foto-telegram', 'perfil-datos', 'perfil-telegram', 'perfil-firma'];

        return array_values(array_filter(
            $animaciones,
            static fn (string $slot): bool => is_file($directorio . $slot . '.gif')
                && is_file($directorio . $slot . '.webp')
        ));
    }

    public static function capturasAnimadasAltaCalidad(): array
    {
        $directorio = PUBLIC_PATH . '/assets/images/tutorial-bienvenida/';

        return array_values(array_filter(
            self::capturasAnimadas(),
            static fn (string $slot): bool => is_file($directorio . $slot . '.anim.webp')
        ));
    }

    public static function estado(int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            return 'unavailable';
        }

        try {
            return TutorialUsuario::query()
                ->where('id_usuario', $idUsuario)
                ->exists() ? 'completed' : 'pending';
        } catch (Throwable $e) {
            // Un problema opcional del tutorial nunca debe impedir cargar Home.
            Logger::error('No se pudo consultar el estado del tutorial de bienvenida.', [
                'id_usuario' => $idUsuario,
                'exception_class' => $e::class,
            ]);

            return 'unavailable';
        }
    }

    public static function estaPendiente(int $idUsuario): bool
    {
        return self::estado($idUsuario) === 'pending';
    }

    public static function completar(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        try {
            TutorialUsuario::query()->firstOrCreate(
                ['id_usuario' => $idUsuario],
                ['completado_en' => date('Y-m-d H:i:s')]
            );

            return true;
        } catch (QueryException $e) {
            // Una petición concurrente puede ganar la inserción única; ese estado
            // ya satisface la operación idempotente.
            try {
                if (TutorialUsuario::query()->where('id_usuario', $idUsuario)->exists()) {
                    return true;
                }
            } catch (Throwable) {
                // Se conserva la excepción original para el registro del controlador.
            }

            throw $e;
        }
    }
}
