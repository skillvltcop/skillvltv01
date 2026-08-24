<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('discovers public system blueprints through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $discoverableBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-show',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
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
        ],
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'canonical_name',
                'namespace',
                'ownership',
                'metadata',
                'lifecycle_status',
                'current_revision_id',
            ],
        ],
    ]);

    $response->assertJsonCount(
        1,
        'data',
    );

    $response->assertJsonFragment([
        'id' => (string) $discoverableBlueprint->id(),
        'canonical_name' => 'assessment-rubric-show',
        'namespace' => 'skillvlt.edu.assessment',
    ]);

    $response->assertJsonPath(
        'data.0.ownership.type',
        'system',
    );

    $response->assertJsonPath(
        'data.0.ownership.id',
        'skillvlt',
    );

    $response->assertJsonPath(
        'data.0.metadata.taxonomy.domain',
        'assessment',
    );

    $response->assertJsonPath(
        'data.0.metadata.documentation.description',
        'Assessment rubric blueprint.',
    );

    $response->assertJsonPath(
        'data.0.metadata.discovery.tags.0',
        'assessment',
    );
});

it('does not expose another user blueprint through discovery', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $systemBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-assessment',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'discovery' => [
                'tags' => [
                    'assessment',
                ],
            ],
        ],
    );

    $privateBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'private-assessment',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $otherUser->id,
        ],
        metadata: [
            'discovery' => [
                'tags' => [
                    'assessment',
                ],
            ],
        ],
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();

    $response->assertJsonCount(
        1,
        'data',
    );

    $response->assertJsonFragment([
        'id' => (string) $systemBlueprint->id(),
        'canonical_name' => 'system-assessment',
    ]);

    $response->assertJsonMissing([
        'id' => (string) $privateBlueprint->id(),
        'canonical_name' => 'private-assessment',
    ]);
});

it('returns an empty discovery list when no discoverable blueprints exist', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();

    $response->assertJson([
        'data' => [],
    ]);
});

it('rejects unauthenticated blueprint discovery', function () {
    $response = $this->getJson('/api/blueprints/discover');

    $response->assertUnauthorized();
});