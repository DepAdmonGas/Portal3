<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Institutional administration for the closed Checador V2 capability catalog. */
final class ChecadorPermissionGrantAdminService
{
    private const GRANT_TABLE = 'op_rh_v2_permission_grants';
    private const AUDIT_TABLE = 'op_rh_v2_m11_configuration_audit';
    private const AUDIT_COLUMN = 'permission_change_json';
    private const SOURCE = 'PORTAL3_CHECADOR_CAPABILITY_ADMIN_V1';

    /** @var array<string,array{label:string,description:string,positions:list<int>}> */
    private const CATALOG = [
        'checador-v2-dispositivos.leer' => [
            'label' => 'Consultar dispositivos',
            'description' => 'Consultar el estado de dispositivos dentro del alcance Checador autorizado.',
            'positions' => [6, 13],
        ],
        'checador.dispositivos.vincular' => [
            'label' => 'Vincular dispositivos',
            'description' => 'Generar, renovar o revocar la vinculación dentro de la estación autorizada.',
            'positions' => [6],
        ],
        'checador.dispositivos.biometria.administrar' => [
            'label' => 'Administrar autorización biométrica del dispositivo',
            'description' => 'Administrar el grant biométrico del dispositivo; no autoriza por sí mismo escrituras de huellas.',
            'positions' => [6],
        ],
        'checador.admin_pin.emitir' => [
            'label' => 'Emitir PIN de configuración',
            'description' => 'Autorizar la emisión del PIN administrativo exclusivamente para Encargados.',
            'positions' => [6],
        ],
        'checador.admin_pin.revocar' => [
            'label' => 'Revocar PIN o sesión de configuración',
            'description' => 'Autorizar la revocación del PIN o sesión administrativa exclusivamente para Encargados.',
            'positions' => [6],
        ],
    ];

    /** Effective institutional ACL plus full Checador scope for global grants. */
    public function actorAccess(int $actorId): array
    {
        $denied = ['show_entry' => false, 'can_read' => false, 'can_write' => false, 'reason' => 'permission_denied', 'workplaces' => []];
        try {
            $authenticated = Auth::user();
            if (!Auth::check() || !$authenticated || (int) $authenticated->id !== $actorId) {
                $denied['reason'] = 'unauthenticated';
                return $denied;
            }
            $actor = Usuario::query()->where('id', $actorId)->where('estatus', 0)->first(['id']);
            if ($actor === null) {
                $denied['reason'] = 'inactive_user';
                return $denied;
            }

            $checadorScope = ChecadorAccessService::authorize();
            if (empty($checadorScope['allowed'])) {
                $denied['reason'] = 'checador_access_denied';
                return $denied;
            }

            $rh = ModuloDptoOperativoService::getPermiso($actorId, 'recursos-humanos');
            $hasChecador = false;
            foreach ($rh['submenus'] ?? [] as $submenu) {
                if (($submenu['clave'] ?? '') === ChecadorAccessService::MODULE_KEY) $hasChecador = true;
            }
            if (!$hasChecador || (int) ($rh['leer'] ?? 0) !== 1) {
                $denied['reason'] = 'checador_acl_read_denied';
                return $denied;
            }

            $fullScope = ChecadorAccessService::hasCompleteConfiguredWorkplaceScope($checadorScope);
            if (!$fullScope) {
                return [
                    'show_entry' => true,
                    'can_read' => false,
                    'can_write' => false,
                    'reason' => 'global_scope_required',
                    'workplaces' => $checadorScope['workplaces'] ?? [],
                ];
            }

            return [
                'show_entry' => true,
                'can_read' => true,
                'can_write' => (int) ($rh['editar'] ?? 0) === 1,
                'reason' => (int) ($rh['editar'] ?? 0) === 1 ? 'authorized' : 'read_only',
                'workplaces' => $checadorScope['workplaces'] ?? [],
            ];
        } catch (Throwable) {
            $denied['reason'] = 'authorization_unavailable';
            return $denied;
        }
    }

    /** Active principal directory limited to positions covered by the closed capability catalog. */
    public function targetDirectory(int $actorId): array
    {
        if (empty($this->actorAccess($actorId)['can_read'])) {
            return ['ok' => false, 'positions' => [], 'users' => [], 'error' => 'permission_denied'];
        }
        try {
            $actor = Usuario::query()->where('id', $actorId)->where('estatus', 0)->first(['id', 'id_puesto']);
            if ($actor === null) return ['ok' => false, 'positions' => [], 'users' => [], 'error' => 'permission_denied'];
            $positionIds = array_values(array_unique(array_merge(...array_map(
                static fn(array $definition): array => $definition['positions'],
                array_values(self::CATALOG)
            ))));
            $catalogPositions = Capsule::connection('default')->table('tb_puestos')
                ->where('estatus', 0)->whereIn('id', $positionIds)
                ->orderBy('tipo_puesto')->get(['id', 'tipo_puesto'])
                ->map(static fn($row): array => ['id' => (int) $row->id, 'name' => (string) $row->tipo_puesto])
                ->all();
            $positions = array_values(array_filter(
                $catalogPositions,
                static fn(array $position): bool => $position['id'] !== (int) $actor->id_puesto
            ));
            $positionNames = [];
            foreach ($catalogPositions as $position) $positionNames[$position['id']] = $position['name'];
            $users = Usuario::query()->where('estatus', 0)->where('id', '<>', $actorId)->whereIn('id_puesto', array_keys($positionNames))
                ->orderBy('nombre')->get(['id', 'nombre', 'id_puesto'])
                ->map(static fn($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->nombre,
                    'position_id' => (int) $row->id_puesto,
                    'position_name' => $positionNames[(int) $row->id_puesto] ?? '',
                ])->all();
            return ['ok' => true, 'positions' => $positions, 'users' => $users, 'error' => null];
        } catch (Throwable) {
            return ['ok' => false, 'positions' => [], 'users' => [], 'error' => 'directory_unavailable'];
        }
    }

    /** Recent append-only permission audit entries, optionally for one principal. */
    public function auditHistory(int $actorId, ?string $principalType = null, ?int $principalId = null): array
    {
        if (empty($this->actorAccess($actorId)['can_read'])) {
            return ['status' => 'permission_denied', 'items' => []];
        }
        try {
            $schema = $this->schemaStatus();
            if (!$schema['audit_ready']) return ['status' => 'unavailable', 'items' => []];
            $keys = array_keys(self::CATALOG);
            $rows = Capsule::connection('default')->table(self::AUDIT_TABLE)
                ->where('entity_type', 'CHECADOR_PERMISSION')->whereIn('permission_key', $keys)
                ->orderByDesc('id')->limit(250)
                ->get(['id', 'actor_portal_user_id', 'permission_key', 'action', self::AUDIT_COLUMN, 'created_at_utc']);
            $items = [];
            $actorIds = [];
            foreach ($rows as $row) {
                $change = json_decode((string) $row->{self::AUDIT_COLUMN}, true);
                if (!is_array($change)) continue;
                $type = strtoupper((string) ($change['principal_type'] ?? ''));
                $id = filter_var($change['principal_id'] ?? null, FILTER_VALIDATE_INT);
                if (!in_array($type, ['POSITION', 'USER'], true) || !$id || $id < 1) continue;
                if ($principalType !== null && ($type !== strtoupper($principalType) || $id !== $principalId)) continue;
                $actorIdForRow = (int) $row->actor_portal_user_id;
                $actorIds[$actorIdForRow] = $actorIdForRow;
                $items[] = [
                    'id' => (int) $row->id,
                    'actor_id' => $actorIdForRow,
                    'permission_key' => (string) $row->permission_key,
                    'action' => (string) $row->action,
                    'principal_type' => $type,
                    'principal_id' => $id,
                    'reason' => (string) ($change['reason'] ?? ''),
                    'before' => is_array($change['before'] ?? null) ? $change['before'] : [],
                    'after' => is_array($change['after'] ?? null) ? $change['after'] : [],
                    'created_at_utc' => (string) $row->created_at_utc,
                ];
            }
            $actorNames = $actorIds === [] ? [] : Usuario::query()->whereIn('id', array_values($actorIds))
                ->pluck('nombre', 'id')->all();
            foreach ($items as &$item) $item['actor_name'] = (string) ($actorNames[$item['actor_id']] ?? ('Usuario ' . $item['actor_id']));
            unset($item);
            return ['status' => 'ready', 'items' => $items];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'items' => []];
        }
    }

    /** Read-only projection. Missing audit DDL does not block viewing grants. */
    public function positionPanel(int $actorId, int $positionId): array
    {
        return $this->panel($actorId, 'POSITION', $positionId);
    }

    /** Read-only projection. Missing audit DDL does not block viewing grants. */
    public function userPanel(int $actorId, int $userId): array
    {
        return $this->panel($actorId, 'USER', $userId);
    }

    /**
     * Persist one explicit allow/deny or restore a user's inheritance.
     * Every decision is re-authorized and audited in the same transaction.
     *
     * @return array{ok:bool,code:string}
     */
    public function update(
        int $actorId,
        string $principalType,
        int $principalId,
        string $permissionKey,
        string $decision,
        string $reason,
        string $expectedState
    ): array {
        $principalType = strtoupper(trim($principalType));
        $permissionKey = trim($permissionKey);
        $decision = strtolower(trim($decision));
        $reason = trim($reason);

        if (!in_array($principalType, ['POSITION', 'USER'], true)
            || $principalId < 1
            || !isset(self::CATALOG[$permissionKey])
            || !in_array($decision, ['allow', 'deny', 'inherit'], true)
            || ($principalType === 'POSITION' && $decision === 'inherit')
            || preg_match('/\A[a-f0-9]{64}\z/i', $expectedState) !== 1
            || mb_strlen($reason, 'UTF-8') < 5
            || mb_strlen($reason, 'UTF-8') > 200
            || preg_match('/(?:password|contrase(?:n|ñ)a|secret|secreto)\s*[:=]|\bBearer\s+[A-Za-z0-9._~-]{20,}|\b(?:pin|token)\s*[:=]\s*\S{4,}|\b[0-9]{6}\b|\b[a-f0-9]{32,}\b/i', $reason) === 1) {
            return ['ok' => false, 'code' => 'invalid_input'];
        }

        try {
            $target = $this->resolveTarget($principalType, $principalId);
            if ($target === null) return ['ok' => false, 'code' => 'principal_unavailable'];
            if (!$this->isManager($actorId)) return ['ok' => false, 'code' => 'administrator_permission_denied'];
            if (!$this->targetIsInScope($actorId, $principalType, $principalId, $target['position_id'])) {
                return ['ok' => false, 'code' => 'principal_out_of_scope'];
            }
            if (!in_array($target['position_id'], self::CATALOG[$permissionKey]['positions'], true)) {
                return ['ok' => false, 'code' => 'capability_not_applicable'];
            }
            if ($principalType === 'USER' && $decision === 'inherit' && $target['position_id'] < 1) {
                return ['ok' => false, 'code' => 'principal_unavailable'];
            }

            $schema = $this->schemaStatus();
            if (!$schema['grant_ready']) return ['ok' => false, 'code' => 'grant_schema_unavailable'];
            if (!$schema['audit_ready']) return ['ok' => false, 'code' => 'audit_schema_pending'];

            Capsule::connection('default')->transaction(function () use (
                $actorId,
                $principalType,
                $principalId,
                $permissionKey,
                $decision,
                $reason,
                $expectedState,
                $target
            ): void {
                $this->lockTarget($principalType, $principalId, $target['position_id']);
                if (!$this->isManager($actorId)
                    || !$this->targetIsInScope($actorId, $principalType, $principalId, $target['position_id'])) {
                    throw new \DomainException('administrator_permission_denied');
                }

                $db = Capsule::connection('default');
                $direct = $this->lockedGrant($permissionKey, $principalType, $principalId);
                $positionGrant = $principalType === 'USER'
                    ? $this->lockedGrant($permissionKey, 'POSITION', $target['position_id'])
                    : null;
                $currentToken = self::stateToken($principalType, $direct, $positionGrant);
                if (!hash_equals($currentToken, $expectedState)) {
                    throw new \DomainException('state_stale');
                }

                if ($decision === 'inherit' && $direct === null) {
                    throw new \DomainException('already_inherited');
                }

                $before = self::auditSnapshot($direct);
                $grantId = $direct === null ? null : (int) $direct->id;
                if ($decision === 'inherit') {
                    $db->table(self::GRANT_TABLE)
                        ->where('id', (int) $direct->id)
                        ->where('permission_key', $permissionKey)
                        ->where('principal_type', 'USER')
                        ->where('principal_id', $principalId)
                        ->delete();
                    $after = ['assigned' => false];
                    $action = 'RESTORE_INHERITANCE';
                } elseif ($direct !== null) {
                    $db->table(self::GRANT_TABLE)->where('id', (int) $direct->id)->update([
                        'granted' => $decision === 'allow' ? 1 : 0,
                        'active' => 1,
                        'reason' => $reason,
                        'source_task' => self::SOURCE,
                        'updated_by_user_id' => $actorId,
                        'updated_at_utc' => $this->utcTimestampExpression($db),
                    ]);
                    $grantId = (int) $direct->id;
                    $after = ['assigned' => true, 'active' => 1, 'granted' => $decision === 'allow' ? 1 : 0, 'reason' => $reason];
                    $action = $decision === 'allow' ? 'SET_ALLOW' : 'SET_DENY';
                } else {
                    $grantId = (int) $db->table(self::GRANT_TABLE)->insertGetId([
                        'permission_key' => $permissionKey,
                        'principal_type' => $principalType,
                        'principal_id' => $principalId,
                        'granted' => $decision === 'allow' ? 1 : 0,
                        'active' => 1,
                        'reason' => $reason,
                        'source_task' => self::SOURCE,
                        'updated_by_user_id' => $actorId,
                        'created_at_utc' => $this->utcTimestampExpression($db),
                        'updated_at_utc' => $this->utcTimestampExpression($db),
                    ]);
                    $after = ['assigned' => true, 'active' => 1, 'granted' => $decision === 'allow' ? 1 : 0, 'reason' => $reason];
                    $action = $decision === 'allow' ? 'SET_ALLOW' : 'SET_DENY';
                }

                $change = [
                    'schema_version' => 1,
                    'principal_type' => $principalType,
                    'principal_id' => $principalId,
                    'before' => $before,
                    'after' => $after,
                    'reason' => $reason,
                ];
                $changeJson = json_encode($change, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $auditId = $db->table(self::AUDIT_TABLE)->insertGetId([
                    'actor_portal_user_id' => $actorId,
                    'permission_key' => $permissionKey,
                    'workplace_id' => null,
                    'entity_type' => 'CHECADOR_PERMISSION',
                    'entity_id' => $grantId ?? $principalId,
                    'action' => $action,
                    self::AUDIT_COLUMN => $changeJson,
                    'created_at_utc' => $this->utcTimestampExpression($db),
                ]);

                $auditReadback = $db->table(self::AUDIT_TABLE)->where('id', $auditId)
                    ->value(self::AUDIT_COLUMN);
                if (!is_string($auditReadback) || json_decode($auditReadback, true) !== $change) {
                    throw new \RuntimeException('permission_audit_readback_failed');
                }

                $afterRow = $decision === 'inherit'
                    ? null
                    : $db->table(self::GRANT_TABLE)->where('permission_key', $permissionKey)
                        ->where('principal_type', $principalType)->where('principal_id', $principalId)->first();
                if ($decision !== 'inherit'
                    && ($afterRow === null
                        || (int) $afterRow->active !== 1
                        || (int) $afterRow->granted !== ($decision === 'allow' ? 1 : 0)
                        || (string) $afterRow->reason !== $reason
                        || (int) $afterRow->updated_by_user_id !== $actorId)) {
                    throw new \RuntimeException('permission_readback_failed');
                }
                if ($decision === 'inherit'
                    && $db->table(self::GRANT_TABLE)->where('permission_key', $permissionKey)
                        ->where('principal_type', 'USER')->where('principal_id', $principalId)->exists()) {
                    throw new \RuntimeException('inheritance_readback_failed');
                }
            });

            return ['ok' => true, 'code' => 'saved'];
        } catch (\DomainException $exception) {
            return ['ok' => false, 'code' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'code' => 'permission_write_unavailable'];
        }
    }

    /** @return array{visible:bool,principal_type:string,principal_id:int,position_id:int,items:array,grant_ready:bool,audit_ready:bool,can_write:bool,blocker:?string} */
    private function panel(int $actorId, string $principalType, int $principalId): array
    {
        $base = [
            'visible' => false,
            'principal_type' => $principalType,
            'principal_id' => $principalId,
            'position_id' => 0,
            'items' => [],
            'grant_ready' => false,
            'audit_ready' => false,
            'can_write' => false,
            'history' => ['status' => 'unavailable', 'items' => []],
            'blocker' => null,
        ];
        try {
            $target = $this->resolveTarget($principalType, $principalId);
            $actorAccess = $this->actorAccess($actorId);
            if ($target === null
                || empty($actorAccess['can_read'])
                || !$this->targetIsInScope($actorId, $principalType, $principalId, $target['position_id'])) {
                return $base;
            }
            $schema = $this->schemaStatus();
            $rows = Capsule::connection('default')->table(self::GRANT_TABLE)
                ->whereIn('permission_key', array_keys(self::CATALOG))
                ->where('principal_type', $principalType)
                ->where('principal_id', $principalId)
                ->get()
                ->keyBy('permission_key');
            $positionRows = $principalType === 'USER'
                ? Capsule::connection('default')->table(self::GRANT_TABLE)
                    ->whereIn('permission_key', array_keys(self::CATALOG))
                    ->where('principal_type', 'POSITION')->where('principal_id', $target['position_id'])
                    ->get()->keyBy('permission_key')
                : collect();

            $items = [];
            foreach (self::CATALOG as $key => $definition) {
                $direct = $rows->get($key);
                $post = $positionRows->get($key);
                $effective = $principalType === 'USER' ? ($direct ?? $post) : $direct;
                $items[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'applicable' => in_array($target['position_id'], $definition['positions'], true),
                    'position_state' => self::displayState($principalType === 'USER' ? $post : $direct),
                    'individual_state' => self::displayState($principalType === 'USER' ? $direct : null),
                    'effective_state' => self::displayState($effective),
                    'effective_allowed' => self::isAllowed($effective),
                    'effective_source' => $principalType === 'USER'
                        ? ($direct !== null ? 'Individual' : ($post !== null ? 'Puesto' : 'Sin asignación'))
                        : ($direct !== null ? 'Puesto' : 'Sin asignación'),
                    'direct_exists' => $direct !== null,
                    'expected_state' => self::stateToken($principalType, $direct, $post),
                ];
            }
            $base['visible'] = true;
            $base['position_id'] = $target['position_id'];
            $base['items'] = $items;
            $base['grant_ready'] = $schema['grant_ready'];
            $base['audit_ready'] = $schema['audit_ready'];
            $base['can_write'] = !empty($actorAccess['can_write']) && $schema['grant_ready'] && $schema['audit_ready'];
            $base['history'] = $this->auditHistory($actorId, $principalType, $principalId);
            $base['blocker'] = !$schema['grant_ready']
                ? 'La tabla de capacidades no cumple el preflight requerido.'
                : (!$schema['audit_ready']
                    ? 'Escrituras deshabilitadas: falta la columna de auditoría permission_change_json.'
                    : (empty($actorAccess['can_write'])
                        ? 'Tienes acceso de consulta; la edición requiere permiso efectivo editar de Recursos Humanos y alcance Checador completo.'
                        : null));
            return $base;
        } catch (Throwable) {
            $base['blocker'] = 'No fue posible leer el estado de capacidades.';
            return $base;
        }
    }

    private function isManager(int $actorId): bool
    {
        return !empty($this->actorAccess($actorId)['can_write']);
    }

    /** @return array{position_id:int}|null */
    private function resolveTarget(string $principalType, int $principalId): ?array
    {
        if ($principalType === 'POSITION') {
            $target = Capsule::connection('default')->table('tb_puestos')
                ->where('id', $principalId)->where('estatus', 0)->first(['id']);
            return $target === null ? null : ['position_id' => (int) $target->id];
        }
        if ($principalType === 'USER') {
            $target = Usuario::query()->where('id', $principalId)->where('estatus', 0)->first(['id', 'id_puesto']);
            if ($target === null || !Capsule::connection('default')->table('tb_puestos')
                ->where('id', (int) $target->id_puesto)->where('estatus', 0)->exists()) {
                return null;
            }
            return ['position_id' => (int) $target->id_puesto];
        }
        return null;
    }

    private function targetIsInScope(int $actorId, string $principalType, int $principalId, int $targetPositionId): bool
    {
        $actor = Usuario::query()->where('id', $actorId)->where('estatus', 0)->first(['id', 'id_puesto']);
        if ($actor === null || !in_array($targetPositionId, [6, 13], true)) return false;
        if ($principalType === 'USER') return $principalId !== $actorId;
        // A position grant also affects its administrator; prohibit indirect self-grant.
        return $principalId !== (int) $actor->id_puesto;
    }

    private function lockTarget(string $principalType, int $principalId, int $targetPositionId): void
    {
        $table = $principalType === 'POSITION' ? 'tb_puestos' : 'tb_usuarios';
        $query = Capsule::connection('default')->table($table)->where('id', $principalId);
        if ($principalType === 'POSITION') $query->where('estatus', 0);
        else $query->where('estatus', 0)->where('id_puesto', $targetPositionId);
        if ($query->lockForUpdate()->first(['id']) === null) throw new \DomainException('principal_unavailable');
        if ($principalType === 'USER'
            && Capsule::connection('default')->table('tb_puestos')->where('id', $targetPositionId)
                ->where('estatus', 0)->lockForUpdate()->first(['id']) === null) {
            throw new \DomainException('principal_unavailable');
        }
    }

    private function lockedGrant(string $key, string $type, int $principalId): ?object
    {
        return Capsule::connection('default')->table(self::GRANT_TABLE)
            ->where('permission_key', $key)->where('principal_type', $type)->where('principal_id', $principalId)
            ->lockForUpdate()->first();
    }

    /** @return array{grant_ready:bool,audit_ready:bool} */
    private function schemaStatus(): array
    {
        try {
            $schema = Capsule::connection('default')->getSchemaBuilder();
            $grantRequired = ['id','permission_key','principal_type','principal_id','granted','active','reason','source_task','updated_by_user_id','created_at_utc','updated_at_utc'];
            $auditRequired = ['id','actor_portal_user_id','permission_key','workplace_id','entity_type','entity_id','action','created_at_utc',self::AUDIT_COLUMN];
            $grantColumnsReady = $schema->hasColumns(self::GRANT_TABLE, $grantRequired);
            $auditColumnsReady = $schema->hasColumns(self::AUDIT_TABLE, $auditRequired);
            $uniqueReady = false;
            foreach ($schema->getIndexes(self::GRANT_TABLE) as $index) {
                $columns = array_map('strtolower', (array) ($index['columns'] ?? []));
                sort($columns);
                $requiredUniqueColumns = ['permission_key','principal_id','principal_type'];
                sort($requiredUniqueColumns);
                if (!empty($index['unique']) && $columns === $requiredUniqueColumns) {
                    $uniqueReady = true;
                    break;
                }
            }
            return ['grant_ready' => $grantColumnsReady && $uniqueReady, 'audit_ready' => $auditColumnsReady];
        } catch (Throwable) {
            return ['grant_ready' => false, 'audit_ready' => false];
        }
    }

    private function utcTimestampExpression($connection): \Illuminate\Database\Query\Expression
    {
        // SQLite CURRENT_TIMESTAMP is UTC and keeps isolated regression fixtures
        // representative; production MariaDB always uses UTC_TIMESTAMP().
        return $connection->raw($connection->getDriverName() === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'UTC_TIMESTAMP()');
    }

    private static function stateToken(string $principalType, ?object $direct, ?object $position): string
    {
        return hash('sha256', json_encode([
            'principal_type' => $principalType,
            'direct' => self::auditSnapshot($direct),
            'position' => self::auditSnapshot($position),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function auditSnapshot(?object $row): array
    {
        if ($row === null) return ['assigned' => false];
        return [
            'assigned' => true,
            'id' => (int) $row->id,
            'active' => (int) $row->active,
            'granted' => (int) $row->granted,
            'reason' => (string) $row->reason,
            'source_task' => (string) $row->source_task,
            'updated_by_user_id' => $row->updated_by_user_id === null ? null : (int) $row->updated_by_user_id,
            'updated_at_utc' => (string) $row->updated_at_utc,
        ];
    }

    private static function displayState(?object $row): string
    {
        if ($row === null) return 'Sin asignación';
        if ((int) $row->active !== 1) return 'Denegado (inactivo)';
        return (int) $row->granted === 1 ? 'Permitido' : 'Denegado';
    }

    private static function isAllowed(?object $row): bool
    {
        return $row !== null && (int) $row->active === 1 && (int) $row->granted === 1;
    }
}
