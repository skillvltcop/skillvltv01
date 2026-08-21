<?php

use App\Application\Execution\Commands\FailExecution;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Repositories\ExecutionRepository;

it('fails a running execution and persists the error', function () {
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

    $execution->start();

    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn($execution);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($execution);

    $command = new FailExecution($repository);

    $result = $command->handle(
        executionId: (string) $execution->id(),
        error: 'Validation failed.',
    );

    expect($result)
        ->toBe($execution);

    expect($execution->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($execution->error())
        ->toBe('Validation failed.');
});

it('cannot fail a non-existing execution', function () {
    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn(null);

    $repository
        ->shouldNotReceive('save');

    $command = new FailExecution($repository);

    expect(fn () => $command->handle(
        executionId: (string) \App\Domain\Execution\ValueObjects\ExecutionId::generate(),
        error: 'Validation failed.',
    ))->toThrow(
        \DomainException::class,
        'Execution not found.'
    );
});