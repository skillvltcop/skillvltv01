<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('retrieves a blueprint through the HTTP API', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-show',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'assessment',
            ],
            'documentation' => [
                'description' => 'Assessment rubric blueprint.',
            ],
            'discovery' => [
                'tags' => [
                    'assessment',
                    'rubric',
                ],
            ],
            'lifecycle_metadata' => [
                'versioning' => 'semantic',
            ],
        ],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/blueprints/{$blueprint->id()}",
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'id',
        'canonical_name',
        'namespace',
        'ownership',
        'metadata',
        'lifecycle_status',
        'current_revision_id',
        'revisions',
    ]);

    $response->assertJsonPath(
        'id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'canonical_name',
        'assessment-rubric-show',
    );

    $response->assertJsonPath(
        'namespace',
        'skillvlt.edu.assessment',
    );

    $response->assertJsonPath(
        'ownership.type',
        'user',
    );

    $response->assertJsonPath(
        'ownership.id',
        (string) $user->id,
    );

    $response->assertJsonPath(
        'lifecycle_status',
        'draft',
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'metadata.taxonomy.domain',
        'assessment',
    );

    $response->assertJsonPath(
        'metadata.documentation.description',
        'Assessment rubric blueprint.',
    );

    $response->assertJsonCount(
        1,
        'revisions',
    );

    $response->assertJsonPath(
        'revisions.0.id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'revisions.0.number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'revisions.0.frozen',
        true,
    );
});

it('returns 404 when the blueprint does not exist', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/blueprints/{$blueprintId}",
        );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('rejects unauthenticated blueprint access', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this->getJson(
        "/api/blueprints/{$blueprintId}",
    );

    $response->assertUnauthorized();
});

it('forbids a user from accessing another user blueprint', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'private-blueprint',
        namespace: 'skillvlt.edu.private',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
    );

    $response = $this
        ->actingAs($otherUser)
        ->getJson(
            "/api/blueprints/{$blueprint->id()}",
        );

    $response->assertForbidden();
});