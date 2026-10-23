<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Authorization\AuthorizationAuditLogger;
use App\Authorization\AuthorizationPermissionCatalog;
use App\Authorization\TeacherToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class TeacherToolRegistryController
{
    public function __construct(
        private TeacherToolRegistry $registry,
        private AuthorizationAuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo(AuthorizationPermissionCatalog::TEACHER_TOOLS_MANAGE_REGISTRY)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tools = [];

        foreach ($this->registry->administrativeEntries() as $slug => $entry) {
            $tools[] = [
                'slug' => $slug,
                'namespace' => $entry['namespace'],
                'view' => $entry['view'],
                'enabled' => $entry['enabled'],
            ];
        }

        return response()->json(['data' => $tools]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->hasPermissionTo(AuthorizationPermissionCatalog::TEACHER_TOOLS_MANAGE_REGISTRY)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,127}$/', $slug)) {
            return response()->json(['message' => 'Invalid teacher tool slug.'], 422);
        }

        $validated = Validator::make($request->all(), [
            'namespace' => ['required', 'string', 'max:255'],
            'view' => ['required', 'string', 'max:255', 'regex:/^teacher\.tools\.[a-z0-9-]+$/'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ])->validate();

        if ($validated['enabled'] && ! view()->exists($validated['view'])) {
            return response()->json([
                'message' => 'The configured teacher tool view does not exist.',
            ], 422);
        }

        return DB::transaction(function () use ($actor, $slug, $validated): JsonResponse {
            $before = $this->registry->administrativeEntries()[$slug] ?? null;
            $existing = DB::table('teacher_tool_registry')->where('slug', $slug)->first();
            $now = now();

            $values = [
                'namespace' => $validated['namespace'],
                'view' => $validated['view'],
                'is_enabled' => (bool) $validated['enabled'],
                'updated_by' => (string) $actor->id,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $values['created_at'] = $now;
                DB::table('teacher_tool_registry')->insert(['slug' => $slug] + $values);
            } else {
                DB::table('teacher_tool_registry')->where('slug', $slug)->update($values);
            }

            $after = [
                'namespace' => $validated['namespace'],
                'view' => $validated['view'],
                'enabled' => (bool) $validated['enabled'],
            ];

            $this->audit->recordTeacherToolRegistryChange(
                actor: $actor,
                slug: $slug,
                reason: $validated['reason'],
                context: ['before' => $before, 'after' => $after],
            );

            return response()->json([
                'slug' => $slug,
                'namespace' => $validated['namespace'],
                'view' => $validated['view'],
                'enabled' => (bool) $validated['enabled'],
            ]);
        });
    }
}
