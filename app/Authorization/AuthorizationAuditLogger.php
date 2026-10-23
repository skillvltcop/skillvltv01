<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Execution\Entities\Execution;
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
     * @param array<string, mixed> $context
     */
    public function recordTeacherToolRegistryChange(
        User $actor,
        string $slug,
        string $reason,
        array $context = [],
    ): void {
        $this->write(
            actor: $actor,
            action: 'change_teacher_tool_registry',
            resourceType: 'teacher_tool',
            resourceId: $slug,
            reason: $reason,
            permission: AuthorizationPermissionCatalog::TEACHER_TOOLS_MANAGE_REGISTRY,
            context: $context,
        );
    }

    public function recordExecutionRead(
        User $actor,
        Execution $execution,
        string $reason,
    ): void {
        $this->write(
            actor: $actor,
            action: 'read_execution_support',
            resourceType: 'execution',
            resourceId: (string) $execution->id(),
            reason: $reason,
            permission: AuthorizationPermissionCatalog::EXECUTION_SUPPORT_READ,
            context: [
                'blueprint_id' => (string) $execution->blueprintId(),
                'revision_id' => (string) $execution->revisionId(),
            ],
        );
    }

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
            permission: null,
            context: ['required_role' => 'super_admin'] + $context,
        );
    }

    /**
     * @param array<string, mixed> $context
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
