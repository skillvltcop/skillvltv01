<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promotes and persists a blueprint revision through the application layer', function () {
    $repository = new EloquentBlueprintRepository();

    $create = new CreateBlueprint($repository);

    $blueprint = $create->handle(
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

    $result = $promote->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    expect($result->isFrozen())
        ->toBeTrue();

    $reloaded = $repository->find(
        new \App\Domain\Blueprint\ValueObjects\BlueprintId(
            (string) $blueprint->id()
        )
    );

    expect($reloaded)
        ->not->toBeNull();

    expect($reloaded->currentRevision())
        ->not->toBeNull();

    expect((string) $reloaded->currentRevision()->id())
        ->toBe((string) $revision->id());

    expect((string) $reloaded->latestRevision()->id())
        ->toBe((string) $revision->id());
});