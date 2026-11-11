<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Narrow module capabilities independent of the broad Biométricos ACL. */
final class ChecadorV2PermissionService
{
    public const SCHEDULE_READ = 'checador.programacion.leer';
    public const SCHEDULE_EDIT = 'checador.programacion.editar';
    public const TEMPLATE_ADMIN = 'checador.plantillas.administrar';
    public const STATION_TEMPLATE_ADMIN = 'checador.turnos.estacion.administrar';
    public const JOURNEY_READ = 'checador.jornadas.leer';
    public const ATTENDANCE_POLICY_READ = 'checador.configuracion.leer';
    public const ATTENDANCE_POLICY_EDIT = 'checador.configuracion.retardo_incidencias.editar';
    public const READER_PROFILE_READ = 'checador.configuracion.lectores.leer';
    public const READER_PROFILE_EDIT = 'checador.configuracion.lectores.editar';
    public const POSITION_CATALOG_READ = 'checador.configuracion.puestos.leer';
    public const POSITION_CATALOG_EDIT = 'checador.configuracion.puestos.editar';
    public const DEVICE_READ = 'checador-v2-dispositivos.leer';
    public const COMODINES_READ = 'checador.comodines.leer';
    public const COMODINES_REQUEST = 'checador.comodines.solicitar';
    public const COMODINES_APPROVE = 'checador.comodines.aprobar';
    public const COMODINES_CANCEL = 'checador.comodines.cancelar';

    private const PERMISSIONS = [
        self::SCHEDULE_READ, self::SCHEDULE_EDIT, self::TEMPLATE_ADMIN, self::STATION_TEMPLATE_ADMIN, self::JOURNEY_READ,
        self::ATTENDANCE_POLICY_READ, self::ATTENDANCE_POLICY_EDIT,
        self::READER_PROFILE_READ, self::READER_PROFILE_EDIT,
        self::POSITION_CATALOG_READ, self::POSITION_CATALOG_EDIT,
        self::DEVICE_READ,
        self::COMODINES_READ, self::COMODINES_REQUEST, self::COMODINES_APPROVE, self::COMODINES_CANCEL,
    ];

    public static function allows(int $userId, int $positionId, string $permission): bool
    {
        if ($userId < 1 || $positionId < 1 || !in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }

        try {
            $userGrant = Capsule::table('op_rh_v2_permission_grants')
                ->where('permission_key', $permission)
                ->where('principal_type', 'USER')
                ->where('principal_id', $userId)
                ->first();
            if ($userGrant !== null) {
                return self::effectiveGrantDecision([
                    'active' => (int) ($userGrant->active ?? 0),
                    'granted' => (int) ($userGrant->granted ?? 0),
                ], null);
            }

            $positionGrant = Capsule::table('op_rh_v2_permission_grants')
                ->where('permission_key', $permission)
                ->where('principal_type', 'POSITION')
                ->where('principal_id', $positionId)
                ->first();
            return self::effectiveGrantDecision(null, $positionGrant === null ? null : [
                'active' => (int) ($positionGrant->active ?? 0),
                'granted' => (int) ($positionGrant->granted ?? 0),
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    /** Comodines is direction-only; the separate API authorization remains authoritative. */
    public static function allowsComodines(int $userId, int $positionId, string $permission): bool
    {
        if ($positionId !== 13 || !in_array($permission, [
            self::COMODINES_READ,
            self::COMODINES_REQUEST,
            self::COMODINES_APPROVE,
            self::COMODINES_CANCEL,
        ], true)) {
            return false;
        }

        return self::allows($userId, $positionId, $permission);
    }

    /** A direct user row, including a revocation, takes precedence over the position grant. */
    public static function effectiveGrantDecision(?array $userGrant, ?array $positionGrant): bool
    {
        $grant = $userGrant ?? $positionGrant;
        return $grant !== null && (int) ($grant['active'] ?? 0) === 1 && (int) ($grant['granted'] ?? 0) === 1;
    }
}
