<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('denies private system execution to regular users', function () {
    $user = User::factory()->create();
    $blueprint = makePrivateSystemBlueprint();

    $this->actingAs($user)
        ->postJson('/api/blueprints/' . $blueprint['id'] . '/execute', [
            'revision_id' => $blueprint['revision_id'],
            'input' => [],
            'context' => [],
        ])
        ->assertForbidden();

    expect(DB::table('executions')->count())->toBe(0);
});

it('allows a specifically authorized administrator to execute a private system Blueprint with a reason', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.execute_private'],
    ])->save();

    $blueprint = makePrivateSystemBlueprint();

    $this->actingAs($admin)
        ->postJson('/api/blueprints/' . $blueprint['id'] . '/execute', [
            'revision_id' => $blueprint['revision_id'],
            'input' => [],
            'context' => [],
            'authorization_reason' => 'Approved internal validation',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed');

    expect(DB::table('authorization_audit_events')
        ->where('actor_user_id', (string) $admin->id)
        ->where('action', 'execute_private_blueprint')
        ->where('resource_id', $blueprint['id'])
        ->where('reason', 'Approved internal validation')
        ->exists())->toBeTrue();
});

function makePrivateSystemBlueprint(): array
{
    $repository = new EloquentBlueprintRepository();
    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'internal-execution-' . bin2hex(random_bytes(4)),
        namespace: 'skillvlt.internal',
        ownership: ['type' => 'system', 'id' => 'skillvlt'],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: ['type' => 'steps', 'version' => 1, 'steps' => [
            ['type' => 'return', 'data' => ['status' => 'ok']],
        ]],
        outputs: ['type' => 'internal-result'],
        policies: ['visibility' => 'private'],
    );

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );
    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );
    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    return [
        'id' => (string) $blueprint->id(),
        'revision_id' => (string) $revision->id(),
    ];
}
