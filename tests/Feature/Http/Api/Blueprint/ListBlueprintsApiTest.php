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

it('lists the authenticated user blueprints through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $firstBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [
            'documentation' => [
                'description' => 'Assessment rubric blueprint.',
            ],
        ],
    );

    $secondBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'student-evaluation',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints');

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
        2,
        'data',
    );

    $response->assertJsonFragment([
        'id' => (string) $firstBlueprint->id(),
        'canonical_name' => 'assessment-rubric',
        'namespace' => 'skillvlt.edu.assessment',
    ]);

    $response->assertJsonFragment([
        'id' => (string) $secondBlueprint->id(),
        'canonical_name' => 'student-evaluation',
        'namespace' => 'skillvlt.edu.assessment',
    ]);

    $response->assertJsonPath(
        'data.0.ownership.type',
        'user',
    );

    $response->assertJsonPath(
        'data.0.ownership.id',
        (string) $user->id,
    );
});

it('does not return another user blueprints', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $ownedBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'my-blueprint',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $otherBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'other-blueprint',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $otherUser->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints');

    $response->assertSuccessful();

    $response->assertJsonCount(
        1,
        'data',
    );

    $response->assertJsonFragment([
        'id' => (string) $ownedBlueprint->id(),
        'canonical_name' => 'my-blueprint',
    ]);

    $response->assertJsonMissing([
        'id' => (string) $otherBlueprint->id(),
        'canonical_name' => 'other-blueprint',
    ]);
});

it('returns an empty list when the authenticated user has no blueprints', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints');

    $response->assertSuccessful();

    $response->assertJson([
        'data' => [],
    ]);
});

it('rejects unauthenticated blueprint listing', function () {
    $response = $this->getJson('/api/blueprints');

    $response->assertUnauthorized();
});