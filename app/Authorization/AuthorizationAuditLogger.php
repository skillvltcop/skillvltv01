<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AuthorizationAuditLogger
{
    /**
     * Record an administrative exception without copying sensitive payloads.
     *
     * @param array<string, mixed> $context
     */
    public function record(
        User $actor,
        string $action,
        Blueprint $blueprint,
        string $permission,
        string $reason,
        array $context = [],
    ): void {
        $this->write(
            actor: $actor,
            action: $action,
            resourceType: 'blueprint',
            resourceId: (string) $blueprint->id(),
            reason: $reason,
            permission: $permission,
            context: $context,
        );
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function recordUserAuthorizationChange(
        User $actor,
        User $target,
        string $reason,
        array $context = [],
    ): void {
        $this->write(
            actor: $actor,
            action: 'change_user_authorization',
            resourceType: 'user',
            resourceId: (string) $target->id,
            reason: $reason,
            permission: 'authorization.users.manage',
            context: $context,
        );
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function write(
        User $actor,
        string $action,
        string $resourceType,
        string $resourceId,
        string $reason,
        ?string $permission,
        array $context,
    ): void {
        DB::table('authorization_audit_events')->insert([
            'actor_user_id' => (string) $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'permission' => $permission,
            'reason' => trim($reason),
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
