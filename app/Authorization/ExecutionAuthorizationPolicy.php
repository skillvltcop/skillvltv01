<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Execution\Entities\Execution;
use App\Models\User;

final class ExecutionAuthorizationPolicy
{
    public function __construct(
        private AuthorizationAuditLogger $audit,
    ) {
    }

    public function canRead(Execution $execution, User $user, mixed $reason = null): bool
    {
        $ownerId = $execution->ownerId();

        if ($ownerId !== null && $ownerId === (string) $user->id) {
            return true;
        }

        if (
            ! $user->hasPermissionTo(AuthorizationPermissionCatalog::EXECUTION_SUPPORT_READ)
            || ! is_string($reason)
            || trim($reason) === ''
        ) {
            return false;
        }

        $this->audit->recordExecutionRead(
            actor: $user,
            execution: $execution,
            reason: $reason,
        );

        return true;
    }
}
