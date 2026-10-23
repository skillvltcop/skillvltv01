<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ListAuthorizationAuditEventsController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 25)));
        $query = DB::table('authorization_audit_events');

        foreach (['action', 'resource_type', 'actor_user_id'] as $filter) {
            $value = $request->query($filter);

            if (is_string($value) && trim($value) !== '') {
                $query->where($filter, $value);
            }
        }

        $events = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $events->getCollection()->map(static fn (object $event): array => [
                'id' => $event->id,
                'actor_user_id' => $event->actor_user_id,
                'action' => $event->action,
                'resource_type' => $event->resource_type,
                'resource_id' => $event->resource_id,
                'permission' => $event->permission,
                'reason' => $event->reason,
                'context' => $event->context !== null
                    ? json_decode($event->context, true)
                    : null,
                'created_at' => $event->created_at,
            ])->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
