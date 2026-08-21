<?php

declare(strict_types=1);

use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists a blueprint with its lifecycle status', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => $blueprint->id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'lifecycle_status' => 'draft',
    ]);
});

it('casts lifecycle status to the domain enum', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    $blueprint->refresh();

    expect($blueprint->lifecycle_status)
        ->toBe(LifecycleStatus::DRAFT);
});

it('persists a blueprint revision as json-backed behavior', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    $revision = BlueprintRevision::create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_number' => '1.0.0',
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),

        'contracts' => [
            'executable_contract' => [
                'target_engine' => 'assessment',
                'min_engine_version' => '1.0.0',
            ],
        ],

        'logic' => [
            'variables' => [
                'question_count' => 10,
            ],
        ],

        'outputs' => [
            'output_contract' => [
                'asset_type' => 'assessment',
            ],
        ],

        'policies' => [
            'security_policies' => [],
            'ai_policies' => [],
            'resource_policies' => [],
            'execution_policies' => [],
            'recovery_policies' => [],
        ],
    ]);

    $revision->refresh();

    expect($revision->contracts)
        ->toBeArray()
        ->and($revision->logic)
        ->toBeArray()
        ->and($revision->outputs)
        ->toBeArray()
        ->and($revision->policies)
        ->toBeArray();
});

it('loads blueprint revisions through the relationship', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    BlueprintRevision::create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_number' => '1.0.0',
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
    ]);

    $blueprint->load('revisions');

    expect($blueprint->revisions)
        ->toHaveCount(1)
        ->and($blueprint->revisions->first())
        ->toBeInstanceOf(BlueprintRevision::class);
});

it('loads the current revision through the relationship', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    $revision = BlueprintRevision::create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_number' => '1.0.0',
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
    ]);

    $blueprint->update([
        'current_revision_id' => $revision->id,
    ]);

    $blueprint->refresh();
    $blueprint->load('currentRevision');

    expect($blueprint->currentRevision)
        ->toBeInstanceOf(BlueprintRevision::class)
        ->and($blueprint->currentRevision->id)
        ->toBe($revision->id);
});

it('loads parent and child revisions through relationships', function () {
    $blueprint = Blueprint::create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'platform',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => LifecycleStatus::DRAFT,
    ]);

    $first = BlueprintRevision::create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_number' => '1.0.0',
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
    ]);

    $second = BlueprintRevision::create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_number' => '1.1.0',
        'parent_revision_id' => $first->id,
        'behavior_digest' => 'sha256:' . str_repeat('b', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
    ]);

    $second->load('parentRevision');
    $first->load('childRevisions');

    expect($second->parentRevision)
        ->toBeInstanceOf(BlueprintRevision::class)
        ->and($second->parentRevision->id)
        ->toBe($first->id)
        ->and($first->childRevisions)
        ->toHaveCount(1)
        ->and($first->childRevisions->first()->id)
        ->toBe($second->id);
});

it('persists the frozen state of a blueprint revision', function () {
    $blueprint = \App\Models\Blueprint::query()->create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $revision = $blueprint->revisions()->create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'revision_number' => '1.0.0',
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'contracts' => ['input' => ['type' => 'object']],
        'logic' => ['steps' => ['validate']],
        'outputs' => ['type' => 'assessment-result'],
        'policies' => ['visibility' => 'public'],
        'frozen' => true,
    ]);

    $revision->refresh();

    expect($revision->frozen)
        ->toBeTrue();
});