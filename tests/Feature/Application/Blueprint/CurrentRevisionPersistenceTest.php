<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the current revision unchanged when a new revision is added', function () {
    $repository = new \App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository();

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

    $firstRevision = $addRevision->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
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
        revisionId: (string) $firstRevision->id(),
    );

    $promote = new PromoteBlueprintRevision($repository);

    $promote->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $firstRevision->id(),
    );

    $secondRevision = $addRevision->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        behaviorDigest:
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate', 'score'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $reloaded = $repository->find(
        new BlueprintId((string) $blueprint->id())
    );

    expect($reloaded)
        ->not->toBeNull();

    expect($reloaded->latestRevision())
        ->not->toBeNull();

    expect((string) $reloaded->latestRevision()->id())
        ->toBe((string) $secondRevision->id());

    expect($reloaded->currentRevision())
        ->not->toBeNull();

    expect((string) $reloaded->currentRevision()->id())
        ->toBe((string) $firstRevision->id());

    expect((string) $reloaded->currentRevision()->number())
        ->toBe('1.0.0');

    expect((string) $reloaded->latestRevision()->number())
        ->toBe('1.1.0');
});