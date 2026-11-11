<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Read-only M11 projections for the authenticated operator and real audit rows. */
final class ChecadorM11AdministrationReadService
{
    /**
     * Return only the authenticated active user's effective Checador capabilities
     * for the selected authorized locality. This is not an account directory.
     *
     * @return array{status:string,user?:array<string,mixed>,scope?:array<string,mixed>,items?:list<array<string,mixed>>}
     */
    public function readCurrentUserPermissions(int $workplaceId): array
    {
        $m11Read = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer');
        if (empty($m11Read['allowed'])) {
            return ['status' => 'permission_denied'];
        }

        try {
            $authenticated = \App\Core\Auth::user();
            $user = $authenticated ? Usuario::query()
                ->where('id', (int) $authenticated->id)
                ->where('estatus', 0)
                ->first(['id', 'nombre', 'id_puesto']) : null;
            if (!$user) return ['status' => 'unavailable'];

            $workplace = $m11Read['workplaces'][0] ?? null;
            if (!is_array($workplace) || (int) ($workplace['id'] ?? 0) !== $workplaceId) {
                return ['status' => 'scope_unavailable'];
            }
            $scope = [
                'workplace_id' => $workplaceId,
                'workplace_name' => (string) ($workplace['nombre'] ?? 'Localidad autorizada'),
            ];
            $checks = [
                ['module' => 'Checador', 'action' => 'Consultar módulos', 'scope' => 'Alcance autorizado del usuario', 'check' => static fn(): array => ChecadorAccessService::authorize($workplaceId)],
                ['module' => 'Programación semanal', 'action' => 'Leer', 'scope' => 'Estación seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')],
                ['module' => 'Programación semanal', 'action' => 'Editar', 'scope' => 'Estación seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')],
                ['module' => 'Plantillas globales', 'action' => 'Administrar', 'scope' => 'Catálogo global', 'check' => static fn(): array => ChecadorAccessService::authorizeTemplateAdministration($workplaceId)],
                ['module' => 'Jornadas', 'action' => 'Leer', 'scope' => 'Estación seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeJourneyRead($workplaceId)],
                ['module' => 'Incidencias', 'action' => 'Leer', 'scope' => 'Localidad seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeIncidenceRead($workplaceId)],
                ['module' => 'Incidencias', 'action' => 'Crear', 'scope' => 'Localidad seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeIncidenceCreate($workplaceId)],
                ['module' => 'Rol de comodines', 'action' => 'Leer', 'scope' => 'Estaciones autorizadas', 'check' => static fn(): array => RolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read')],
                ['module' => 'Rol de comodines', 'action' => 'Crear', 'scope' => 'Estaciones autorizadas', 'check' => static fn(): array => RolComodinesService::authorizeCanonicalProgramming($workplaceId, 'create')],
                ['module' => 'Acceso temporal', 'action' => 'Leer', 'scope' => 'Estación seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'leer')],
                ['module' => 'Acceso temporal', 'action' => 'Editar', 'scope' => 'Estación seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'editar')],
                ['module' => 'Retardos e incidencias', 'action' => 'Leer', 'scope' => 'Localidad seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer')],
                ['module' => 'Retardos e incidencias', 'action' => 'Editar', 'scope' => 'Localidad seleccionada', 'check' => static fn(): array => ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar')],
                ['module' => 'Perfil del lector', 'action' => 'Leer', 'scope' => 'Estación física asignada', 'check' => static fn(): array => ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'leer')],
                ['module' => 'Perfil del lector', 'action' => 'Editar', 'scope' => 'Estación física asignada', 'check' => static fn(): array => ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar')],
                ['module' => 'Personal y puestos', 'action' => 'Leer', 'scope' => 'Catálogo institucional', 'check' => static fn(): array => ChecadorAccessService::authorizePositionCatalog($workplaceId, 'leer')],
                ['module' => 'Personal y puestos', 'action' => 'Editar', 'scope' => 'Catálogo institucional', 'check' => static fn(): array => ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar')],
                ['module' => 'Administración de dispositivos', 'action' => 'Leer', 'scope' => 'Estaciones/localidades autorizadas', 'check' => static fn(): array => [
                    'allowed' => ChecadorV2PermissionService::allows(
                        (int) $user->id,
                        (int) $user->id_puesto,
                        ChecadorV2PermissionService::DEVICE_READ
                    ),
                ]],
            ];

            $items = [];
            foreach ($checks as $check) {
                try {
                    $decision = ($check['check'])();
                    $items[] = [
                        'module' => $check['module'],
                        'action' => $check['action'],
                        'scope' => $check['scope'],
                        'allowed' => ($decision['allowed'] ?? false) === true,
                    ];
                } catch (Throwable) {
                    $items[] = [
                        'module' => $check['module'],
                        'action' => $check['action'],
                        'scope' => $check['scope'],
                        'allowed' => false,
                    ];
                }
            }

            return [
                'status' => 'ready',
                'user' => ['id' => (int) $user->id, 'name' => trim((string) $user->nombre), 'position_id' => (int) $user->id_puesto],
                'scope' => $scope,
                'items' => $items,
            ];
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }
    }

    /**
     * Read real M11 audit metadata for one authorized locality. Global position
     * catalog audit rows are included only for Dirección's existing catalog ACL.
     *
     * @return array{status:string,items?:list<array<string,mixed>>}
     */
    public function readChangeAudit(int $workplaceId): array
    {
        $m11Read = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer');
        if (empty($m11Read['allowed'])) return ['status' => 'permission_denied'];
        $canReadGlobal = !empty(ChecadorAccessService::authorizePositionCatalog($workplaceId, 'leer')['allowed']);

        try {
            $query = Capsule::table('op_rh_v2_m11_configuration_audit')
                ->where(function ($scope) use ($workplaceId, $canReadGlobal): void {
                    $scope->where('workplace_id', $workplaceId);
                    if ($canReadGlobal) {
                        $scope->orWhere(function ($global): void {
                            $global->whereNull('workplace_id')->where('entity_type', 'POSITION');
                        });
                    }
                })
                ->orderByDesc('created_at_utc')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'actor_portal_user_id', 'permission_key', 'workplace_id', 'entity_type', 'entity_id', 'action', 'created_at_utc']);

            $items = $query->map(static fn(object $row): array => [
                'id' => (int) $row->id,
                'actor_id' => (int) $row->actor_portal_user_id,
                'permission' => (string) $row->permission_key,
                'workplace_id' => $row->workplace_id === null ? null : (int) $row->workplace_id,
                'entity_type' => (string) $row->entity_type,
                'entity_id' => $row->entity_id === null ? null : (int) $row->entity_id,
                'action' => (string) $row->action,
                'created_at_utc' => (string) $row->created_at_utc,
            ])->all();

            return ['status' => 'ready', 'items' => $items];
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }
    }
}
