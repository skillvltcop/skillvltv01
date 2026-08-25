<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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