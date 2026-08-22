<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Blueprint\Repositories\BlueprintRepository;

it('executes an activated blueprint through the application layer', function () {
    $repository = Mockery::mock(BlueprintRepository::class);

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
            fn ($id) => (string) $id === (string) $blueprint->id()
        ))
        ->andReturn($blueprint);

   $engine = Mockery::mock(ExecutionEngineContract::class);

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
                    (string) $revisionId === (string) $revision->id()
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

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
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

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn(null);

    $engine = Mockery::mock(ExecutionEngineContract::class);

    $engine
        ->shouldNotReceive('execute');

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
    );

    expect(fn () => $command->handle(
        blueprintId: (string) \App\Domain\Blueprint\ValueObjects\BlueprintId::generate(),
        revisionId: (string) \App\Domain\Blueprint\ValueObjects\RevisionId::generate(),
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
    $engine = Mockery::mock(ExecutionEngineContract::class);

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

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn($blueprint);

    $invalidRevisionId = \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    $engine
        ->shouldReceive('execute')
        ->once()
        ->with(
            $blueprint,
            Mockery::on(
                fn ($revisionId) =>
                    (string) $revisionId === (string) $invalidRevisionId
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
                'Revision does not belong to the Blueprint.'
            )
        );

    $command = new ExecuteBlueprint(
        blueprintRepository: $repository,
        engine: $engine,
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