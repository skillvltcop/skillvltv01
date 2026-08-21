<?php

use App\Domain\Blueprint\Entities\Blueprint as DomainBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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
        new \App\Domain\Blueprint\ValueObjects\BlueprintId($id)
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
        new \App\Domain\Blueprint\ValueObjects\BlueprintId($id)
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
        new \App\Domain\Blueprint\ValueObjects\BlueprintId($id)
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
        new \App\Domain\Blueprint\ValueObjects\BlueprintId($id)
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
    ]);

    $model->update([
        'current_revision_id' => $revisionId,
    ]);

    $repository = new EloquentBlueprintRepository();

    $blueprint = $repository->find(
        new \App\Domain\Blueprint\ValueObjects\BlueprintId($id)
    );

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe($revisionId);
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
