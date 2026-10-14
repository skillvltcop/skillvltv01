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


it('rejects malformed item paths in nested calculate operands', function ($operand, $pathValue, $errorKey) {
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
                        'left' => $operand === 'left' ? $pathValue : 'item.score',
                        'right' => $operand === 'right' ? $pathValue : 1,
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
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
    'empty segment in left operand' => ['left', 'item..score', 'steps.0.steps.0.left'],
    'missing segment in right operand' => ['right', 'item.', 'steps.0.steps.0.right'],
]);


it('rejects malformed item paths in nested map rule conditions', function ($field) {
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
                            'field' => $field,
                            'operator' => 'gte',
                            'value' => 10,
                        ],
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => ['passed' => '{state.passed}'],
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
            ->toHaveKey('steps.0.steps.0.condition.field');
    }
})->with([
    'empty item path segment' => 'item..score',
    'missing item path segment' => 'item.',
    'trailing item path separator' => 'item.score.',
]);


it('rejects non-array conditions in nested map rule steps', function ($condition) {
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
                        'condition' => $condition,
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => ['passed' => '{state.passed}'],
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
            ->toHaveKey('steps.0.steps.0.condition');
    }
})->with([
    'null condition' => null,
    'string condition' => 'item.score',
    'integer condition' => 42,
]);


it('rejects invalid field values in nested map rule conditions', function ($field) {
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
                            'field' => $field,
                            'operator' => 'gte',
                            'value' => 10,
                        ],
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => ['passed' => '{state.passed}'],
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
            ->toHaveKey('steps.0.steps.0.condition.field');
    }
})->with([
    'missing field' => [null],
    'integer field' => [42],
    'array field' => [['item', 'score']],
    'unknown root' => ['learner.score'],
]);


it('rejects invalid operators in nested map rule conditions', function ($operator) {
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
                            'field' => 'item.score',
                            'operator' => $operator,
                            'value' => 10,
                        ],
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => ['passed' => '{state.passed}'],
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
            ->toHaveKey('steps.0.steps.0.condition.operator');
    }
})->with([
    'missing operator' => [null],
    'integer operator' => [42],
    'unsupported operator' => ['contains'],
]);


it('rejects invalid comparison values in nested map rule conditions', function ($value, $includeValue) {
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
                            'field' => 'item.score',
                            'operator' => 'gte',
                            'value' => $value,
                        ],
                        'assign_to' => 'passed',
                        'true_value' => true,
                        'false_value' => false,
                    ],
                    [
                        'type' => 'return',
                        'data' => ['passed' => '{state.passed}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    if (! $includeValue) {
        unset($logic['steps'][0]['steps'][0]['condition']['value']);
    }

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.0.condition.value');
    }
})->with([
    'missing comparison value' => [null, false],
    'non-numeric value for numeric operator' => ['ten', true],
]);

it('rejects invalid operations in nested map calculate steps', function ($operation) {
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
                        'operation' => $operation,
                        'left' => 'item.score',
                        'right' => 10,
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
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
            ->toHaveKey('steps.0.steps.0.operation');
    }
})->with([
    'missing operation' => [null],
    'non-string operation' => [42],
    'unsupported operation' => ['modulo'],
]);

it('rejects nested map calculate steps missing an operand', function ($missingOperand) {
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
                        'right' => 10,
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    unset($logic['steps'][0]['steps'][0][$missingOperand]);

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$missingOperand}");
    }
})->with([
    'missing left operand' => 'left',
    'missing right operand' => 'right',
]);

it('rejects map evaluate_rule steps missing true_value or false_value', function ($missingField) {
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
                            'field' => 'item.score',
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
                            'passed' => '{state.passed}',
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

    unset($logic['steps'][0]['steps'][0][$missingField]);

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$missingField}");
    }
})->with([
    'missing true_value' => 'true_value',
    'missing false_value' => 'false_value',
]);


it('rejects invalid operand types in nested map calculate steps', function ($operand, $value) {
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
                        'right' => 10,
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'boolean left operand' => ['left', true],
    'array right operand' => ['right', ['item.score']],
]);


it('rejects non-numeric string operands in nested map calculate steps', function ($operand, $value) {
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
                        'right' => 10,
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'left operand with ordinary text' => ['left', 'not-a-number'],
    'right operand with whitespace' => ['right', '   '],
]);


it('rejects unknown path roots in nested map calculate operands', function ($operand) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = 'learner.score';

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'left operand' => 'left',
    'right operand' => 'right',
]);


it('accepts allowed path roots in nested map calculate operands', function ($operand, $value) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'input path in left operand' => ['left', 'input.bonus'],
    'context path in left operand' => ['left', 'context.bonus'],
    'state path in left operand' => ['left', 'state.bonus'],
    'item path in left operand' => ['left', 'item.bonus'],
    'input path in right operand' => ['right', 'input.multiplier'],
    'context path in right operand' => ['right', 'context.multiplier'],
    'state path in right operand' => ['right', 'state.multiplier'],
    'item path in right operand' => ['right', 'item.multiplier'],
    'underscore in first segment' => ['left', 'input._bonus'],
    'digits in path segment' => ['right', 'item.score2'],
    'leading digit in path segment' => ['left', 'context.2nd_score'],
    'underscores and digits in nested segments' => ['right', 'state.score_2026.total_2'],
]);


it('rejects invalid characters in nested map calculate path segments', function ($operand, $value) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'hyphen in left path segment' => ['left', 'item.score-value'],
    'space in right path segment' => ['right', 'input.bonus amount'],
    'slash in left path segment' => ['left', 'item.score/total'],
    'brackets in right path segment' => ['right', 'input[bonus]'],
    'colon in left path segment' => ['left', 'context.score:total'],
    'backslash in right path segment' => ['right', 'state.bonus\\amount'],
    'at sign in left path segment' => ['left', 'item.score@total'],
    'hash in right path segment' => ['right', 'input.bonus#amount'],
    'comma in left path segment' => ['left', 'context.score,total'],
    'non-ASCII character in right path segment' => ['right', 'state.élément'],
]);


it('rejects incorrectly cased path roots in nested map calculate operands', function ($operand, $value) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'uppercase root in left operand' => ['left', 'Input.score'],
    'uppercase root in right operand' => ['right', 'ITEM.score'],
]);


it('rejects empty segments in nested map calculate paths', function ($operand, $value) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'empty middle segment in left operand' => ['left', 'item..score'],
    'trailing empty segment in right operand' => ['right', 'input.'],
]);


it('rejects leading empty segments in nested map calculate paths', function ($operand, $value) {
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
                        'assign_to' => 'total',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['total' => '{state.total}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    $logic['steps'][0]['steps'][0][$operand] = $value;

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        (new BehaviorContractValidator())->validate($logic);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey("steps.0.steps.0.{$operand}");
    }
})->with([
    'leading empty segment in left operand' => ['left', '.score'],
    'leading empty segment in right operand' => ['right', '.multiplier'],
]);
