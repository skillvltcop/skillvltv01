<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

function makeRevision(
    ?RevisionId $parentRevisionId = null,
): BlueprintRevision {
    return BlueprintRevision::create(
        number: new RevisionNumber('1.0.0'),
        parentRevisionId: $parentRevisionId,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64)
        ),
        contracts: [
            'executable_contract' => [
                'target_engine' => 'assessment',
                'min_engine_version' => '1.0.0',
            ],
        ],
        logic: [
            'variables' => [
                'question_count' => 10,
            ],
            'execution_logic' => [
                'instructions' => 'Generate assessment items.',
            ],
        ],
        outputs: [
            'output_contract' => [
                'asset_type' => 'assessment',
            ],
        ],
        policies: [
            'security_policies' => [],
            'ai_policies' => [],
            'resource_policies' => [],
            'execution_policies' => [],
            'recovery_policies' => [],
        ],
    );
}

it('creates a revision with a generated id', function () {
    $revision = makeRevision();

    expect($revision->id())
        ->toBeInstanceOf(RevisionId::class);
});

it('preserves revision number', function () {
    $revision = makeRevision();

    expect((string) $revision->number())
        ->toBe('1.0.0');
});

it('supports a parent revision', function () {
    $parentId = RevisionId::generate();

    $revision = makeRevision($parentId);

    expect($revision->parentRevisionId())
        ->not->toBeNull()
        ->and((string) $revision->parentRevisionId())
        ->toBe((string) $parentId);
});

it('preserves the behavior digest', function () {
    $revision = makeRevision();

    expect((string) $revision->behaviorDigest())
        ->toBe('sha256:' . str_repeat('a', 64));
});

it('preserves contracts logic outputs and policies', function () {
    $revision = makeRevision();

    expect($revision->contracts())->toHaveKey('executable_contract')
        ->and($revision->logic())->toHaveKey('variables')
        ->and($revision->outputs())->toHaveKey('output_contract')
        ->and($revision->policies())->toHaveKey('security_policies');
});

it('starts as a draft revision', function () {
    $revision = makeRevision();

    expect($revision->isFrozen())->toBeFalse();
});

it('can be frozen', function () {
    $revision = makeRevision();

    $revision->freeze();

    expect($revision->isFrozen())->toBeTrue();
});

it('returns defensive copies of behavioral data', function () {
    $revision = makeRevision();

    $logic = $revision->logic();

    $logic['variables']['question_count'] = 999;

    expect($revision->logic()['variables']['question_count'])
        ->toBe(10);
});

it('reconstitutes a revision without changing its identity', function () {
    $revisionId = RevisionId::generate();

    $revision = BlueprintRevision::reconstitute(
        id: $revisionId,
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: null,
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64)
        ),
        contracts: [
            'executable_contract' => [
                'target_engine' => 'assessment',
                'min_engine_version' => '1.0.0',
            ],
        ],
        logic: [
            'variables' => [
                'question_count' => 10,
            ],
        ],
        outputs: [
            'output_contract' => [
                'asset_type' => 'assessment',
            ],
        ],
        policies: [
            'security_policies' => [],
            'ai_policies' => [],
            'resource_policies' => [],
            'execution_policies' => [],
            'recovery_policies' => [],
        ],
        frozen: false,
    );

    expect($revision->id())
        ->toBe($revisionId);

    expect((string) $revision->number())
        ->toBe('1.1.0');

    expect((string) $revision->behaviorDigest())
        ->toBe(
            'sha256:' . str_repeat('a', 64)
        );
});

it('cannot be frozen twice', function () {
    $revision = makeRevision();

    $revision->freeze();

    expect(fn () => $revision->freeze())
        ->toThrow(
            DomainException::class,
            'Revision is already frozen.'
        );
});