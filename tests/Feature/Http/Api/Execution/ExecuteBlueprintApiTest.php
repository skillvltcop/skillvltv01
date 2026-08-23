<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes a blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
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

    $response = $this->postJson(
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
        'output.steps.0',
        'validate',
    );

    $response->assertJsonPath(
        'output.steps.1',
        'score',
    );
});

it('returns 404 when the blueprint does not exist', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();
    $revisionId = \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    $response = $this->postJson(
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
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-validation',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $response = $this->postJson(
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