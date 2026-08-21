<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

it('activates a blueprint with at least one revision and persists it', function () {
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

    $revision = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
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

    $revision->freeze();

    $blueprint->promoteRevision(
        $revision->id()
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

    $command = new ActivateBlueprint($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($result)
        ->toBe($blueprint);

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);
});

it('cannot activate a blueprint without a revision', function () {
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
        ->shouldNotReceive('save');

    $command = new ActivateBlueprint($repository);

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
    ))->toThrow(
        \DomainException::class,
        'A Blueprint cannot become active without a Revision.'
    );

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::DRAFT);
});

it('activates a blueprint using the frozen current revision even when a newer revision exists', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
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
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $firstRevision->freeze();

    $blueprint->promoteRevision($firstRevision->id());

    $secondRevision = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        ),
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate', 'score']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe((string) $firstRevision->id());

    expect((string) $blueprint->latestRevision()->id())
        ->toBe((string) $secondRevision->id());

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

    $command = new ActivateBlueprint($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($result)
        ->toBe($blueprint);

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);
});

