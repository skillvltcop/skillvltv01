<?php

use App\Application\Execution\Commands\CompleteExecution;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Repositories\ExecutionRepository;

it('completes a running execution and persists the output', function () {
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

    $command = new CompleteExecution($repository);

    $result = $command->handle(
        executionId: (string) $execution->id(),
        output: [
            'result' => 'success',
            'score' => 18,
        ],
    );

    expect($result)
        ->toBe($execution);

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect($execution->output())
        ->toBe([
            'result' => 'success',
            'score' => 18,
        ]);
});

it('cannot complete a non-existing execution', function () {
    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn(null);

    $repository
        ->shouldNotReceive('save');

    $command = new CompleteExecution($repository);

    expect(fn () => $command->handle(
        executionId: (string) \App\Domain\Execution\ValueObjects\ExecutionId::generate(),
        output: [
            'result' => 'success',
        ],
    ))->toThrow(
        \DomainException::class,
        'Execution not found.'
    );
});