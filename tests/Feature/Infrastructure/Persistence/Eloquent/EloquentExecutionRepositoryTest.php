<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ownerId = (string) User::factory()->create()->id;
});

it('persists and retrieves an execution through the repository', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $repository = new EloquentExecutionRepository();

    $repository->save($execution);

    $found = $repository->find(
        $execution->id(),
    );

    expect($found)
        ->not->toBeNull();

    expect((string) $found->id())
        ->toBe((string) $execution->id());

    expect((string) $found->blueprintId())
        ->toBe((string) $blueprintId);

    expect((string) $found->revisionId())
        ->toBe((string) $revisionId);

    expect($found->input())
        ->toBe([
            'student' => [
                'name' => 'Ahmed',
            ],
        ]);

    expect($found->context())
        ->toBe([
            'locale' => 'ar',
        ]);
});

it('returns null when an execution does not exist', function () {
    $repository = new EloquentExecutionRepository();

    $executionId = \App\Domain\Execution\ValueObjects\ExecutionId::generate();

    expect($repository->find($executionId))
        ->toBeNull();
});

it('updates an existing execution without changing its identity', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $repository = new EloquentExecutionRepository();

    $repository->save($execution);

    $execution->start();

    $repository->save($execution);

    $found = $repository->find($execution->id());

    expect($found)
        ->not->toBeNull();

    expect((string) $found->id())
        ->toBe((string) $execution->id());

    expect($found->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::RUNNING);

    expect(\App\Models\Execution::query()->count())
        ->toBe(1);
});

it('rejects a stale execution status rollback', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'concurrent-execution-test',
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
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
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
    $currentWriter->complete(['result' => 'ok']);
    $repository->save($currentWriter);

    $staleWriter->start();

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(
            \App\Domain\Execution\Exceptions\ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect($persisted->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::COMPLETED);
});

it('rejects a stale terminal execution overwrite', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'concurrent-terminal-test',
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
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
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
    $currentWriter->complete(['result' => 'current']);
    $repository->save($currentWriter);

    $staleWriter->start();
    $staleWriter->complete(['result' => 'stale']);

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(
            \App\Domain\Execution\Exceptions\ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect($persisted->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::COMPLETED);

    expect($persisted->output())
        ->toBe([
            'result' => 'current',
        ]);
});

it('rejects a stale successful completion after a concurrent failure', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'concurrent-completed-failed-test',
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
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
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
    $currentWriter->fail('current failure');
    $repository->save($currentWriter);

    $staleWriter->start();
    $staleWriter->complete(['result' => 'stale success']);

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(
            \App\Domain\Execution\Exceptions\ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect($persisted->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::FAILED);

    expect($persisted->output())
        ->toBeNull();

    expect($persisted->error())
        ->toBe('current failure');
});

it('rejects changing execution input and context after persistence', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'execution-input-context-immutability',
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
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
        input: [
            'score' => 10,
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $repository = new EloquentExecutionRepository();
    $repository->save($execution);

    $tampered = Execution::reconstitute(
        id: $execution->id(),
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
        input: [
            'score' => 99,
        ],
        context: [
            'locale' => 'fr',
        ],
        status: \App\Domain\Execution\Enums\ExecutionStatus::PENDING,
        output: null,
        error: null,
    );

    expect(fn () => $repository->save($tampered))
        ->toThrow(
            \App\Domain\Execution\Exceptions\ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect($persisted->input())
        ->toBe([
            'score' => 10,
        ]);

    expect($persisted->context())
        ->toBe([
            'locale' => 'ar',
        ]);
});

it('rejects changing an execution identity after persistence', function () {
    $blueprintAId = BlueprintId::generate();
    $revisionAId = RevisionId::generate();
    $blueprintBId = BlueprintId::generate();
    $revisionBId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintAId,
        'canonical_name' => 'execution-identity-a',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionAId,
        'blueprint_id' => (string) $blueprintAId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    Blueprint::query()->create([
        'id' => (string) $blueprintBId,
        'canonical_name' => 'execution-identity-b',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionBId,
        'blueprint_id' => (string) $blueprintBId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintAId,
        revisionId: $revisionAId,
        ownerId: $this->ownerId,
        input: [],
        context: [],
    );

    $repository = new EloquentExecutionRepository();
    $repository->save($execution);

    $tampered = Execution::reconstitute(
        id: $execution->id(),
        blueprintId: $blueprintBId,
        revisionId: $revisionBId,
        ownerId: $this->ownerId,
        input: [],
        context: [],
        status: \App\Domain\Execution\Enums\ExecutionStatus::PENDING,
        output: null,
        error: null,
    );

    expect(fn () => $repository->save($tampered))
        ->toThrow(
            \App\Domain\Execution\Exceptions\ConcurrentExecutionException::class,
            'The Execution was modified concurrently; reload it before saving.',
        );

    $persisted = $repository->find($execution->id());

    expect((string) $persisted->blueprintId())
        ->toBe((string) $blueprintAId);

    expect((string) $persisted->revisionId())
        ->toBe((string) $revisionAId);
});

it('persists and retrieves a completed execution with its output', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
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

    $execution->complete([
        'score' => 18,
        'steps' => ['validate'],
    ]);

    $repository = new EloquentExecutionRepository();

    $repository->save($execution);

    $found = $repository->find($execution->id());

    expect($found)
        ->not->toBeNull();

    expect($found->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::COMPLETED);

    expect($found->output())
        ->toBe([
            'score' => 18,
            'steps' => ['validate'],
        ]);

    expect($found->error())
        ->toBeNull();
});

it('persists and retrieves a failed execution with its error', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        ownerId: $this->ownerId,
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

    $execution->fail('Behavior execution failed.');

    $repository = new EloquentExecutionRepository();

    $repository->save($execution);

    $found = $repository->find($execution->id());

    expect($found)
        ->not->toBeNull();

    expect($found->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::FAILED);

    expect($found->output())
        ->toBeNull();

    expect($found->error())
        ->toBe('Behavior execution failed.');
});

it('prevents deleting a revision that has executions', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    \App\Models\Execution::query()->create([
        'id' => (string) \App\Domain\Execution\ValueObjects\ExecutionId::generate(),
        'blueprint_id' => (string) $blueprintId,
        'revision_id' => (string) $revisionId,
        'input' => [],
        'context' => [],
        'status' => 'completed',
        'output' => ['result' => 'ok'],
        'error' => null,
    ]);

    expect(fn () => BlueprintRevision::query()
        ->whereKey((string) $revisionId)
        ->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects an execution referencing a revision from another blueprint', function () {
    $blueprintAId = BlueprintId::generate();
    $revisionAId = RevisionId::generate();
    $blueprintBId = BlueprintId::generate();

    Blueprint::query()->create([
        'id' => (string) $blueprintAId,
        'canonical_name' => 'blueprint-a',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    BlueprintRevision::query()->create([
        'id' => (string) $revisionAId,
        'blueprint_id' => (string) $blueprintAId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'frozen' => true,
    ]);

    Blueprint::query()->create([
        'id' => (string) $blueprintBId,
        'canonical_name' => 'blueprint-b',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintBId,
        revisionId: $revisionAId,
        ownerId: $this->ownerId,
        input: [],
        context: [],
    );

    $repository = new EloquentExecutionRepository();

    expect(fn () => $repository->save($execution))
        ->toThrow(
            \DomainException::class,
            'Execution revision does not belong to the Blueprint.',
        );
});