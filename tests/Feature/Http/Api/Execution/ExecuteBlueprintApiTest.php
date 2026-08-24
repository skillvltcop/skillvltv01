<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes a blueprint through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
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

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'execution_id',
        'blueprint_id',
        'revision_id',
        'status',
        'input',
        'context',
        'output',
    ]);

    $response->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'revision_id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'status',
        'completed',
    );

    $response->assertJsonPath(
        'input.student.name',
        'Ahmed',
    );

    $response->assertJsonPath(
        'context.locale',
        'ar',
    );
});

it('returns 404 when the blueprint does not exist', function () {
    $user = User::factory()->create();

    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprintId}/execute",
            [
                'revision_id' => (string) $revisionId,
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when revision_id is missing', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute-validation',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'revision_id',
    ]);
});

it('persists the execution when executed through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute-persistence',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
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

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertSuccessful();

    $executionId = $response->json('execution_id');

    expect($executionId)->not->toBeNull();

    $this->assertDatabaseHas('executions', [
        'id' => $executionId,
        'blueprint_id' => (string) $blueprint->id(),
        'revision_id' => (string) $revision->id(),
        'status' => 'completed',
    ]);
});

it('forbids a user from executing another user blueprint', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-owner-protected',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
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

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertForbidden();
});

it('rejects unauthenticated blueprint execution', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/execute",
        [
            'revision_id' => (string) $revisionId,
            'input' => [],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});