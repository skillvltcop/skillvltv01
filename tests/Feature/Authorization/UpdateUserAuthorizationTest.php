<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows only a super administrator to change a user role with an audit reason', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();
    $target = User::factory()->create();

    $this->actingAs($superAdmin)
        ->patchJson('/api/admin/users/' . $target->id . '/authorization', [
            'role' => 'admin',
            'permissions' => ['blueprint.system.read_private'],
            'reason' => 'Grant access for support duties',
        ])
        ->assertSuccessful()
        ->assertJsonPath('role', 'admin')
        ->assertJsonPath('permissions.0', 'blueprint.system.read_private');

    $target->refresh();

    expect($target->role)->toBe('admin')
        ->and($target->permissions)->toBe(['blueprint.system.read_private'])
        ->and(DB::table('authorization_audit_events')
            ->where('action', 'change_user_authorization')
            ->where('actor_user_id', (string) $superAdmin->id)
            ->where('resource_id', (string) $target->id)
            ->where('reason', 'Grant access for support duties')
            ->exists())->toBeTrue();
});

it('forbids a regular administrator from changing roles', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ])->save();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patchJson('/api/admin/users/' . $target->id . '/authorization', [
            'role' => 'super_admin',
            'permissions' => [],
            'reason' => 'Attempt privilege escalation',
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe('user');
});


it('prevents demoting the last super administrator', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

    $this->actingAs($superAdmin)
        ->patchJson('/api/admin/users/' . $superAdmin->id . '/authorization', [
            'role' => 'admin',
            'permissions' => ['blueprint.system.read_private'],
            'reason' => 'Change admin access safely',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect($superAdmin->fresh()->role)->toBe('super_admin');
});
