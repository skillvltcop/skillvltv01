<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

beforeEach(function () {
    $this->validator = new BehaviorContractValidator();
});

function validBehaviorContract(): array
{
    return [
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
                'type' => 'format_template',
                'template' => 'Student {input.student_name} got {state.result_status}.',
                'assign_to' => 'final_feedback',
            ],
            [
                'type' => 'return',
                'data' => [
                    'status' => '{state.result_status}',
                    'message' => '{state.final_feedback}',
                ],
            ],
        ],
    ];
}

test('it passes a valid behavior contract v1', function () {
    expect(fn () => $this->validator->validate(
        validBehaviorContract()
    ))->not->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects invalid root envelope', function () {
    $contract = validBehaviorContract();

    $contract['type'] = 'invalid';
    $contract['version'] = 2;

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKeys([
                'root.type',
                'root.version',
            ]);
    }
});

test('it rejects empty steps', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects non-array steps', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = 'invalid';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects a step that is not an array', function () {
    $contract = validBehaviorContract();

    $contract['steps'][0] = 'invalid';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.structure');
    }
});

test('it rejects a step without a valid type', function () {
    $contract = validBehaviorContract();

    unset($contract['steps'][0]['type']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.type');
    }
});

test('it rejects an unknown step type', function () {
    $contract = validBehaviorContract();

    $contract['steps'][0]['type'] = 'unknown_step';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.type');
    }
});

test('it rejects a behavior without return step', function () {
    $contract = validBehaviorContract();

    array_pop($contract['steps']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('root.sequence');
    }
});

test('it requires return step to be last', function () {
    $contract = validBehaviorContract();

    $return = array_pop($contract['steps']);
    $contract['steps'][] = $return;
    $contract['steps'][] = [
        'type' => 'format_template',
        'template' => 'Unreachable {input.student_name}',
        'assign_to' => 'extra_message',
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.2.position')
            ->toHaveKey('steps.3.unreachable');
    }
});

test('it rejects duplicate return steps', function () {
    $contract = validBehaviorContract();

    $contract['steps'] = [
        [
            'type' => 'return',
            'data' => ['status' => 'first'],
        ],
        [
            'type' => 'return',
            'data' => ['status' => 'second'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.1.duplicate');
    }
});

test('it accepts all allowed evaluate_rule operators', function ($operator) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['operator'] = $operator;

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'eq',
    'neq',
    'gt',
    'gte',
    'lt',
    'lte',
]);

test('it rejects unsupported evaluate_rule operator', function () {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['operator'] = 'contains';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it accepts numeric strings for strict equality and inequality operators', function ($operator) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['operator'] = $operator;
    $contract['steps'][0]['condition']['value'] = '10';

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'eq',
    'neq',
]);

test('it rejects non-numeric comparison values for numeric operators', function ($operator) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['operator'] = $operator;
    $contract['steps'][0]['condition']['value'] = '10';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
})->with([
    'gt',
    'gte',
    'lt',
    'lte',
]);

test('it accepts valid paths', function ($path) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['field'] = $path;

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'input.score',
    'input.student.name',
    'context.locale',
    'state.result_status',
]);

test('it rejects invalid paths', function ($path) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['field'] = $path;

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
})->with([
    'user.password',
    'blueprint.id',
    'input.',
    'input..score',
    'state.',
    'context..locale',
]);

test('it rejects evaluate_rule without condition array', function () {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition'] = 'invalid';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects evaluate_rule without condition value', function () {
    $contract = validBehaviorContract();

    unset($contract['steps'][0]['condition']['value']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it accepts valid simple identifiers', function ($identifier) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['assign_to'] = $identifier;

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'result',
    'result_status',
    '_result',
    'score1',
]);

test('it rejects invalid assign_to identifiers', function ($identifier) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['assign_to'] = $identifier;

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
})->with([
    'result.status',
    '1result',
    'result-status',
    'state.result',
]);

test('it rejects evaluate_rule without true_value or false_value', function () {
    $contract = validBehaviorContract();

    unset($contract['steps'][0]['true_value']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    $contract = validBehaviorContract();

    unset($contract['steps'][0]['false_value']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it accepts valid format_template placeholders', function ($placeholder) {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] =
        "Value: {$placeholder}";

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    '{input.score}',
    '{input.student.name}',
    '{context.locale}',
    '{state.result_status}',
]);

test('it rejects invalid format_template placeholders', function ($placeholder) {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] =
        "Value: {$placeholder}";

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
})->with([
    '{user.name}',
    '{blueprint.id}',
    '{input..score}',
    '{input.}',
]);

test('it rejects non-string format_template', function () {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] = 123;

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects return without data', function () {
    $contract = validBehaviorContract();

    unset($contract['steps'][2]['data']);

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it accepts nested return data with valid placeholders', function () {
    $contract = validBehaviorContract();

    $contract['steps'][2]['data'] = [
        'student' => '{input.student.name}',
        'result' => [
            'status' => '{state.result_status}',
            'locale' => '{context.locale}',
        ],
        'score' => 14,
        'passed' => true,
        'metadata' => null,
    ];

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects invalid nested return placeholders', function () {
    $contract = validBehaviorContract();

    $contract['steps'][2]['data'] = [
        'result' => [
            'status' => '{blueprint.id}',
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects return data that is not an array', function () {
    $contract = validBehaviorContract();

    $contract['steps'][2]['data'] = 'invalid';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.2.data');
    }
});

test('it rejects placeholders containing whitespace', function () {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] =
        'Value: {input.student name}';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects malformed placeholders with unmatched braces', function () {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] =
        'Value: {input.score';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects empty placeholders', function () {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] =
        'Value: {}';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it rejects duplicate return steps inside a map', function () {
    $contract = validBehaviorContract();

    $contract['steps'] = [
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
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.1.duplicate');
    }
});

test('it rejects steps after a return inside a map', function () {
    $contract = validBehaviorContract();

    $contract['steps'] = [
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
                    'assign_to' => 'score',
                ],
            ],
        ],
        [
            'type' => 'return',
            'data' => ['results' => '{state.results}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.steps.0.position');
    }
});


test('it rejects placeholders wrapped in extra balanced braces', function () {
    $contract = validBehaviorContract();

    $contract['steps'][1]['template'] = 'Value: {{input.score}}';

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
});

test('it accepts valid calculate assign_to identifiers', function ($identifier) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'right' => 2,
            'assign_to' => $identifier,
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->not->toThrow(InvalidBehaviorContractException::class);
})->with([
    'single letter' => 'r',
    'uppercase identifier' => 'Result',
    'underscore prefix' => '_result',
    'trailing underscore' => 'result_',
    'digits after prefix' => 'result123',
    'mixed case and digits' => 'Result_123',
]);

test('it rejects invalid calculate assign_to identifiers', function ($identifier) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'right' => 2,
            'assign_to' => $identifier,
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.assign_to');
    }
})->with([
    'dotted identifier' => 'result.value',
    'leading digit' => '1result',
    'hyphenated identifier' => 'result-value',
    'empty identifier' => '',
]);

test('it rejects non-string calculate assign_to values', function ($assignTo) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'right' => 2,
            'assign_to' => $assignTo,
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.assign_to');
    }
})->with([
    'integer value' => 123,
    'boolean value' => true,
]);

test('it rejects calculate steps without assign_to', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'right' => 2,
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.assign_to');
    }
});

test('it rejects non-string calculate operation', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => true,
            'left' => 1,
            'right' => 2,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operation');
    }
});



test('it rejects null calculate operation', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => null,
            'left' => 1,
            'right' => 2,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operation');
    }
});

test('it rejects object calculate operation', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => (object) ['name' => 'add'],
            'left' => 1,
            'right' => 2,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operation');
    }
});

test('it rejects unsupported calculate operations', function ($operation) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => $operation,
            'left' => 1,
            'right' => 2,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operation');
    }
})->with([
    'unknown operation' => 'modulo',
    'empty operation' => '',
    'wrong case' => 'ADD',
    'whitespace suffix' => 'add ',
    'whitespace prefix' => ' add',
    'embedded whitespace' => 'ad d',
]);

test('it rejects calculate steps without left operand', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'right' => 1,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.left');
    }
});

test('it rejects malformed calculate operand types', function ($operand) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => $operand,
            'right' => 1,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())->toHaveKey('steps.0.left');
    }
})->with([
    'boolean operand' => true,
    'array operand' => [['value' => 1]],
    'null operand' => null,
    'object operand' => [(object) ['value' => 1]],
    'invalid path' => 'invalid.path',
]);

test('it rejects calculate steps without right operand', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.right');
    }
});

test('it rejects malformed right operand types in calculate steps', function ($operand) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'calculate',
            'operation' => 'add',
            'left' => 1,
            'right' => $operand,
            'assign_to' => 'result',
        ],
        [
            'type' => 'return',
            'data' => ['result' => '{state.result}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())->toHaveKey('steps.0.right');
    }
})->with([
    'boolean operand' => false,
    'array operand' => [['value' => 1]],
    'null operand' => null,
    'object operand' => [(object) ['value' => 1]],
    'invalid path' => 'invalid.path',
]);

test('it rejects a non-string count_matching operator', function ($operator) {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'aggregate',
            'source' => 'input.learners',
            'operation' => 'count_matching',
            'field' => 'score',
            'operator' => $operator,
            'value' => 10,
            'assign_to' => 'matching_count',
        ],
        [
            'type' => 'return',
            'data' => ['count' => '{state.matching_count}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operator');
    }
})->with([
    'null' => null,
    'integer' => 42,
    'boolean' => true,
    'object' => (object) ['operator' => 'gte'],
]);

test('it rejects an unsupported count_matching operator', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'aggregate',
            'source' => 'input.learners',
            'operation' => 'count_matching',
            'field' => 'score',
            'operator' => 'contains',
            'value' => 10,
            'assign_to' => 'matching_count',
        ],
        [
            'type' => 'return',
            'data' => ['count' => '{state.matching_count}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())
            ->toHaveKey('steps.0.operator');
    }
});

test('it rejects non-numeric values for numeric count_matching operators', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'aggregate',
            'source' => 'input.learners',
            'operation' => 'count_matching',
            'field' => 'score',
            'operator' => 'gte',
            'value' => '10',
            'assign_to' => 'matching_count',
        ],
        [
            'type' => 'return',
            'data' => ['count' => '{state.matching_count}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())->toHaveKey('steps.0.value');
    }
});

test('it rejects count_matching without a comparison value', function () {
    $contract = validBehaviorContract();
    $contract['steps'] = [
        [
            'type' => 'aggregate',
            'source' => 'input.learners',
            'operation' => 'count_matching',
            'field' => 'score',
            'operator' => 'eq',
            'assign_to' => 'matching_count',
        ],
        [
            'type' => 'return',
            'data' => ['count' => '{state.matching_count}'],
        ],
    ];

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);

    try {
        $this->validator->validate($contract);
    } catch (InvalidBehaviorContractException $exception) {
        expect($exception->getErrors())->toHaveKey('steps.0.value');
    }
});


test('it rejects item paths outside map steps', function ($path) {
    $contract = validBehaviorContract();

    $contract['steps'][0]['condition']['field'] = $path;

    expect(fn () => $this->validator->validate($contract))
        ->toThrow(InvalidBehaviorContractException::class);
})->with([
    'item.name',
    'item.student.score',
]);
