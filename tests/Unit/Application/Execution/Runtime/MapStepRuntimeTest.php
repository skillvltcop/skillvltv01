<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\Runtime\BehaviorRunner;

it('maps nested steps over each collection item', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'calculate',
                        'operation' => 'divide',
                        'left' => 'item.score',
                        'right' => 'item.max_score',
                        'assign_to' => 'percentage',
                    ],
                    [
                        'type' => 'calculate',
                        'operation' => 'multiply',
                        'left' => 'state.percentage',
                        'right' => 100,
                        'assign_to' => 'percentage',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'percentage' => '{state.percentage}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => [
                    'results' => '{state.results}',
                ],
            ],
        ],
    ];

    $result = $runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'أحمد', 'score' => 14, 'max_score' => 20],
                ['name' => 'سارة', 'score' => '18.0', 'max_score' => '20.0'],
            ],
        ],
    );

    expect($result)->toBe([
        'results' => [
            ['name' => 'أحمد', 'percentage' => 70.0],
            ['name' => 'سارة', 'percentage' => 90.0],
        ],
    ]);
});


it('rejects a zero numeric-string denominator in mapped calculations', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'calculate',
                        'operation' => 'divide',
                        'left' => 'item.score',
                        'right' => 'item.max_score',
                        'assign_to' => 'percentage',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['percentage' => '{state.percentage}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: ['learners' => [['score' => '1.0', 'max_score' => '0.0']]],
    ))->toThrow(DomainException::class, 'Division by zero is not allowed.');
});


it('isolates state between mapped collection items', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'calculate',
                        'operation' => 'multiply',
                        'left' => 'item.score',
                        'right' => 1,
                        'assign_to' => 'score',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'score' => '{state.score}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'first', 'score' => 12],
                ['name' => 'second', 'score' => 3],
            ],
        ],
    ))->toBe([
        'results' => [
            ['name' => 'first', 'score' => 12],
            ['name' => 'second', 'score' => 3],
        ],
    ]);
});


it('rejects non-array items in mapped collections', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: ['learners' => [['name' => 'first'], 'invalid-item']],
    ))->toThrow(DomainException::class, 'Map items must be arrays.');
});


it('rejects a non-array source in map steps', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: ['learners' => 'not-an-array'],
    ))->toThrow(DomainException::class, 'Map source must be an array.');
});


it('returns an empty list when mapping an empty source', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: ['learners' => []],
    ))->toBe(['results' => []]);
});


it('preserves source order when mapping collection items', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'third'],
                ['name' => 'first'],
                ['name' => 'second'],
            ],
        ],
    ))->toBe([
        'results' => [
            ['name' => 'third'],
            ['name' => 'first'],
            ['name' => 'second'],
        ],
    ]);
});


it('maps associative source arrays into an ordered result list', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'learners' => [
                'learner-c' => ['name' => 'third'],
                'learner-a' => ['name' => 'first'],
                'learner-b' => ['name' => 'second'],
            ],
        ],
    ))->toBe([
        'results' => [
            ['name' => 'third'],
            ['name' => 'first'],
            ['name' => 'second'],
        ],
    ]);
});

it('resolves context values inside mapped steps', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'locale' => '{context.locale}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: ['learners' => [['name' => 'first'], ['name' => 'second']]],
        context: ['locale' => 'ar'],
    ))->toBe([
        'results' => [
            ['name' => 'first', 'locale' => 'ar'],
            ['name' => 'second', 'locale' => 'ar'],
        ],
    ]);
});

it('uses context values as operands in mapped calculations', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'calculate',
                        'operation' => 'add',
                        'left' => 'item.score',
                        'right' => 'context.scoring.bonus',
                        'assign_to' => 'adjusted_score',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'score' => '{state.adjusted_score}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'first', 'score' => 10],
                ['name' => 'second', 'score' => 25],
            ],
        ],
        context: ['scoring' => ['bonus' => 3]],
    ))->toBe([
        'results' => [
            ['name' => 'first', 'score' => 13],
            ['name' => 'second', 'score' => 28],
        ],
    ]);
});

it('maps items from a context collection', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'context.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'level' => '{item.level}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [],
        context: [
            'learners' => [
                ['name' => 'first', 'level' => 5],
                ['name' => 'second', 'level' => 6],
            ],
        ],
    ))->toBe([
        'results' => [
            ['name' => 'first', 'level' => 5],
            ['name' => 'second', 'level' => 6],
        ],
    ]);
});



it('maps a collection produced by an earlier step in state', function () {
    $runner = new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'named_learners',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'map',
                'source' => 'state.named_learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => ['name' => '{item.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'first'],
                ['name' => 'second'],
            ],
        ],
    ))->toBe([
        'results' => [
            ['name' => 'first'],
            ['name' => 'second'],
        ],
    ]);
});
