<?php

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;
use App\Application\Execution\Engine\ExecutionEngineContract;

it('executes a frozen blueprint revision and completes an execution', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
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

    $runner = new BehaviorRunner();

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
            'answers' => [
                1 => 'A',
                2 => 'B',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->not->toBeNull();
});

it('uses the revision logic to produce the execution output', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
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

    $runner = new BehaviorRunner();

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
            'answers' => [
                1 => 'A',
                2 => 'B',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($execution->output())
        ->toBe([
            'steps' => [
                'validate',
                'score',
            ],
        ]);
});

it('delegates behavior execution to the runner', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
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

    $input = [
        'student' => [
            'name' => 'Ahmed',
        ],
    ];

    $context = [
        'locale' => 'ar',
    ];

    $expectedOutput = [
        'result' => 'executed',
    ];

    $runner = Mockery::mock(BehaviorRunnerContract::class);

    $runner
        ->shouldReceive('run')
        ->once()
        ->with(
            $revision->logic(),
            $input,
            $context,
        )
        ->andReturn($expectedOutput);

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: $input,
        context: $context,
    );

    expect($execution->output())
        ->toBe($expectedOutput);
});

it('fails an execution when the behavior runner throws an exception', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
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

    $input = [
        'student' => [
            'name' => 'Ahmed',
        ],
    ];

    $context = [
        'locale' => 'ar',
    ];

    $runner = Mockery::mock(BehaviorRunnerContract::class);

    $runner
        ->shouldReceive('run')
        ->once()
        ->with(
            $revision->logic(),
            $input,
            $context,
        )
        ->andThrow(
            new \RuntimeException('Behavior execution failed.')
        );

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: $input,
        context: $context,
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($execution->output())
        ->toBeNull();

    expect($execution->error())
        ->toBe('Behavior execution failed.');
});

it('cannot execute a revision that does not belong to the blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revisionId = \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    $runner = Mockery::mock(BehaviorRunnerContract::class);
    $runner->shouldNotReceive('run');

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    expect(fn () => $engine->execute(
        blueprint: $blueprint,
        revisionId: $revisionId,
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Revision does not belong to the Blueprint.'
    );
});

it('cannot execute a frozen revision that is not current', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: ['steps' => ['validate']],
        outputs: [],
        policies: [],
    );

    $revision1->freeze();
    $blueprint->promoteRevision($revision1->id());

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: ['steps' => ['validate', 'score']],
        outputs: [],
        policies: [],
    );

    $revision2->freeze();
    $blueprint->promoteRevision($revision2->id());
    $blueprint->activate();

    $runner = Mockery::mock(BehaviorRunnerContract::class);
    $runner->shouldNotReceive('run');

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    expect(fn () => $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision1->id(),
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Only the current revision can be executed.'
    );
});

it('cannot execute a current frozen revision of an inactive blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: ['steps' => ['validate']],
        outputs: [],
        policies: [],
    );

    $revision->freeze();
    $blueprint->promoteRevision($revision->id());

    $runner = Mockery::mock(BehaviorRunnerContract::class);
    $runner->shouldNotReceive('run');

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    expect(fn () => $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Only an active Blueprint can be executed.'
    );
});