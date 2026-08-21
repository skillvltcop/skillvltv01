<?php

use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;

it('defines the execution repository contract', function () {
    expect(interface_exists(ExecutionRepository::class))
        ->toBeTrue();

    expect(ExecutionRepository::class)
        ->toBeInterface();
});

it('can save and retrieve an execution through the repository contract', function () {
    $repository = Mockery::mock(ExecutionRepository::class);

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

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($execution);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(ExecutionId::class))
        ->andReturn($execution);

    $repository->save($execution);

    $found = $repository->find($execution->id());

    expect($found)
        ->toBe($execution);
});

it('returns null when an execution does not exist', function () {
    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(ExecutionId::class))
        ->andReturnNull();

    expect($repository->find(
        ExecutionId::generate()
    ))->toBeNull();
});