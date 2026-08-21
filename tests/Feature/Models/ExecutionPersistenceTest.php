<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('persists an execution with its lifecycle state', function () {
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

    $this->assertDatabaseHas('executions', [
        'id' => (string) $execution->id(),
        'blueprint_id' => (string) $blueprintId,
        'revision_id' => (string) $revisionId,
        'status' => 'pending',
    ]);
});