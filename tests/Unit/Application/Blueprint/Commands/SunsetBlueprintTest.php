<?php

use App\Application\Blueprint\Commands\SunsetBlueprint;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;

it('sunsets an active blueprint and persists it', function () {
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
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $revision->freeze();

    $blueprint->promoteRevision(
        $revision->id()
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

    $command = new SunsetBlueprint($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($result)
        ->toBe($blueprint);

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::SUNSET);
});

it('sunsets a deprecated blueprint and persists it', function () {
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
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $revision->freeze();

    $blueprint->promoteRevision(
        $revision->id()
    );

    $blueprint->activate();

    $blueprint->deprecate();

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

    $command = new SunsetBlueprint($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
    );

    expect($result)
        ->toBe($blueprint);

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::SUNSET);
});

it('cannot sunset a draft blueprint', function () {
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

    $command = new SunsetBlueprint($repository);

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
    ))->toThrow(
        \DomainException::class,
        'Invalid Blueprint lifecycle transition: draft → sunset.'
    );

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::DRAFT);
});