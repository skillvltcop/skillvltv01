<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('adds a revision to a blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-revision',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions",
        [
            'number' => '1.0.0',
            'behavior_digest' =>
                'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
            'contracts' => [
                'input' => [
                    'type' => 'object',
                ],
            ],
            'logic' => [
                'steps' => [
                    'validate',
                    'score',
                ],
            ],
            'outputs' => [
                'type' => 'assessment-result',
            ],
            'policies' => [
                'visibility' => 'public',
            ],
        ],
    );

    $response->assertCreated();

    $response->assertJsonStructure([
        'id',
        'blueprint_id',
        'number',
        'behavior_digest',
        'contracts',
        'logic',
        'outputs',
        'policies',
        'frozen',
    ]);

    $response->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'behavior_digest',
        'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
    );

    $response->assertJsonPath(
        'contracts.input.type',
        'object',
    );

    $response->assertJsonPath(
        'logic.steps.0',
        'validate',
    );

    $response->assertJsonPath(
        'logic.steps.1',
        'score',
    );

    $response->assertJsonPath(
        'outputs.type',
        'assessment-result',
    );

    $response->assertJsonPath(
        'policies.visibility',
        'public',
    );

    $response->assertJsonPath(
        'frozen',
        false,
    );

    $revisionId = $response->json('id');

    expect($revisionId)->not->toBeNull();

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => $revisionId,
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.0.0',
        'behavior_digest' =>
            'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
        'frozen' => false,
    ]);
});

it('returns 404 when adding a revision to a missing blueprint', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/revisions",
        [
            'number' => '1.0.0',
            'behavior_digest' =>
                'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
            'contracts' => [
                'input' => [
                    'type' => 'object',
                ],
            ],
            'logic' => [
                'steps' => [
                    'validate',
                    'score',
                ],
            ],
            'outputs' => [
                'type' => 'assessment-result',
            ],
            'policies' => [
                'visibility' => 'public',
            ],
        ],
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when required revision fields are missing', function () {
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
        "/api/blueprints/{$blueprint->id()}/revisions",
        [],
    );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'number',
        'behavior_digest',
        'contracts',
        'logic',
        'outputs',
        'policies',
    ]);
});