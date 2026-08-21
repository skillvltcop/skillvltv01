<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;

uses(RefreshDatabase::class);

it('activates and persists a blueprint through the application layer', function () {
    $repository = new EloquentBlueprintRepository();

    $createBlueprint = new \App\Application\Blueprint\Commands\CreateBlueprint(
        $repository
    );

    $blueprint = $createBlueprint->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $addRevision = new AddBlueprintRevision($repository);

    $revision = $addRevision->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $freeze = new FreezeBlueprintRevision($repository);

    $freeze->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    $promote = new PromoteBlueprintRevision($repository);

    $promote->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    $activate = new ActivateBlueprint($repository);

    $activated = $activate->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($activated->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'active',
        'current_revision_id' => (string) $revision->id(),
    ]);
});