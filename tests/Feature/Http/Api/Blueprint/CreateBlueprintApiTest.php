<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('creates a blueprint through the HTTP API', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->postJson('/api/blueprints', [
            'canonical_name' => 'assessment-rubric-core',
            'namespace' => 'skillvlt.edu.assessment',
            'metadata' => [
                'taxonomy' => [
                    'domain' => 'assessment',
                ],
            ],
        ]);

    $response->assertCreated();

    $response->assertJsonStructure([
        'id',
        'canonical_name',
        'namespace',
        'ownership',
        'metadata',
        'lifecycle_status',
    ]);

    $response->assertJsonPath(
        'canonical_name',
        'assessment-rubric-core',
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
        'metadata.taxonomy.domain',
        'assessment',
    );

    $response->assertJsonPath(
        'lifecycle_status',
        'draft',
    );

    $blueprintId = $response->json('id');

    expect($blueprintId)->not->toBeNull();

    $this->assertDatabaseHas('blueprints', [
        'id' => $blueprintId,
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'user',
        'owner_id' => (string) $user->id,
        'lifecycle_status' => 'draft',
    ]);

    $this->assertDatabaseHas('blueprint_metadata', [
        'blueprint_id' => $blueprintId,
    ]);
});

it('returns 422 when required blueprint fields are missing', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(
            '/api/blueprints',
            [],
        );
    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'canonical_name',
        'namespace',
    ]);
});

it('assigns the authenticated user as blueprint owner', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/blueprints', [
            'canonical_name' => 'test-blueprint',
            'namespace' => 'test',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'ownership.type',
            'user',
        )
        ->assertJsonPath(
            'ownership.id',
            (string) $user->id,
        );
});

it('does not allow the client to choose blueprint ownership', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/blueprints', [
            'canonical_name' => 'test-blueprint',
            'namespace' => 'test',
            'ownership' => [
                'type' => 'user',
                'id' => 'someone-else',
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('ownership.type', 'user')
        ->assertJsonPath('ownership.id', (string) $user->id);
});