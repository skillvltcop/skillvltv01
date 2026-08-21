<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\ValueObjects\ExecutionId;
use App\Models\Execution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('persists execution behavioral data as json', function () {
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

    $execution = Execution::query()->create([
        'id' => (string) ExecutionId::generate(),
        'blueprint_id' => (string) $blueprintId,
        'revision_id' => (string) $revisionId,
        'input' => [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        'context' => [
            'locale' => 'ar',
        ],
        'status' => ExecutionStatus::PENDING,
        'output' => null,
        'error' => null,
    ]);

    $this->assertDatabaseHas('executions', [
        'id' => (string) $execution->id,
        'blueprint_id' => (string) $blueprintId,
        'revision_id' => (string) $revisionId,
        'status' => 'pending',
    ]);

    $execution->refresh();

    expect($execution->input)
        ->toBe([
            'student' => [
                'name' => 'Ahmed',
            ],
        ]);

    expect($execution->context)
        ->toBe([
            'locale' => 'ar',
        ]);

    expect($execution->status)
        ->toBe(ExecutionStatus::PENDING);
});