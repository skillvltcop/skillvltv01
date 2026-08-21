<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\SunsetBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;

uses(RefreshDatabase::class);

it('sunsets and persists a blueprint through the application layer', function () {
    $repository = new EloquentBlueprintRepository();

    $createBlueprint = new CreateBlueprint($repository);

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

    $activate->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $sunset = new SunsetBlueprint($repository);

    $sunsetBlueprint = $sunset->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($sunsetBlueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::SUNSET);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'sunset',
    ]);
});