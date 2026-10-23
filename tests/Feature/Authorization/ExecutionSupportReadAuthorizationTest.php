<?php

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\Blueprint as BlueprintModel;
use App\Models\BlueprintRevision as BlueprintRevisionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires a reason and audits privileged execution-record reads', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['execution.support_read'],
    ])->save();

    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'support-read-execution',
        'namespace' => 'skillvlt.test',
        'owner_type' => 'user',
        'owner_id' => (string) $owner->id,
        'lifecycle_status' => 'draft',
    ]);

    $revision = BlueprintRevisionModel::query()->create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => (string) $blueprint->id,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('b', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);

    $execution = Execution::create(
        blueprintId: new BlueprintId((string) $blueprint->id),
        revisionId: new RevisionId((string) $revision->id),
        input: ['private' => 'learner data'],
        context: [],
        ownerId: (string) $owner->id,
    );
    $execution->start();
    $execution->complete(['private_report' => 'sensitive']);
    (new EloquentExecutionRepository())->save($execution);

    $this->actingAs($admin)
        ->getJson('/api/executions/' . $execution->id())
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/api/executions/' . $execution->id() . '?authorization_reason=Support case 456')
        ->assertSuccessful()
        ->assertJsonPath('input.private', 'learner data');

    expect(DB::table('authorization_audit_events')
        ->where('actor_user_id', (string) $admin->id)
        ->where('action', 'read_execution_support')
        ->where('resource_id', (string) $execution->id())
        ->where('reason', 'Support case 456')
        ->exists())->toBeTrue();
});
