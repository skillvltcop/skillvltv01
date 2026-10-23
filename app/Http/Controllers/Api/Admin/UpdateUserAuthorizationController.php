<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Authorization\AuthorizationAuditLogger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateUserAuthorizationController
{
    private const ALLOWED_PERMISSIONS = [
        'blueprint.system.create',
        'blueprint.user.read_private',
        'blueprint.user.execute_private',
        'blueprint.system.read_private',
        'blueprint.system.execute_private',
        'blueprint.system.manage_revisions',
        'blueprint.system.manage_lifecycle',
        'teacher_tools.manage_registry',
        'execution.support_read',
    ];

    public function __construct(
        private AuthorizationAuditLogger $audit,
    ) {
    }

    public function __invoke(Request $request, string $userId): JsonResponse
    {
        $actorId = (string) $request->user()->id;

        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:user,admin,super_admin'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', 'in:' . implode(',', self::ALLOWED_PERMISSIONS)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ])->validate();

        $permissions = array_values($validated['permissions']);

        if ($validated['role'] !== User::ROLE_ADMIN && $permissions !== []) {
            throw ValidationException::withMessages([
                'permissions' => ['Only an admin role can have an explicit permission list.'],
            ]);
        }

        return DB::transaction(function () use ($actorId, $userId, $validated, $permissions): JsonResponse {
            // Lock the super-admin set before checking the last-admin invariant.
            $superAdmins = User::query()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $actor = User::query()->lockForUpdate()->find($actorId);

            if ($actor === null || ! $actor->isSuperAdmin()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            $target = User::query()->lockForUpdate()->find($userId);

            if ($target === null) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            if (
                $target->role === User::ROLE_SUPER_ADMIN
                && $validated['role'] !== User::ROLE_SUPER_ADMIN
                && $superAdmins->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'role' => ['The last super administrator cannot be demoted.'],
                ]);
            }

            $before = [
                'role' => $target->role,
                'permissions' => $target->permissions ?? [],
            ];

            $target->forceFill([
                'role' => $validated['role'],
                'permissions' => $permissions,
            ])->save();

            $this->audit->recordUserAuthorizationChange(
                actor: $actor,
                target: $target,
                reason: $validated['reason'],
                context: [
                    'before' => $before,
                    'after' => [
                        'role' => $target->role,
                        'permissions' => $target->permissions ?? [],
                    ],
                ],
            );

            return response()->json([
                'id' => (string) $target->id,
                'role' => $target->role,
                'permissions' => $target->permissions ?? [],
            ]);
        });
    }
}
