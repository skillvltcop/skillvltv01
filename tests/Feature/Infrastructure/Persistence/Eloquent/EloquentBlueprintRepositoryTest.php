<?php

use App\Domain\Blueprint\Entities\Blueprint as DomainBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Models\BlueprintRevision;

uses(RefreshDatabase::class);

it('reconstitutes a blueprint from persistence without changing its identity', function () {
    $id = (string) Str::ulid();

    BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($id)
    );

    expect($blueprint)
        ->toBeInstanceOf(DomainBlueprint::class);

    expect((string) $blueprint->id())
        ->toBe($id);

    expect((string) $blueprint->canonicalName())
        ->toBe('assessment-rubric-core');

    expect((string) $blueprint->namespace())
        ->toBe('skillvlt.edu.assessment');
});

it('reconstitutes blueprint metadata from persistence', function () {
    $id = (string) Str::ulid();

    $model = BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $model->metadata()->create([
        'blueprint_id' => $id,
        'taxonomy' => [
            'domain' => 'education',
            'type' => 'assessment',
        ],
        'documentation' => [
            'summary' => 'Assessment rubric blueprint',
        ],
        'discovery' => [
            'keywords' => ['assessment', 'rubric'],
        ],
        'lifecycle_metadata' => [
            'created_by' => 'skillvlt',
        ],
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($id)
    );

    expect($blueprint->metadata())
        ->toBe([
            'taxonomy' => [
                'domain' => 'education',
                'type' => 'assessment',
            ],
            'documentation' => [
                'summary' => 'Assessment rubric blueprint',
            ],
            'discovery' => [
                'keywords' => ['assessment', 'rubric'],
            ],
            'lifecycle_metadata' => [
                'created_by' => 'skillvlt',
            ],
        ]);
});

it('reconstitutes blueprint revisions from persistence', function () {
    $id = (string) Str::ulid();
    $revisionId = (string) Str::ulid();

    $model = BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $model->revisions()->create([
        'id' => $revisionId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        'contracts' => [
            'input' => ['type' => 'object'],
        ],
        'logic' => [
            'steps' => ['validate', 'score'],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
        'lifecycle_status' => 'draft',
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($id)
    );

    expect($blueprint->revisions())
        ->toHaveCount(1);

    $revision = $blueprint->revision(
        new \App\Domain\Blueprint\ValueObjects\RevisionId($revisionId)
    );

    expect($revision)
        ->not->toBeNull();

    expect((string) $revision->id())
        ->toBe($revisionId);

    expect((string) $revision->number())
        ->toBe('1.0.0');

    expect((string) $revision->behaviorDigest())
        ->toBe('sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

    expect($revision->contracts())
        ->toBe([
            'input' => ['type' => 'object'],
        ]);

    expect($revision->logic())
        ->toBe([
            'steps' => ['validate', 'score'],
        ]);

    expect($revision->outputs())
        ->toBe([
            'type' => 'assessment-result',
        ]);

    expect($revision->policies())
        ->toBe([
            'visibility' => 'public',
        ]);
});

it('reconstitutes parent revision relationships from persistence', function () {
    $id = (string) Str::ulid();
    $parentRevisionId = (string) Str::ulid();
    $childRevisionId = (string) Str::ulid();

    $model = BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $model->revisions()->create([
        'id' => $parentRevisionId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:1111111111111111111111111111111111111111111111111111111111111111',
        'contracts' => ['input' => ['type' => 'object']],
        'logic' => ['steps' => ['validate']],
        'outputs' => ['type' => 'assessment-result'],
        'policies' => ['visibility' => 'public'],
        'lifecycle_status' => 'draft',
    ]);

    $model->revisions()->create([
        'id' => $childRevisionId,
        'revision_number' => '1.1.0',
        'parent_revision_id' => $parentRevisionId,
        'behavior_digest' => 'sha256:2222222222222222222222222222222222222222222222222222222222222222',
        'contracts' => ['input' => ['type' => 'object']],
        'logic' => ['steps' => ['validate', 'score']],
        'outputs' => ['type' => 'assessment-result'],
        'policies' => ['visibility' => 'public'],
        'lifecycle_status' => 'draft',
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($id)
    );

    expect($blueprint->revisions())
        ->toHaveCount(2);

    $child = collect($blueprint->revisions())
        ->first(
            fn ($revision) => (string) $revision->id() === $childRevisionId
        );

    expect($child)
        ->not->toBeNull();

    expect($child->parentRevisionId())
        ->not->toBeNull();

    expect((string) $child->parentRevisionId())
        ->toBe($parentRevisionId);
});

it('reconstitutes the current revision from persistence', function () {
    $id = (string) Str::ulid();
    $revisionId = (string) Str::ulid();

    $model = BlueprintModel::query()->create([
        'id' => $id,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $model->revisions()->create([
        'id' => $revisionId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:3333333333333333333333333333333333333333333333333333333333333333',
        'contracts' => ['input' => ['type' => 'object']],
        'logic' => ['steps' => ['validate']],
        'outputs' => ['type' => 'assessment-result'],
        'policies' => ['visibility' => 'public'],
        'lifecycle_status' => 'draft',
        'frozen' => true,
    ]);

    $model->update([
        'current_revision_id' => $revisionId,
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($id)
    );

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe($revisionId);
});

it('reconstitutes invalid behavior safely for runtime failure handling', function () {
    $blueprintId = (string) Str::ulid();
    $revisionId = (string) Str::ulid();

    BlueprintModel::query()->create([
        'id' => $blueprintId,
        'canonical_name' => 'persisted-invalid-behavior',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'active',
        'current_revision_id' => null,
    ]);

    BlueprintRevision::query()->create([
        'id' => $revisionId,
        'blueprint_id' => $blueprintId,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gt',
                        'value' => '10',
                    ],
                    'assign_to' => 'result_status',
                    'true_value' => 'pass',
                    'false_value' => 'fail',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'status' => '{state.result_status}',
                    ],
                ],
            ],
        ],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    BlueprintModel::query()
        ->whereKey($blueprintId)
        ->update([
            'current_revision_id' => $revisionId,
        ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new BlueprintId($blueprintId),
    );

    expect($blueprint)
        ->not->toBeNull();

    $engine = new \App\Application\Execution\Engine\ExecutionEngine(
        runner: new \App\Application\Execution\Runtime\BehaviorRunner(
            new \App\Application\Behavior\ValueResolver(),
            new \App\Application\Behavior\BehaviorContractValidator(),
        ),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: new \App\Domain\Blueprint\ValueObjects\RevisionId($revisionId),
        input: ['score' => 14],
        context: [],
    );

    expect($execution->status())
        ->toBe(\App\Domain\Execution\Enums\ExecutionStatus::FAILED);

    expect($execution->error())
        ->toContain('Behavior contract validation failed');
});

it('round trips a complete blueprint aggregate through persistence', function () {
    $blueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'education',
                'type' => 'assessment',
            ],
            'documentation' => [
                'summary' => 'Assessment rubric blueprint',
            ],
            'discovery' => [
                'keywords' => ['assessment', 'rubric'],
            ],
            'lifecycle_metadata' => [
                'created_by' => 'skillvlt',
            ],
        ],
    );

    $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        ),
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate', 'score'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $currentRevision = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.1.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        ),
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate', 'score', 'publish'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $currentRevision->freeze();

    $blueprint->promoteRevision(
        $currentRevision->id()
    );

    $repository = new EloquentBlueprintRepository();

    $repository->save($blueprint);

    $reconstituted = $repository->find($blueprint->id());

    expect($reconstituted)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\Blueprint::class
        );

    expect((string) $reconstituted->id())
        ->toBe((string) $blueprint->id());

    expect((string) $reconstituted->canonicalName())
        ->toBe('assessment-rubric-core');

    expect((string) $reconstituted->namespace())
        ->toBe('skillvlt.edu.assessment');

    expect($reconstituted->ownership())
        ->toBe([
            'type' => 'system',
            'id' => 'skillvlt',
        ]);

    expect($reconstituted->metadata())
        ->toBe($blueprint->metadata());

    expect($reconstituted->revisions())
        ->toHaveCount(2);

    expect($reconstituted->currentRevision())
        ->not->toBeNull();

    expect((string) $reconstituted->currentRevision()->id())
        ->toBe((string) $currentRevision->id());

    expect($reconstituted->currentRevision()->isFrozen())
    ->toBeTrue();

    expect((string) $reconstituted->currentRevision()->number())
        ->toBe('1.1.0');

    $parentRevisionId = $currentRevision->parentRevisionId();

    expect($parentRevisionId)
        ->not->toBeNull();

    expect($reconstituted->revision($parentRevisionId))
        ->not->toBeNull();

    expect((string) $reconstituted->currentRevision()->parentRevisionId())
        ->toBe((string) $parentRevisionId);
});

it('preserves the current revision when saving a new draft revision', function () {
    $blueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [],
        policies: [],
    );

    $revision1->freeze();

    $blueprint->promoteRevision($revision1->id());
    $blueprint->activate();

    $repository = new EloquentBlueprintRepository();

    $repository->save($blueprint);

    $revision2 = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.1.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [],
        policies: [],
    );

    expect($revision2->isFrozen())
        ->toBeFalse();

    expect((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision1->id());

    $repository->save($blueprint);

    $reconstituted = $repository->find($blueprint->id());

    expect($reconstituted)
        ->not->toBeNull();

    expect($reconstituted->revisions())
        ->toHaveCount(2);

    expect($reconstituted->currentRevision())
        ->not->toBeNull();

    expect((string) $reconstituted->currentRevision()->id())
        ->toBe((string) $revision1->id());

    expect($reconstituted->currentRevision()->isFrozen())
        ->toBeTrue();

    expect($reconstituted->revision($revision2->id()))
        ->not->toBeNull();

    expect($reconstituted->revision($revision2->id())->isFrozen())
        ->toBeFalse();
});

it('round trips the blueprint lifecycle status', function () {
    $blueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [],
        policies: [],
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $repository = new EloquentBlueprintRepository();

    $repository->save($blueprint);

    $reconstituted = $repository->find($blueprint->id());

    expect($reconstituted)
        ->not->toBeNull();

    expect($reconstituted->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);
});

it('rejects a stale concurrent revision write', function () {
    $blueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'concurrent-revision-test'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.test'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $firstRevision = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $firstWriter = $repository->find($blueprint->id());
    $secondWriter = $repository->find($blueprint->id());

    expect($firstWriter)->not->toBeNull();
    expect($secondWriter)->not->toBeNull();

    $firstWriter->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.1.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $secondWriter->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.1.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('c', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $repository->save($firstWriter);

    expect(fn () => $repository->save($secondWriter))
        ->toThrow(
            \App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException::class
        );

    expect(
        \App\Models\BlueprintRevision::query()
            ->where('blueprint_id', (string) $blueprint->id())
            ->count()
    )->toBe(2);
});

it('rejects a stale current revision rollback', function () {
    $blueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'concurrent-current-revision-test'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.test'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $revision1->freeze();
    $blueprint->promoteRevision($revision1->id());

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $revision2 = $currentWriter->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.1.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $revision2->freeze();
    $currentWriter->promoteRevision($revision2->id());
    $repository->save($currentWriter);

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(
            \App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException::class
        );

    $persisted = $repository->find($blueprint->id());

    expect($persisted->currentRevision())
        ->not->toBeNull()
        ->and((string) $persisted->currentRevision()->id())
        ->toBe((string) $revision2->id());
});

it('rejects a parent revision belonging to another blueprint', function () {
    $firstBlueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-first'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $secondBlueprint = \App\Domain\Blueprint\Entities\Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-second'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $parentRevision = $firstBlueprint->addRevision(
        number: new \App\Domain\Blueprint\ValueObjects\RevisionNumber('1.0.0'),
        behaviorDigest: new \App\Domain\Blueprint\ValueObjects\BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $repository = new EloquentBlueprintRepository();

    $repository->save($firstBlueprint);
    $repository->save($secondBlueprint);

    expect(fn () => \Illuminate\Support\Facades\DB::table('blueprint_revisions')->insert([
        'id' => (string) \App\Domain\Blueprint\ValueObjects\RevisionId::generate(),
        'blueprint_id' => (string) $secondBlueprint->id(),
        'revision_number' => '1.1.0',
        'parent_revision_id' => (string) $parentRevision->id(),
        'behavior_digest' => 'sha256:' . str_repeat('b', 64),
        'contracts' => json_encode([]),
        'logic' => json_encode([]),
        'outputs' => json_encode([]),
        'policies' => json_encode([]),
        'frozen' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects duplicate revision numbers for the same blueprint', function () {
    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'duplicate-revision-number',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $blueprint->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);

    expect(fn () => $blueprint->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('b', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]))->toThrow(
        \Illuminate\Database\QueryException::class
    );
});

it('rejects a current revision belonging to another blueprint', function () {
    $blueprintA = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'blueprint-a',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $revisionA = $blueprintA->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $blueprintB = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'blueprint-b',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    expect(fn () => $blueprintB->update([
        'current_revision_id' => $revisionA->id,
    ]))->toThrow(
        \Illuminate\Database\QueryException::class
    );
});

it('rejects deleting the current revision', function () {
    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'current-revision-protection',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $revision = $blueprint->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    $blueprint->update([
        'current_revision_id' => $revision->id,
    ]);

    expect(fn () => $revision->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('cascades blueprint deletion to its revisions', function () {
    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'cascade-test',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $blueprint->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);

    expect(BlueprintRevision::query()
        ->where('blueprint_id', $blueprint->id)
        ->count())->toBe(1);

    $blueprint->delete();

    expect(BlueprintRevision::query()
        ->where('blueprint_id', $blueprint->id)
        ->count())->toBe(0);
});

it('prevents deleting a blueprint that has executions', function () {
    $blueprint = BlueprintModel::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => 'execution-cascade-test',
        'namespace' => 'skillvlt.edu.test',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $revision = $blueprint->revisions()->create([
        'id' => (string) Str::ulid(),
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => true,
    ]);

    \App\Models\Execution::query()->create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => $blueprint->id,
        'revision_id' => $revision->id,
        'input' => [],
        'context' => [],
    ]);

    expect(\App\Models\Execution::query()
        ->where('blueprint_id', $blueprint->id)
        ->count())->toBe(1);

    expect(fn () => $blueprint->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect(BlueprintModel::query()
        ->where('id', $blueprint->id)
        ->exists())->toBeTrue();

    expect(BlueprintRevision::query()
        ->where('blueprint_id', $blueprint->id)
        ->exists())->toBeTrue();

    expect(\App\Models\Execution::query()
        ->where('blueprint_id', $blueprint->id)
        ->exists())->toBeTrue();
});

