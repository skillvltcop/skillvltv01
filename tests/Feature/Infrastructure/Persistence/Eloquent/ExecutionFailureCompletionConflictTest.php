<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution as DomainExecution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Exceptions\ConcurrentExecutionException;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a stale completion after another writer fails the execution', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'concurrent-fail-complete-test',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = DomainExecution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        input: [],
        context: [],
    );

    $repository = new EloquentExecutionRepository();
    $repository->save($execution);

    $staleWriter = $repository->find($execution->id());
    $currentWriter = $repository->find($execution->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->start();
    $currentWriter->fail('authoritative failure');
    $repository->save($currentWriter);

    $staleWriter->start();
    $staleWriter->complete(['result' => 'stale completion']);

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(
            ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect($persisted->status())
        ->toBe(ExecutionStatus::FAILED);

    expect($persisted->output())->toBeNull();
    expect($persisted->error())->toBe('authoritative failure');
});
