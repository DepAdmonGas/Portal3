<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/** Append-only M11 audit with no payload, username, password, token or ciphertext fields. */
final class ChecadorM11ConfigurationAuditService
{
    public static function record(
        int $actorUserId,
        string $permission,
        ?int $workplaceId,
        string $entityType,
        ?int $entityId,
        string $action
    ): void {
        if ($actorUserId < 1 || $entityType === '' || $action === '') {
            throw new InvalidArgumentException('Invalid M11 audit metadata.');
        }
        Capsule::table('op_rh_v2_m11_configuration_audit')->insert([
            'actor_portal_user_id' => $actorUserId,
            'permission_key' => $permission,
            'workplace_id' => $workplaceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
