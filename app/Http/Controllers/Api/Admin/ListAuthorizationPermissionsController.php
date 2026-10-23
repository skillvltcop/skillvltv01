<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Authorization\AuthorizationPermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAuthorizationPermissionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = [];

        foreach (AuthorizationPermissionCatalog::definitions() as $key => $description) {
            $data[] = [
                'key' => $key,
                'description' => $description,
            ];
        }

        return response()->json(['data' => $data]);
    }
}
