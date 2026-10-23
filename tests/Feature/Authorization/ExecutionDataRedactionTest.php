<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint as BlueprintModel;
use App\Models\BlueprintRevision as BlueprintRevisionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('redacts execution payloads and retains an ownerless audit shell when deleting a user', function () {
    $user = User::factory()->create();

    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'user-data-redaction',
        'namespace' => 'skillvlt.test',
        'owner_type' => 'user',
        'owner_id' => (string) $user->id,
        'lifecycle_status' => 'draft',
    ]);

    $revision = BlueprintRevisionModel::query()->create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => (string) $blueprint->id,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);

    $execution = Execution::create(
        blueprintId: new BlueprintId((string) $blueprint->id),
        revisionId: new RevisionId((string) $revision->id),
        input: ['student_name' => 'Sensitive learner name'],
        context: ['school' => 'Sensitive school name'],
        ownerId: (string) $user->id,
    );
    $execution->start();
    $execution->complete(['report' => 'Sensitive report']);
    (new EloquentExecutionRepository())->save($execution);

    $user->delete();

    $stored = DB::table('executions')->where('id', (string) $execution->id())->first();

    expect($stored)->not->toBeNull()
        ->and($stored->owner_id)->toBeNull()
        ->and(json_decode($stored->input, true))->toBe([])
        ->and(json_decode($stored->context, true))->toBe([])
        ->and($stored->output)->toBeNull()
        ->and($stored->error)->toBeNull();
});
