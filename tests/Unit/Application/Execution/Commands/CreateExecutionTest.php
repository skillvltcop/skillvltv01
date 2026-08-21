<?php

use App\Application\Execution\Commands\CreateExecution;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;

it('creates an execution and persists it', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $repository = Mockery::mock(ExecutionRepository::class);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with(Mockery::on(function ($execution) use ($blueprintId, $revisionId) {
            return $execution instanceof Execution
                && (string) $execution->blueprintId() === (string) $blueprintId
                && (string) $execution->revisionId() === (string) $revisionId
                && $execution->input() === [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ]
                && $execution->context() === [
                    'locale' => 'ar',
                ];
        }));

    $command = new CreateExecution($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprintId,
        revisionId: (string) $revisionId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($result)
        ->toBeInstanceOf(Execution::class);

    expect((string) $result->blueprintId())
        ->toBe((string) $blueprintId);

    expect((string) $result->revisionId())
        ->toBe((string) $revisionId);
});