<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Models\User;

/**
 * Central authorization decisions for Blueprint visibility and management.
 */
final class BlueprintAuthorizationPolicy
{
    public function __construct(
        private AuthorizationAuditLogger $audit,
    ) {
    }

    public function canReadBlueprintDetails(
        Blueprint $blueprint,
        User $user,
        mixed $reason = null,
    ): bool {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->allowAuditedSystemException(
            $blueprint,
            $user,
            'blueprint.system.read_private',
            'read_blueprint_details',
            $reason,
        );
    }

    public function canReadRevisionDetails(
        Blueprint $blueprint,
        BlueprintRevision $revision,
        User $user,
        ?string $reason = null,
    ): bool {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->allowAuditedSystemException(
            $blueprint,
            $user,
            'blueprint.system.read_private',
            'read_blueprint_revision_details',
            $reason,
            ['revision_id' => (string) $revision->id()],
        );
    }

    public function canReadPublicMetadata(Blueprint $blueprint, User $user): bool
    {
        return $this->isOwner($blueprint, $user)
            || $this->isPubliclyAvailable($blueprint);
    }

    public function canExecute(
        Blueprint $blueprint,
        User $user,
        ?string $reason = null,
    ): bool {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        if ($this->isPubliclyAvailable($blueprint)) {
            return true;
        }

        return $this->allowAuditedSystemException(
            $blueprint,
            $user,
            'blueprint.system.execute_private',
            'execute_private_blueprint',
            $reason,
        );
    }

    public function canManage(
        Blueprint $blueprint,
        User $user,
        string $permission,
        ?string $reason = null,
    ): bool {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->allowAuditedSystemException(
            $blueprint,
            $user,
            $permission,
            'manage_system_blueprint',
            $reason,
        );
    }

    public function isPubliclyAvailable(Blueprint $blueprint): bool
    {
        if (
            ! $this->isSystemOwned($blueprint)
            || $blueprint->lifecycleStatus() !== LifecycleStatus::ACTIVE
        ) {
            return false;
        }

        $currentRevisionId = $blueprint->currentRevisionId();

        if ($currentRevisionId === null) {
            return false;
        }

        $revision = $blueprint->revision($currentRevisionId);

        if ($revision === null || ! $revision->isFrozen()) {
            return false;
        }

        return ($revision->policies()['visibility'] ?? null) === 'public';
    }

    public function isOwner(Blueprint $blueprint, User $user): bool
    {
        $ownership = $blueprint->ownership();

        return ($ownership['type'] ?? null) === 'user'
            && (string) ($ownership['id'] ?? '') === (string) $user->id;
    }

    public function isSystemOwned(Blueprint $blueprint): bool
    {
        $ownership = $blueprint->ownership();

        return ($ownership['type'] ?? null) === 'system'
            && (string) ($ownership['id'] ?? '') === 'skillvlt';
    }

    /**
     * Administrative exceptions require an explicit permission and a reason.
     *
     * @param array<string, scalar|null> $context
     */
    private function allowAuditedSystemException(
        Blueprint $blueprint,
        User $user,
        string $permission,
        string $action,
        mixed $reason,
        array $context = [],
    ): bool {
        if (! $this->isSystemOwned($blueprint) || ! $user->hasPermissionTo($permission)) {
            return false;
        }

        if (! is_string($reason) || trim($reason) === '') {
            return false;
        }

        $this->audit->record(
            actor: $user,
            action: $action,
            blueprint: $blueprint,
            permission: $permission,
            reason: $reason,
            context: $context,
        );

        return true;
    }
}
