<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('prevents regular users from creating system-owned Blueprints', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/blueprints', [
            'canonical_name' => 'forbidden-system-blueprint',
            'namespace' => 'skillvlt.internal',
            'ownership' => ['type' => 'system'],
            'metadata' => [],
        ])
        ->assertForbidden();
});

it('allows an explicitly authorized administrator to create a system Blueprint with an audit reason', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ])->save();

    $response = $this->actingAs($admin)
        ->postJson('/api/blueprints', [
            'canonical_name' => 'authorized-system-blueprint',
            'namespace' => 'skillvlt.internal',
            'ownership' => ['type' => 'system'],
            'authorization_reason' => 'Register a reviewed core Blueprint',
            'metadata' => ['title' => 'Core Blueprint'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('ownership.type', 'system')
        ->assertJsonPath('ownership.id', 'skillvlt');

    expect(DB::table('authorization_audit_events')
        ->where('actor_user_id', (string) $admin->id)
        ->where('action', 'create_system_blueprint')
        ->where('resource_id', $response->json('id'))
        ->where('reason', 'Register a reviewed core Blueprint')
        ->exists())->toBeTrue();
});
