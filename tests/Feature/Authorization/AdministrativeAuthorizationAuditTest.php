<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires and records a reason for privileged system Blueprint detail access', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.read_private'],
    ])->save();

    $blueprint = (new CreateBlueprint(new EloquentBlueprintRepository()))->handle(
        canonicalName: 'admin-private-blueprint',
        namespace: 'skillvlt.internal',
        ownership: ['type' => 'system', 'id' => 'skillvlt'],
        metadata: ['title' => 'Internal'],
    );

    $this->actingAs($admin)
        ->getJson('/api/blueprints/' . $blueprint->id())
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/api/blueprints/' . $blueprint->id() . '?authorization_reason=incident-review-123')
        ->assertSuccessful()
        ->assertJsonPath('metadata.title', 'Internal');

    $event = DB::table('authorization_audit_events')
        ->where('actor_user_id', (string) $admin->id)
        ->where('action', 'read_blueprint_details')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->resource_id)->toBe((string) $blueprint->id())
        ->and($event->permission)->toBe('blueprint.system.read_private')
        ->and($event->reason)->toBe('incident-review-123');
});
