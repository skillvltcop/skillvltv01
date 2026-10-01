<?php

use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\ValueObjects\ExecutionId;

it('creates an execution with a generated identity', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($execution->id())
        ->not->toBeNull();

    expect($execution->status())
        ->toBe(ExecutionStatus::PENDING);

    expect($execution->output())
        ->toBeNull();

    expect($execution->error())
        ->toBeNull();
});

it('starts in pending status', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::PENDING);
});

it('returns defensive copies of input and context', function () {
    $input = [
        'student' => [
            'name' => 'Ahmed',
        ],
    ];

    $context = [
        'locale' => 'ar',
    ];

    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: $input,
        context: $context,
    );

    $returnedInput = $execution->input();
    $returnedInput['student']['name'] = 'Changed';

    $returnedContext = $execution->context();
    $returnedContext['locale'] = 'fr';

    expect($execution->input()['student']['name'])
        ->toBe('Ahmed');

    expect($execution->context()['locale'])
        ->toBe('ar');
});

it('starts running from pending', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    expect($execution->status())
        ->toBe(ExecutionStatus::RUNNING);
});

it('completes a running execution with an output', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->complete([
        'result' => 'success',
    ]);

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->toBe([
            'result' => 'success',
        ]);

    expect($execution->error())
        ->toBeNull();
});

it('fails a running execution with an error', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->fail('Validation failed.');

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($execution->output())
        ->toBeNull();

    expect($execution->error())
        ->toBe('Validation failed.');
});

it('cannot complete a pending execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    expect(fn () => $execution->complete([
        'result' => 'success',
    ]))->toThrow(
        \DomainException::class,
        'Only a running execution can be completed.'
    );
});

it('cannot fail a pending execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    expect(fn () => $execution->fail(
        'Something went wrong.'
    ))->toThrow(
        \DomainException::class,
        'Only a running execution can fail.'
    );
});

it('cannot start an already completed execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->complete([
        'result' => 'success',
    ]);

    expect(fn () => $execution->start())
        ->toThrow(
            \DomainException::class,
            'Only a pending execution can be started.'
        );
});

it('cannot fail an already completed execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->complete([
        'result' => 'success',
    ]);

    expect(fn () => $execution->fail(
        'Too late.'
    ))->toThrow(
        \DomainException::class,
        'Only a running execution can fail.'
    );
});

it('cannot complete an already failed execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->fail('Validation failed.');

    expect(fn () => $execution->complete([
        'result' => 'success',
    ]))->toThrow(
        \DomainException::class,
        'Only a running execution can be completed.'
    );
});

it('reconstitutes an execution without changing its identity', function () {
    $id = ExecutionId::generate();

    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $execution = Execution::reconstitute(
        id: $id,
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
        status: ExecutionStatus::COMPLETED,
        output: [
            'score' => 18,
        ],
        error: null,
    );

    expect((string) $execution->id())
        ->toBe((string) $id);

    expect((string) $execution->blueprintId())
        ->toBe((string) $blueprintId);

    expect((string) $execution->revisionId())
        ->toBe((string) $revisionId);

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->toBe([
            'score' => 18,
        ]);
});

it('returns a defensive copy of output', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->complete([
        'result' => [
            'score' => 18,
        ],
    ]);

    $output = $execution->output();

    $output['result']['score'] = 5;

    expect($execution->output()['result']['score'])
        ->toBe(18);
});

it('reconstitutes a failed execution with its error', function () {
    $id = ExecutionId::generate();

    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $execution = Execution::reconstitute(
        id: $id,
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
        status: ExecutionStatus::FAILED,
        output: null,
        error: 'Behavior execution failed.',
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($execution->output())
        ->toBeNull();

    expect($execution->error())
        ->toBe('Behavior execution failed.');
});

it('rejects an invalid completed execution state', function () {
    expect(fn () => Execution::reconstitute(
        id: ExecutionId::generate(),
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
        status: ExecutionStatus::COMPLETED,
        output: null,
        error: null,
    ))->toThrow(
        DomainException::class,
        'A completed execution must have output and no error.'
    );
});

it('rejects an invalid failed execution state', function () {
    expect(fn () => Execution::reconstitute(
        id: ExecutionId::generate(),
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
        status: ExecutionStatus::FAILED,
        output: null,
        error: null,
    ))->toThrow(
        DomainException::class,
        'A failed execution must have an error and no output.'
    );
});

it('cannot complete an already completed execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->complete([
        'result' => 'first',
    ]);

    expect(fn () => $execution->complete([
        'result' => 'second',
    ]))->toThrow(
        DomainException::class,
        'Only a running execution can be completed.'
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->toBe([
            'result' => 'first',
        ]);
});

it('cannot start a failed execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $execution->fail('Behavior execution failed.');

    expect(fn () => $execution->start())
        ->toThrow(
            DomainException::class,
            'Only a pending execution can be started.'
        );

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);
});

it('copies output when completing an execution', function () {
    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
    );

    $execution->start();

    $output = [
        'result' => [
            'score' => 18,
        ],
    ];

    $execution->complete($output);

    $output['result']['score'] = 5;

    expect($execution->output())
        ->toBe([
            'result' => [
                'score' => 18,
            ],
        ]);
});

it('copies output when reconstituting a completed execution', function () {
    $output = [
        'result' => [
            'score' => 18,
        ],
    ];

    $execution = Execution::reconstitute(
        id: ExecutionId::generate(),
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
        status: ExecutionStatus::COMPLETED,
        output: $output,
        error: null,
    );

    $output['result']['score'] = 5;

    expect($execution->output())
        ->toBe([
            'result' => [
                'score' => 18,
            ],
        ]);
});

it('copies input and context when creating an execution', function () {
    $input = [
        'student' => [
            'name' => 'Ali',
        ],
    ];

    $context = [
        'school' => [
            'name' => 'School A',
        ],
    ];

    $execution = Execution::create(
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: $input,
        context: $context,
    );

    $input['student']['name'] = 'Omar';
    $context['school']['name'] = 'School B';

    expect($execution->input())
        ->toBe([
            'student' => [
                'name' => 'Ali',
            ],
        ])
        ->and($execution->context())
        ->toBe([
            'school' => [
                'name' => 'School A',
            ],
        ]);
});

it('copies input and context when reconstituting an execution', function () {
    $input = [
        'student' => [
            'name' => 'Ali',
        ],
    ];

    $context = [
        'school' => [
            'name' => 'School A',
        ],
    ];

    $execution = Execution::reconstitute(
        id: ExecutionId::generate(),
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: $input,
        context: $context,
        status: ExecutionStatus::COMPLETED,
        output: [],
        error: null,
    );

    $input['student']['name'] = 'Omar';
    $context['school']['name'] = 'School B';

    expect($execution->input())
        ->toBe([
            'student' => [
                'name' => 'Ali',
            ],
        ])
        ->and($execution->context())
        ->toBe([
            'school' => [
                'name' => 'School A',
            ],
        ]);
});

it('rejects a pending or running execution state with output or error', function () {
    expect(fn () => Execution::reconstitute(
        id: ExecutionId::generate(),
        blueprintId: BlueprintId::generate(),
        revisionId: RevisionId::generate(),
        input: [],
        context: [],
        status: ExecutionStatus::PENDING,
        output: [
            'result' => 'ok',
        ],
        error: null,
    ))->toThrow(
        DomainException::class,
        'A pending or running execution cannot have output or error.'
    );
});