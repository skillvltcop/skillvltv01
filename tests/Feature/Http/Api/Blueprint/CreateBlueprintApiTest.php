<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('creates a blueprint through the HTTP API', function () {
    $response = $this->postJson(
        '/api/blueprints',
        [
            'canonical_name' => 'assessment-rubric-core',
            'namespace' => 'skillvlt.edu.assessment',
            'ownership' => [
                'type' => 'system',
                'id' => 'skillvlt',
            ],
            'metadata' => [
                'taxonomy' => [
                    'domain' => 'education',
                ],
            ],
        ],
    );

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
        'system',
    );

    $response->assertJsonPath(
        'ownership.id',
        'skillvlt',
    );

    $response->assertJsonPath(
        'metadata.taxonomy.domain',
        'education',
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
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $this->assertDatabaseHas('blueprint_metadata', [
        'blueprint_id' => $blueprintId,
    ]);
});

it('returns 422 when required blueprint fields are missing', function () {
    $response = $this->postJson(
        '/api/blueprints',
        [],
    );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'canonical_name',
        'namespace',
        'ownership',
    ]);
});