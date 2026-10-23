<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists user authorization attributes only to a super administrator', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

    $target = User::factory()->create();
    $target->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ])->save();

    $response = $this->actingAs($superAdmin)
        ->getJson('/api/admin/users?per_page=100')
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 2);

    $response->assertJsonFragment([
        'id' => (string) $target->id,
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ]);

    expect($response->json('data.0'))->not->toHaveKey('password');
});

it('lets a super administrator filter and paginate authorization audit events', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

    DB::table('authorization_audit_events')->insert([
        'actor_user_id' => (string) $superAdmin->id,
        'action' => 'change_user_authorization',
        'resource_type' => 'user',
        'resource_id' => 'target-123',
        'permission' => 'authorization.users.manage',
        'reason' => 'Test audit visibility safely',
        'context' => json_encode(['role' => 'admin']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->getJson('/api/admin/audit-events?action=change_user_authorization&per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.resource_id', 'target-123')
        ->assertJsonPath('data.0.context.role', 'admin');
});

it('forbids regular administrators from listing users or audit events', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.read_private'],
    ])->save();

    $this->actingAs($admin)
        ->getJson('/api/admin/users')
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-events')
        ->assertForbidden();
});


it('exposes the canonical permission catalog only to super administrators', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

    $this->actingAs($superAdmin)
        ->getJson('/api/admin/permissions')
        ->assertSuccessful()
        ->assertJsonFragment([
            'key' => 'blueprint.system.create',
            'description' => 'إنشاء Blueprint مملوك للنظام.',
        ])
        ->assertJsonFragment([
            'key' => 'execution.support_read',
            'description' => 'قراءة سجلات التنفيذ لأغراض الدعم مع تسجيل التدقيق.',
        ]);

    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'permissions' => []])->save();

    $this->actingAs($admin)
        ->getJson('/api/admin/permissions')
        ->assertForbidden();
});
