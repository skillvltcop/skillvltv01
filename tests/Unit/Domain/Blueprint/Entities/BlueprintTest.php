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

it('freezes a revision through the Blueprint aggregate', function () {
    $blueprint = makeBlueprint();

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($revision->isFrozen())
        ->toBeFalse();

    $result = $blueprint->freezeRevision($revision->id());

    expect($result)
        ->toBe($revision);

    expect($revision->isFrozen())
        ->toBeTrue();
});

it('cannot freeze a revision on a sunset Blueprint through the aggregate', function () {
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

    $draftRevision = BlueprintRevision::reconstitute(
        id: RevisionId::generate(),
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: $revision->id(),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: false,
    );

    $sunsetBlueprint = Blueprint::reconstitute(
        id: $blueprint->id(),
        canonicalName: $blueprint->canonicalName(),
        namespace: $blueprint->namespace(),
        ownership: $blueprint->ownership(),
        metadata: $blueprint->metadata(),
        lifecycleStatus: LifecycleStatus::SUNSET,
        currentRevisionId: $revision->id(),
        revisions: [
            (string) $revision->id() => $revision,
            (string) $draftRevision->id() => $draftRevision,
        ],
    );

    expect(fn () => $sunsetBlueprint->freezeRevision($draftRevision->id()))
        ->toThrow(
            DomainException::class,
            'A sunset Blueprint cannot freeze a Revision.'
        );
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

it('cannot activate an already active blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    expect(fn () => $blueprint->activate())
        ->toThrow(
            DomainException::class,
            'Invalid Blueprint lifecycle transition: active → active.'
        );
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

    $revisionId = RevisionId::generate();

    $revision = BlueprintRevision::reconstitute(
        id: $revisionId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: true,
    );

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
        currentRevisionId: $revisionId,
        revisions: [
            (string) $revisionId => $revision,
        ],
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
        frozen: true,
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

it('maintains the revision parent chain in creation order', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName(
            'assessment-rubric-core',
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment',
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ),
        contracts: [],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [],
        policies: [],
    );

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ),
        contracts: [],
        logic: [
            'steps' => ['validate', 'score'],
        ],
        outputs: [],
        policies: [],
    );

    $revision3 = $blueprint->addRevision(
        number: new RevisionNumber('1.2.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
        ),
        contracts: [],
        logic: [
            'steps' => ['validate', 'score', 'publish'],
        ],
        outputs: [],
        policies: [],
    );

    expect($revision1->parentRevisionId())
        ->toBeNull();

    expect($revision2->parentRevisionId())
        ->not->toBeNull();

    expect((string) $revision2->parentRevisionId())
        ->toBe((string) $revision1->id());

    expect($revision3->parentRevisionId())
        ->not->toBeNull();

    expect((string) $revision3->parentRevisionId())
        ->toBe((string) $revision2->id());

    expect($blueprint->latestRevision())
        ->toBe($revision3);
});

it('cannot promote an unfrozen revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    expect(fn () => $blueprint->promoteRevision($revision->id()))
        ->toThrow(
            DomainException::class,
            'A Revision must be frozen before it can become current.'
        );
});

it('cannot promote a revision that does not belong to the blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $otherBlueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-other'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $otherBlueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $revision->freeze();

    expect(fn () => $blueprint->promoteRevision($revision->id()))
        ->toThrow(
            DomainException::class,
            'Blueprint revision not found.'
        );
});

it('allows an active blueprint to evolve without losing its current revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
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

    expect($blueprint->currentRevisionId())
        ->not->toBeNull()
        ->and((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision1->id());

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
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

    expect($revision2->parentRevisionId())
        ->not->toBeNull()
        ->and((string) $revision2->parentRevisionId())
        ->toBe((string) $revision1->id());

    expect((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision1->id());

    expect($revision1->isFrozen())
        ->toBeTrue();

    $revision2->freeze();

    $blueprint->promoteRevision($revision2->id());

    expect((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision2->id());

    expect($blueprint->revision($revision1->id()))
        ->toBe($revision1);

    expect($blueprint->revision($revision2->id()))
        ->toBe($revision2);

    expect($revision1->isFrozen())
        ->toBeTrue();

    expect($revision2->isFrozen())
        ->toBeTrue();
});

it('rejects a current revision that does not belong to the blueprint', function () {
    $currentRevisionId = RevisionId::generate();

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: $currentRevisionId,
        revisions: [],
    ))->toThrow(
        DomainException::class,
        'Current revision does not belong to the Blueprint.'
    );
});

it('cannot add a revision to a sunset blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-sunset'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

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

    expect(fn () => $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    ))->toThrow(DomainException::class);
});

it('allows a deprecated blueprint to receive a new revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-deprecated-evolution'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision1->freeze();

    $blueprint->promoteRevision($revision1->id());
    $blueprint->activate();
    $blueprint->deprecate();

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($revision2->parentRevisionId())
        ->not->toBeNull()
        ->and((string) $revision2->parentRevisionId())
        ->toBe((string) $revision1->id());

    expect($blueprint->currentRevisionId())
        ->not->toBeNull()
        ->and((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision1->id());
});

it('cannot add two revisions with the same revision number', function () {
    $blueprint = makeBlueprint();

    $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect(fn () => $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    ))->toThrow(DomainException::class);
});

it('cannot add a revision with a lower revision number than the latest revision', function () {
    $blueprint = makeBlueprint();

    $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect(fn () => $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    ))->toThrow(DomainException::class);
});

it('determines the latest revision by semantic revision number', function () {
    $revision1 = BlueprintRevision::reconstitute(
        id: RevisionId::generate(),
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $revision2 = BlueprintRevision::reconstitute(
        id: RevisionId::generate(),
        number: new RevisionNumber('1.10.0'),
        parentRevisionId: $revision1->id(),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $blueprint = Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-latest-revision'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::ACTIVE,
        currentRevisionId: $revision2->id(),
        revisions: [
            (string) $revision2->id() => $revision2,
            (string) $revision1->id() => $revision1,
        ],
    );

    expect($blueprint->latestRevision())
        ->toBe($revision2);
});

it('rejects an unfrozen current revision during reconstitution', function () {
    $revisionId = RevisionId::generate();

    $revision = BlueprintRevision::reconstitute(
        id: $revisionId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: false,
    );

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-invalid-current'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::ACTIVE,
        currentRevisionId: $revisionId,
        revisions: [
            (string) $revisionId => $revision,
        ],
    ))->toThrow(
        DomainException::class,
        'Current revision must be frozen.'
    );
});


it('rejects a revision whose parent does not belong to the blueprint', function () {
    $foreignParentId = RevisionId::generate();
    $revisionId = RevisionId::generate();

    $revision = BlueprintRevision::reconstitute(
        id: $revisionId,
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: $foreignParentId,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-invalid-parent'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: null,
        revisions: [
            (string) $revisionId => $revision,
        ],
    ))->toThrow(
        DomainException::class,
        'Revision parent does not belong to the Blueprint.'
    );
});

it('rejects a revision whose parent has a greater revision number', function () {
    $parentRevisionId = RevisionId::generate();
    $childRevisionId = RevisionId::generate();

    $parentRevision = BlueprintRevision::reconstitute(
        id: $parentRevisionId,
        number: new RevisionNumber('2.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $childRevision = BlueprintRevision::reconstitute(
        id: $childRevisionId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: $parentRevisionId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-invalid-parent-order'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: null,
        revisions: [
            (string) $parentRevisionId => $parentRevision,
            (string) $childRevisionId => $childRevision,
        ],
    ))->toThrow(
        DomainException::class,
        'Revision parent must be older than the Revision.'
    );
});

it('rejects revision history with multiple root revisions', function () {
    $revisionOneId = RevisionId::generate();
    $revisionTwoId = RevisionId::generate();

    $revisionOne = BlueprintRevision::reconstitute(
        id: $revisionOneId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $revisionTwo = BlueprintRevision::reconstitute(
        id: $revisionTwoId,
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: null,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-multiple-roots'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: null,
        revisions: [
            (string) $revisionOneId => $revisionOne,
            (string) $revisionTwoId => $revisionTwo,
        ],
    ))->toThrow(
        DomainException::class,
        'Revision history must contain exactly one root Revision.'
    );
});

it('cannot promote a revision older than the current revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-no-rollback'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision1->freeze();
    $revision2->freeze();

    $blueprint->promoteRevision($revision1->id());

    expect(fn () => $blueprint->promoteRevision($revision1->id()))
        ->toThrow(
            DomainException::class,
            'A Revision must be newer than the current Revision.'
        );

    $blueprint->promoteRevision($revision2->id());

    expect($blueprint->currentRevision())
        ->toBe($revision2);
});

it('allows a deprecated blueprint to freeze and promote a newer revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-deprecated-promotion'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision1->freeze();
    $blueprint->promoteRevision($revision1->id());
    $blueprint->activate();
    $blueprint->deprecate();

    $revision2 = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::DEPRECATED);

    $blueprint->freezeRevision($revision2->id());
    $blueprint->promoteRevision($revision2->id());

    expect($blueprint->currentRevision())
        ->toBe($revision2)
        ->and($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::DEPRECATED);
});

it('does not allow a deprecated blueprint to become active again', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-deprecated-reactivation'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

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

    expect(fn () => $blueprint->activate())
        ->toThrow(
            DomainException::class,
            'Invalid Blueprint lifecycle transition: deprecated → active.'
        );
});

it('cannot promote a revision on a sunset blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-sunset-promotion'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision1 = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
    );

    $revision1->freeze();

    $blueprint->promoteRevision($revision1->id());
    $blueprint->activate();
    $blueprint->sunset();

    expect(fn () => $blueprint->promoteRevision($revision1->id()))
        ->toThrow(
            DomainException::class,
            'A sunset Blueprint cannot promote a Revision.'
        );
});

it('rejects non-draft lifecycle states without a current revision', function ($status) {
    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-invalid-lifecycle'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: $status,
        currentRevisionId: null,
        revisions: [],
    ))->toThrow(
        DomainException::class,
        'A non-draft Blueprint must have a current Revision.'
    );
})->with([
    LifecycleStatus::ACTIVE,
    LifecycleStatus::DEPRECATED,
    LifecycleStatus::SUNSET,
]);

it('rejects branching revision history', function () {
    $rootRevisionId = RevisionId::generate();
    $childOneRevisionId = RevisionId::generate();
    $childTwoRevisionId = RevisionId::generate();

    $rootRevision = BlueprintRevision::reconstitute(
        id: $rootRevisionId,
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: null,
        behaviorDigest: makeDigest(),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $childOne = BlueprintRevision::reconstitute(
        id: $childOneRevisionId,
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: $rootRevisionId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    $childTwo = BlueprintRevision::reconstitute(
        id: $childTwoRevisionId,
        number: new RevisionNumber('1.2.0'),
        parentRevisionId: $rootRevisionId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('c', 64),
        ),
        contracts: makeContracts(),
        logic: makeLogic(),
        outputs: makeOutputs(),
        policies: makePolicies(),
        frozen: true,
    );

    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName(
            'assessment-rubric-branching-history'
        ),
        namespace: new BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: $childTwoRevisionId,
        revisions: [
            (string) $rootRevisionId => $rootRevision,
            (string) $childOneRevisionId => $childOne,
            (string) $childTwoRevisionId => $childTwo,
        ],
    ))->toThrow(
        DomainException::class,
        'Revision history must be linear.'
    );
});

it('cannot sunset a draft blueprint', function () {
    $blueprint = makeBlueprint();

    expect(fn () => $blueprint->sunset())
        ->toThrow(DomainException::class);
});

it('does not expose mutable metadata state', function () {
    $blueprint = makeBlueprint();

    $originalMetadata = $blueprint->metadata();
    $metadata = $blueprint->metadata();

    $metadata['taxonomy']['domain'] = 'modified';
    $metadata['taxonomy']['tags'][] = 'modified';

    expect($blueprint->metadata())
        ->toBe($originalMetadata);
});