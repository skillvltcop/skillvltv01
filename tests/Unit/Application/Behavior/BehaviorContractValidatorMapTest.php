<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

it('accepts a valid map step contract', function () {
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->not->toThrow(InvalidBehaviorContractException::class);
});

it('rejects invalid placeholders in map return data', function () {
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
                            'name' => '{other.name}',
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.0.data.name.placeholder');
    }
});


it('accepts nested item paths in map rule conditions', function () {
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
                        'type' => 'evaluate_rule',
                        'condition' => [
                            'field' => 'item.student.score',
                            'operator' => 'gte',
                            'value' => 10,
                        ],
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.student.name}',
                            'passed' => '{state.passed}',
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->not->toThrow(InvalidBehaviorContractException::class);
});


it('rejects a map return step followed by another nested step', function () {
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
                    [
                        'type' => 'calculate',
                        'operation' => 'add',
                        'left' => 'item.score',
                        'right' => 1,
                        'assign_to' => 'adjusted_score',
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.0.position');
    }
});


it('rejects duplicate return steps inside a map', function () {
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.1.duplicate');
    }
});

it('rejects a map without a nested return step', function () {
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
                        'right' => 1,
                        'assign_to' => 'adjusted_score',
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.sequence');
    }
});

it('rejects unsupported step types inside a map', function () {
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
                        'type' => 'format_template',
                        'template' => '{item.name}',
                        'assign_to' => 'name',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['name' => '{state.name}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.0.type');
    }
});

