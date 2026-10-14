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



it('rejects invalid source paths in map steps', function ($source) {
    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => $source,
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.source');
    }
})->with([
    'unknown root' => 'users.learners',
    'missing path segment' => 'input.',
    'empty path segment' => 'input..learners',
    'item path outside nested map context' => 'item.learners',
]);

it('rejects invalid assign_to identifiers in map steps', function ($assignTo) {
    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => $assignTo,
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.assign_to');
    }
})->with([
    'empty identifier' => '',
    'dotted identifier' => 'results.items',
    'identifier starting with a digit' => '1results',
    'identifier containing a hyphen' => 'map-results',
]);


it('rejects non-string source values in map steps', function ($source) {
    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => $source,
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.source');
    }
})->with([
    'null source' => null,
    'integer source' => 42,
    'array source' => ['input', 'learners'],
]);


it('rejects malformed nested steps in map contracts', function ($nestedStep, $errorKey) {
    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    $nestedStep,
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
        expect($exception->getErrors())->toHaveKey($errorKey);
    }
})->with([
    'nested step is not an array' => ['not-a-step', 'steps.0.steps.0.structure'],
    'nested step type is not a string' => [['type' => 123], 'steps.0.steps.0.type'],
]);

it('rejects missing, empty, and non-array nested steps in map contracts', function ($nestedSteps, $includeSteps) {
    $map = [
        'type' => 'map',
        'source' => 'input.learners',
        'assign_to' => 'results',
    ];

    if ($includeSteps) {
        $map['steps'] = $nestedSteps;
    }

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            $map,
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
        expect($exception->getErrors())->toHaveKey('steps.0.steps');
    }
})->with([
    'missing nested steps' => [null, false],
    'null nested steps' => [null, true],
    'string nested steps' => ['not-an-array', true],
    'empty nested steps' => [[], true],
]);

it('rejects map steps missing required source or assign_to fields', function ($missingField, $errorKey) {
    $map = [
        'type' => 'map',
        'source' => 'input.learners',
        'assign_to' => 'results',
        'steps' => [
            [
                'type' => 'return',
                'data' => ['name' => '{item.name}'],
            ],
        ],
    ];

    unset($map[$missingField]);

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            $map,
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
        expect($exception->getErrors())->toHaveKey($errorKey);
    }
})->with([
    'missing source' => ['source', 'steps.0.source'],
    'missing assign_to' => ['assign_to', 'steps.0.assign_to'],
]);


it('rejects malformed item placeholder paths in map return data', function ($placeholder) {
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
                        'data' => ['name' => $placeholder],
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
            ->toHaveKey('steps.0.steps.0.data.name.placeholder');
    }
})->with([
    'missing item path segment' => '{item.}',
    'empty item path segment' => '{item..name}',
    'trailing item path separator' => '{item.name.}',
]);
