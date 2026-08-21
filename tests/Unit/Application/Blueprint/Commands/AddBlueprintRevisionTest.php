<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

it('adds a revision to an existing blueprint and persists it', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new AddBlueprintRevision($repository);

    $revision = $command->handle(
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

    expect($revision)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\BlueprintRevision::class
        );

    expect((string) $revision->number())
        ->toBe('1.0.0');

    expect((string) $revision->behaviorDigest())
        ->toBe(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        );

    expect($blueprint->currentRevision())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($revision);
});

it('links a new revision to the previous revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->twice()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->twice()
        ->with($blueprint);

    $command = new AddBlueprintRevision($repository);

    $firstRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        behaviorDigest: 'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate', 'score']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($secondRevision->parentRevisionId())
        ->not->toBeNull();

    expect((string) $secondRevision->parentRevisionId())
        ->toBe((string) $firstRevision->id());

    expect($blueprint->currentRevision())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);
});

it('keeps the current revision unchanged when adding a new revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->twice()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->twice()
        ->with($blueprint);

    $command = new AddBlueprintRevision($repository);

    $firstRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $firstRevision->freeze();

    $blueprint->promoteRevision(
        $firstRevision->id()
    );

    $blueprint->activate();

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        behaviorDigest: 'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate', 'score']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe((string) $firstRevision->id());
});

it('allows adding a new revision to an active blueprint without changing the current revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $firstRevision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        ),
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

    $firstRevision->freeze();

    $blueprint->promoteRevision(
        $firstRevision->id()
    );

    $blueprint->activate();

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new AddBlueprintRevision($repository);

    $secondRevision = $command->handle(
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

    expect($secondRevision)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\BlueprintRevision::class
        );

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);

    expect($blueprint->currentRevision())
        ->toBe($firstRevision);

    expect((string) $blueprint->currentRevision()->number())
        ->toBe('1.0.0');

    expect((string) $blueprint->latestRevision()->number())
        ->toBe('1.1.0');
});