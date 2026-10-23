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
     * @param array<string, scalar|null> $context
     */
    public function record(
        User $actor,
        string $action,
        Blueprint $blueprint,
        string $permission,
        string $reason,
        array $context = [],
    ): void {
        DB::table('authorization_audit_events')->insert([
            'actor_user_id' => (string) $actor->id,
            'action' => $action,
            'resource_type' => 'blueprint',
            'resource_id' => (string) $blueprint->id(),
            'permission' => $permission,
            'reason' => trim($reason),
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
