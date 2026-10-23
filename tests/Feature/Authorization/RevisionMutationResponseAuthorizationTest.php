<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not disclose private revision details to an administrator who can manage but cannot read revisions', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.manage_revisions'],
    ])->save();

    $repository = new EloquentBlueprintRepository();
    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-blueprint-limited-revision-response',
        namespace: 'skillvlt.test.security',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                ['type' => 'return', 'data' => ['secret' => 'private behavior']],
            ],
        ],
        outputs: ['type' => 'object'],
        policies: ['visibility' => 'private'],
    );

    $reason = 'Manage revision without reading private details';

    $freeze = $this->actingAs($admin)->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/freeze",
        ['authorization_reason' => $reason],
    );

    $freeze->assertSuccessful()
        ->assertJsonStructure(['id', 'blueprint_id', 'number', 'frozen'])
        ->assertJsonMissingPath('logic')
        ->assertJsonMissingPath('contracts')
        ->assertJsonMissingPath('outputs')
        ->assertJsonMissingPath('policies');

    $promote = $this->actingAs($admin)->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/promote",
        ['authorization_reason' => $reason],
    );

    $promote->assertSuccessful()
        ->assertJsonStructure(['id', 'blueprint_id', 'number', 'frozen'])
        ->assertJsonMissingPath('logic')
        ->assertJsonMissingPath('contracts')
        ->assertJsonMissingPath('outputs')
        ->assertJsonMissingPath('policies');
});


it('does not disclose private Blueprint metadata in lifecycle mutation responses without read permission', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.manage_lifecycle'],
    ])->save();

    $repository = new EloquentBlueprintRepository();
    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-blueprint-limited-lifecycle-response',
        namespace: 'skillvlt.test.security',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'title' => 'Internal title',
            'private_note' => 'Internal metadata',
        ],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                ['type' => 'return', 'data' => ['status' => 'ok']],
            ],
        ],
        outputs: ['type' => 'object'],
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

    $reason = 'Change lifecycle without private read access';

    $activate = $this->actingAs($admin)->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
        ['authorization_reason' => $reason],
    );
    $activate->assertSuccessful()
        ->assertJsonPath('lifecycle_status', 'active')
        ->assertJsonMissingPath('metadata')
        ->assertJsonMissingPath('ownership')
        ->assertJsonMissingPath('namespace')
        ->assertJsonMissingPath('current_revision_id');

    $deprecate = $this->actingAs($admin)->postJson(
        "/api/blueprints/{$blueprint->id()}/deprecate",
        ['authorization_reason' => $reason],
    );
    $deprecate->assertSuccessful()
        ->assertJsonPath('lifecycle_status', 'deprecated')
        ->assertJsonMissingPath('metadata')
        ->assertJsonMissingPath('ownership')
        ->assertJsonMissingPath('namespace')
        ->assertJsonMissingPath('current_revision_id');

    $sunset = $this->actingAs($admin)->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
        ['authorization_reason' => $reason],
    );
    $sunset->assertSuccessful()
        ->assertJsonPath('lifecycle_status', 'sunset')
        ->assertJsonMissingPath('metadata')
        ->assertJsonMissingPath('ownership')
        ->assertJsonMissingPath('namespace')
        ->assertJsonMissingPath('current_revision_id');
});
