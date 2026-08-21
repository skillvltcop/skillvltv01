<?php

use App\Application\Execution\Commands\StartExecution;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;

it('starts a pending execution and persists it', function () {
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

    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::on(
            fn (ExecutionId $id) =>
                (string) $id === (string) $execution->id()
        ))
        ->andReturn($execution);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($execution);

    $command = new StartExecution($repository);

    $result = $command->handle(
        executionId: (string) $execution->id(),
    );

    expect($result)
        ->toBe($execution);

    expect($execution->status())
        ->toBe(ExecutionStatus::RUNNING);
});

it('cannot start a non-existing execution', function () {
    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->andReturn(null);

    $repository
        ->shouldNotReceive('save');

    $command = new StartExecution($repository);

    expect(fn () => $command->handle(
        executionId: (string) ExecutionId::generate(),
    ))->toThrow(
        \DomainException::class,
        'Execution not found.'
    );
});