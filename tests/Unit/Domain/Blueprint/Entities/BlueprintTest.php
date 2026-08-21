<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Blueprint\Enums\LifecycleStatus;

function makeBlueprint(): Blueprint
{
    return Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'owner_id' => 'platform',
            'owner_type' => 'platform',
            'author' => 'SkillVLT',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'education',
                'category' => 'assessment',
            ],
            'documentation' => [
                'title' => 'Assessment Rubric',
                'summary' => 'Core assessment rubric blueprint.',
            ],
        ],
    );
}

function makeDigest(): BehaviorDigest
{
    return new BehaviorDigest(
        'sha256:' . str_repeat('a', 64)
    );
}

function makeContracts(): array
{
    return [
        'executable_contract' => [
            'target_engine' => 'assessment',
            'min_engine_version' => '1.0.0',
        ],
    ];
}

function makeLogic(): array
{
    return [
        'variables' => [
            'question_count' => 10,
        ],
        'execution_logic' => [
            'instructions' => 'Generate assessment items.',
        ],
    ];
}

function makeOutputs(): array
{
    return [
        'output_contract' => [
            'asset_type' => 'assessment',
        ],
    ];
}

function makePolicies(): array
{
    return [
        'security_policies' => [],
        'ai_policies' => [],
        'resource_policies' => [],
        'execution_policies' => [],
        'recovery_policies' => [],
    ];
}

it('creates a blueprint with a generated identity', function () {
    $blueprint = makeBlueprint();

    expect($blueprint->id())
        ->toBeInstanceOf(BlueprintId::class);

    expect((string) $blueprint->canonicalName())
        ->toBe('assessment-rubric-core');

    expect((string) $blueprint->namespace())
        ->toBe('skillvlt.edu.assessment');
});

it('preserves ownership and metadata', function () {
    $blueprint = makeBlueprint();

    expect($blueprint->ownership()['owner_type'])
        ->toBe('platform');

    expect($blueprint->metadata()['taxonomy']['domain'])
        ->toBe('education');
});

it('starts without revisions', function () {
    $blueprint = makeBlueprint();

    expect($blueprint->revisions())
        ->toBeEmpty()
        ->and($blueprint->latestRevision())
        ->toBeNull();
});

it('adds the first revision without a parent', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($revision)
        ->toBeInstanceOf(BlueprintRevision::class);

    expect($revision->parentRevisionId())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($revision);
});

it('automatically links a new revision to the previous revision', function () {
    $blueprint = makeBlueprint();

    $first = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $second = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64)
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($second->parentRevisionId())
        ->not->toBeNull()
        ->and((string) $second->parentRevisionId())
        ->toBe((string) $first->id());
});

it('preserves revision history', function () {
    $blueprint = makeBlueprint();

    $first = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $second = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64)
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($blueprint->revision($first->id()))
        ->toBe($first);

    expect($blueprint->revision($second->id()))
        ->toBe($second);

    expect($blueprint->revisions())
        ->toHaveCount(2);
});

it('updates metadata without creating a revision', function () {
    $blueprint = makeBlueprint();

    $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $blueprint->updateMetadata([
        'taxonomy' => [
            'domain' => 'education',
            'category' => 'assessment',
            'tags' => ['rubric', 'primary-school'],
        ],
    ]);

    expect($blueprint->revisions())
        ->toHaveCount(1);

    expect($blueprint->metadata()['taxonomy']['tags'])
        ->toContain('rubric');
});

it('starts in draft lifecycle', function () {
    $blueprint = makeBlueprint();

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::DRAFT);
});

it('cannot activate without a revision', function () {
    $blueprint = makeBlueprint();

    expect(fn () => $blueprint->activate())
        ->toThrow(DomainException::class);
});

it('can activate after adding a revision', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision->freeze();

    $blueprint->promoteRevision(
        $revision->id()
    );

    $blueprint->activate();

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::ACTIVE);
});

it('can deprecate an active blueprint', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());

    $blueprint->activate();
    $blueprint->deprecate();

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::DEPRECATED);
});

it('can sunset an active blueprint', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());

    $blueprint->activate();

    $blueprint->sunset();

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::SUNSET);
});

it('can sunset a deprecated blueprint', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());

    $blueprint->activate();

    $blueprint->deprecate();

    $blueprint->sunset();

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::SUNSET);
});

it('rejects invalid lifecycle transitions', function () {
    $blueprint = makeBlueprint();

    expect(fn () => $blueprint->deprecate())
        ->toThrow(DomainException::class);
});

it('sets the new revision as current revision', function () {
    $revision = $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($blueprint->currentRevisionId())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($revision);

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());

    expect($blueprint->currentRevision())
        ->toBe($revision);
});

it('reconstitutes a blueprint without changing its identity', function () {
    $originalId = BlueprintId::generate();

    $blueprint = Blueprint::reconstitute(
        id: $originalId,
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'owner_id' => 'skillvlt',
            'owner_type' => 'platform',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'education',
            ],
        ],
        lifecycleStatus: LifecycleStatus::ACTIVE,
        currentRevisionId: null,
    );

    expect($blueprint->id())
        ->toBe($originalId);

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::ACTIVE);
});

it('reconstitutes a blueprint with its revision history and current revision', function () {
    $blueprintId = BlueprintId::generate();

    $revisionOneId = RevisionId::generate();
    $revisionTwoId = RevisionId::generate();
    $revisionThreeId = RevisionId::generate();

    $revisionOne = BlueprintRevision::reconstitute(
        id: $revisionOneId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64)
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: false,
    );

    $revisionTwo = BlueprintRevision::reconstitute(
        id: $revisionTwoId,
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: $revisionOneId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64)
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: false,
    );

    $revisionThree = BlueprintRevision::reconstitute(
        id: $revisionThreeId,
        number: new RevisionNumber('1.2.0'),
        parentRevisionId: $revisionTwoId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('c', 64)
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: false,
    );

    $blueprint = Blueprint::reconstitute(
        id: $blueprintId,
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'owner_id' => 'skillvlt',
            'owner_type' => 'platform',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::ACTIVE,
        currentRevisionId: $revisionThreeId,
        revisions: [
            (string) $revisionOneId => $revisionOne,
            (string) $revisionTwoId => $revisionTwo,
            (string) $revisionThreeId => $revisionThree,
        ],
    );

    expect($blueprint->id())
        ->toBe($blueprintId);

    expect($blueprint->revisions())
        ->toHaveCount(3);

    expect($blueprint->revision($revisionOneId))
        ->toBe($revisionOne);

    expect($blueprint->revision($revisionTwoId))
        ->toBe($revisionTwo);

    expect($blueprint->revision($revisionThreeId))
        ->toBe($revisionThree);

    expect($blueprint->currentRevisionId())
        ->toBe($revisionThreeId);

    expect($blueprint->currentRevision())
        ->toBe($revisionThree);

    expect($revisionTwo->parentRevisionId())
        ->toBe($revisionOneId);

    expect($revisionThree->parentRevisionId())
        ->toBe($revisionTwoId);
});