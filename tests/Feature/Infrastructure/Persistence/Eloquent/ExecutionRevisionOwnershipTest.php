<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use App\Models\Execution as ExecutionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects persisting an execution whose revision belongs to another blueprint', function () {
    $blueprintId = BlueprintId::generate();
    $otherBlueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    foreach ([
        [$blueprintId, 'execution-revision-owner'],
        [$otherBlueprintId, 'execution-revision-actual-owner'],
    ] as [$id, $name]) {
        Blueprint::query()->create([
            'id' => (string) $id,
            'canonical_name' => $name,
            'namespace' => 'skillvlt.edu.test',
            'owner_type' => 'system',
            'owner_id' => 'skillvlt',
            'lifecycle_status' => 'draft',
        ]);
    }

    BlueprintRevision::query()->create([
        'id' => (string) $revisionId,
        'blueprint_id' => (string) $otherBlueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $execution = Execution::create(
        blueprintId: $blueprintId,
        revisionId: $revisionId,
        input: [],
        context: [],
    );

    $repository = new EloquentExecutionRepository();

    expect(fn () => $repository->save($execution))
        ->toThrow(
            \\DomainException::class,
            'Execution revision does not belong to the Blueprint.',
        );

    expect(ExecutionModel::query()->count())->toBe(0);
});
