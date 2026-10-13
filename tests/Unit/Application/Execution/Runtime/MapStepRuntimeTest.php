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
