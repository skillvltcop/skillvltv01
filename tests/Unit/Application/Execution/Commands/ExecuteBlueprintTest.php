<?php

use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Repositories\ExecutionRepository;

it('executes an activated blueprint and persists the execution', function () {
    $repository = Mockery::mock(BlueprintRepository::class);
    $executionRepository = Mockery::mock(ExecutionRepository::class);
    $engine = Mockery::mock(ExecutionEngineContract::class);

    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName(
            'assessment-rubric-core',
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment',
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ),
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::on(
            fn ($id) => (string) $id === (string) $blueprint->id(),
        ))
        ->andReturn($blueprint);

    $expectedExecution = Execution::create(
        blueprintId: $blueprint->id(),
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $expectedExecution->start();

    $expectedExecution->complete([
        'steps' => [
            'validate',
            'score',
        ],
    ]);

    $engine
        ->shouldReceive('execute')
        ->once()
        ->with(
            $blueprint,
            Mockery::on(
                fn ($revisionId) =>
                    (string) $revisionId === (string) $revision->id(),
            ),
            [
                'student' => [
                    'name' => 'Ahmed',
                ],
            ],
            [
                'locale' => 'ar',
            ],
        )
        ->andReturn($expectedExecution);

    $executionRepository
        ->shouldReceive('save')
        ->once()
        ->with($expectedExecution);

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
        executionRepository: $executionRepository,
    );

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($result)
        ->toBe($expectedExecution);

    expect($result->status())
        ->toBe(ExecutionStatus::COMPLETED);
});

it('fails when the blueprint does not exist', function () {
    $repository = Mockery::mock(BlueprintRepository::class);
    $executionRepository = Mockery::mock(ExecutionRepository::class);
    $engine = Mockery::mock(ExecutionEngineContract::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn(null);

    $engine
        ->shouldNotReceive('execute');

    $executionRepository
        ->shouldNotReceive('save');

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
        executionRepository: $executionRepository,
    );

    expect(fn () => $command->handle(
        blueprintId: (string) \App\Domain\Blueprint\ValueObjects\BlueprintId::generate(),
        revisionId: (string) RevisionId::generate(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    ))
        ->toThrow(
            DomainException::class,
            'Blueprint not found.',
        );
});

it('delegates an invalid revision to the execution engine', function () {
    $repository = Mockery::mock(BlueprintRepository::class);
    $executionRepository = Mockery::mock(ExecutionRepository::class);
    $engine = Mockery::mock(ExecutionEngineContract::class);

    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName(
            'assessment-rubric-core',
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment',
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn($blueprint);

    $invalidRevisionId = RevisionId::generate();

    $engine
        ->shouldReceive('execute')
        ->once()
        ->with(
            $blueprint,
            Mockery::on(
                fn ($revisionId) =>
                    (string) $revisionId === (string) $invalidRevisionId,
            ),
            [
                'student' => [
                    'name' => 'Ahmed',
                ],
            ],
            [
                'locale' => 'ar',
            ],
        )
        ->andThrow(
            new DomainException(
                'Revision does not belong to the Blueprint.',
            ),
        );

    $executionRepository
        ->shouldNotReceive('save');

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
        executionRepository: $executionRepository,
    );

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $invalidRevisionId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    ))
        ->toThrow(
            DomainException::class,
            'Revision does not belong to the Blueprint.',
        );
});

it('persists a failed execution returned by the execution engine', function () {
    $repository = Mockery::mock(BlueprintRepository::class);
    $executionRepository = Mockery::mock(ExecutionRepository::class);
    $engine = Mockery::mock(ExecutionEngineContract::class);

    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName(
            'assessment-rubric-core',
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment',
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ),
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::on(
            fn ($id) => (string) $id === (string) $blueprint->id(),
        ))
        ->andReturn($blueprint);

    $failedExecution = Execution::create(
        blueprintId: $blueprint->id(),
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $failedExecution->start();

    $failedExecution->fail(
        'Behavior execution failed.',
    );

    $engine
        ->shouldReceive('execute')
        ->once()
        ->andReturn($failedExecution);

    $executionRepository
        ->shouldReceive('save')
        ->once()
        ->with($failedExecution);

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
        executionRepository: $executionRepository,
    );

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($result)
        ->toBe($failedExecution);

    expect($result->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($result->error())
        ->toBe('Behavior execution failed.');
});