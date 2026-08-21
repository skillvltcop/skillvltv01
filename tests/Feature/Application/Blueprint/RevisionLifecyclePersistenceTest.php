<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes a new revision on an active blueprint through persistence', function () {
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

    $activate = new ActivateBlueprint($repository);

    $activate->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $blueprint = $repository->find(
        new BlueprintId((string) $blueprint->id())
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

    $blueprint = $repository->find(
        new BlueprintId((string) $blueprint->id())
    );

    expect($blueprint)
        ->not->toBeNull();

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe((string) $firstRevision->id());

    expect((string) $blueprint->latestRevision()->id())
        ->toBe((string) $secondRevision->id());

    $freeze->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $secondRevision->id(),
    );

    $promote->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $secondRevision->id(),
    );

    $reloaded = $repository->find(
        new BlueprintId((string) $blueprint->id())
    );

    expect($reloaded)
        ->not->toBeNull();

    expect($reloaded->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);

    expect($reloaded->currentRevision())
        ->not->toBeNull();

    expect((string) $reloaded->currentRevision()->id())
        ->toBe((string) $secondRevision->id());

    expect((string) $reloaded->currentRevision()->number())
        ->toBe('1.1.0');

    expect($reloaded->currentRevision()->isFrozen())
        ->toBeTrue();

    expect($reloaded->latestRevision())
        ->toBe($reloaded->currentRevision());

    expect($reloaded->revisions())
        ->toHaveCount(2);

    expect((string) $reloaded->revision(
        $firstRevision->id()
    )->number())
        ->toBe('1.0.0');

    expect((string) $reloaded->revision(
        $secondRevision->id()
    )->parentRevisionId())
        ->toBe((string) $firstRevision->id());
});