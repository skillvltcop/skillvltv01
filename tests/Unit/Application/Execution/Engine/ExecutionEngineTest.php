<?php

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;
use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Application\Behavior\ValueResolver;

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
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gte',
                        'value' => 10,
                    ],
                    'assign_to' => 'result_status',
                    'true_value' => 'pass',
                    'false_value' => 'fail',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'status' => '{state.result_status}',
                    ],
                ],
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

    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

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
            'score' => 14,
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
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gte',
                        'value' => 10,
                    ],
                    'assign_to' => 'result_status',
                    'true_value' => 'pass',
                    'false_value' => 'fail',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'status' => '{state.result_status}',
                    ],
                ],
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

    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

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
            'score' => 14,
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
            'status' => 'pass',
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

it('fails execution when the revision behavior contract is invalid', function () {
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
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gt',
                        'value' => '10',
                    ],
                    'assign_to' => 'result_status',
                    'true_value' => 'pass',
                    'false_value' => 'fail',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'status' => '{state.result_status}',
                    ],
                ],
            ],
        ],
        outputs: [],
        policies: [],
    );

    $revision->freeze();
    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: new BehaviorRunner(
            new ValueResolver(),
            new BehaviorContractValidator(),
        ),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: ['score' => 14],
        context: [],
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($execution->error())
        ->toContain('Behavior contract validation failed');
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

it('cannot execute an unfrozen revision', function () {
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
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'result' => 'ok',
                    ],
                ],
            ],
        ],
        outputs: [],
        policies: [],
    );

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
        'Only a frozen revision can be executed.'
    );
});

it('keeps the executed revision identity after the blueprint promotes a newer revision', function () {
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
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'version' => '1.0.0',
                    ],
                ],
            ],
        ],
        outputs: [],
        policies: [],
    );

    $revision1->freeze();
    $blueprint->promoteRevision($revision1->id());
    $blueprint->activate();

    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision1->id(),
        input: [],
        context: [],
    );

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'version' => '1.1.0',
                    ],
                ],
            ],
        ],
        outputs: [],
        policies: [],
    );

    $revision2->freeze();
    $blueprint->promoteRevision($revision2->id());

    expect((string) $execution->revisionId())
        ->toBe((string) $revision1->id());

    expect($execution->output())
        ->toBe([
            'version' => '1.0.0',
        ]);
});

it('continues an already-started execution if the blueprint lifecycle changes during behavior execution', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('execution-lifecycle-race'),
        namespace: new BlueprintNamespace('skillvlt.edu.execution'),
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
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'result' => 'completed',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'execution-result',
        ],
        policies: [],
    );

    $revision->freeze();
    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $runner = Mockery::mock(BehaviorRunnerContract::class);

    $runner
        ->shouldReceive('run')
        ->once()
        ->andReturnUsing(function () use ($blueprint): array {
            // Simulate a lifecycle change after the engine has accepted the execution.
            $blueprint->deprecate();

            return [
                'result' => 'completed',
            ];
        });

    $engine = new \\App\\Application\\Execution\\Engine\\ExecutionEngine(
        runner: $runner,
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [],
        context: [],
    );

    expect($blueprint->lifecycleStatus()->value)
        ->toBe('deprecated');

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->toBe([
            'result' => 'completed',
        ]);

    expect((string) $execution->revisionId())
        ->toBe((string) $revision->id());
});

