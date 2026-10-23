<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Models\User;

/**
 * Central authorization decisions for Blueprint visibility and management.
 *
 * Authentication is enforced by the route middleware. This class handles
 * authorization after authentication and deliberately keeps domain invariants
 * (revision ownership, frozen/current state and lifecycle transitions) in the
 * domain/application layer.
 */
final class BlueprintAuthorizationPolicy
{
    public function canReadBlueprintDetails(Blueprint $blueprint, User $user): bool
    {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->isSystemOwned($blueprint)
            && $user->hasPermissionTo('blueprint.system.read_private');
    }

    public function canReadRevisionDetails(
        Blueprint $blueprint,
        BlueprintRevision $revision,
        User $user,
    ): bool {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->isSystemOwned($blueprint)
            && $user->hasPermissionTo('blueprint.system.read_private');
    }

    public function canReadPublicMetadata(Blueprint $blueprint, User $user): bool
    {
        return $this->isOwner($blueprint, $user)
            || $this->isPubliclyAvailable($blueprint)
            || (
                $this->isSystemOwned($blueprint)
                && $user->hasPermissionTo('blueprint.system.read_private')
            );
    }

    public function canExecute(Blueprint $blueprint, User $user): bool
    {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->isPubliclyAvailable($blueprint)
            || (
                $this->isSystemOwned($blueprint)
                && $user->hasPermissionTo('blueprint.system.execute_private')
            );
    }

    public function canManage(Blueprint $blueprint, User $user, string $permission): bool
    {
        if ($this->isOwner($blueprint, $user)) {
            return true;
        }

        return $this->isSystemOwned($blueprint)
            && $user->hasPermissionTo($permission);
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
}
