<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListUsersAuthorizationController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 25)));
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'permissions'])
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $users->getCollection()->map(static fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->permissions ?? [],
            ])->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
