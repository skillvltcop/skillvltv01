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

it('records only the effective authorization fields and does not persist unrelated request data', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();
    $target = User::factory()->create();

    $response = $this->actingAs($superAdmin)
        ->patchJson('/api/admin/users/' . $target->id . '/authorization', [
            'role' => 'admin',
            'permissions' => ['blueprint.system.read_private'],
            'reason' => 'Grant controlled support access',
            'password' => 'must-not-be-captured',
            'input' => ['sensitive' => 'must-not-be-captured'],
        ])
        ->assertSuccessful()
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('input');

    $event = DB::table('authorization_audit_events')
        ->where('action', 'change_user_authorization')
        ->where('actor_user_id', (string) $superAdmin->id)
        ->where('resource_id', (string) $target->id)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->reason)->toBe('Grant controlled support access')
        ->and(json_decode($event->context, true))->toMatchArray([
            'required_role' => 'super_admin',
            'before' => ['role' => 'user', 'permissions' => []],
            'after' => ['role' => 'admin', 'permissions' => ['blueprint.system.read_private']],
        ])
        ->and($event->context)->not->toContain('must-not-be-captured')
        ->and($event->context)->not->toContain('password');
});

it('rejects unsupported permissions and role permission mismatches without changing the target or adding audit records', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();
    $target = User::factory()->create();

    $this->actingAs($superAdmin)
        ->patchJson('/api/admin/users/' . $target->id . '/authorization', [
            'role' => 'admin',
            'permissions' => ['authorization.users.manage'],
            'reason' => 'Attempt unsupported permission',
        ])
        ->assertUnprocessable();

    $this->actingAs($superAdmin)
        ->patchJson('/api/admin/users/' . $target->id . '/authorization', [
            'role' => 'user',
            'permissions' => ['blueprint.system.read_private'],
            'reason' => 'Attempt inconsistent assignment',
        ])
        ->assertUnprocessable();

    expect($target->fresh()->role)->toBe('user')
        ->and(DB::table('authorization_audit_events')
            ->where('action', 'change_user_authorization')
            ->where('resource_id', (string) $target->id)
            ->count())->toBe(0);
});

