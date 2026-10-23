<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\DeprecateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Blueprint\Commands\SunsetBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('discovers only active public system blueprints with a frozen current revision', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $discoverableBlueprint = createDiscoverableBlueprint($repository);

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'canonical_name',
                'title',
                'description',
                'category',
                'tags',
                'version',
            ],
        ],
    ]);

    $response->assertJsonCount(1, 'data');

    $response->assertJsonPath('data.0.canonical_name', 'assessment-rubric-show');
    $response->assertJsonPath('data.0.title', 'assessment-rubric-show');
    $response->assertJsonPath('data.0.description', 'Assessment rubric blueprint.');
    $response->assertJsonPath('data.0.category', 'assessment');
    $response->assertJsonPath('data.0.tags', ['assessment', 'rubric']);
    $response->assertJsonPath('data.0.version', '1.0.0');
    $response->assertJsonMissingPath('data.0.id');
    $response->assertJsonMissingPath('data.0.namespace');
    $response->assertJsonMissingPath('data.0.ownership');
    $response->assertJsonMissingPath('data.0.metadata');
    $response->assertJsonMissingPath('data.0.lifecycle_status');
    $response->assertJsonMissingPath('data.0.current_revision_id');
});

it('does not discover a draft system blueprint', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $draftBlueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'draft-assessment',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJson(['data' => []]);

    expect($draftBlueprint->currentRevisionId())->toBeNull();
});

it('does not discover a deprecated public system blueprint', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = createDiscoverableBlueprint($repository);

    (new DeprecateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJson(['data' => []]);
});

it('does not discover a sunset public system blueprint', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = createDiscoverableBlueprint($repository);

    (new SunsetBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJson(['data' => []]);
});

it('does not discover a private active system blueprint', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = createDiscoverableBlueprint(
        $repository,
        visibility: 'private',
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJson(['data' => []]);

    expect($blueprint->lifecycleStatus()->value)->toBe('active');
});

it('does not expose another user blueprint through discovery', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $systemBlueprint = createDiscoverableBlueprint($repository);

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
    $response->assertJsonCount(1, 'data');

    $response->assertJsonPath('data.0.canonical_name', 'assessment-rubric-show');

    $response->assertJsonMissing([
        'id' => (string) $privateBlueprint->id(),
        'canonical_name' => 'private-assessment',
    ]);
});

it('localizes public discovery title and description by requested locale', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    createDiscoverableBlueprint(
        $repository,
        metadataOverrides: [
            'title' => [
                'ar' => 'عنوان عربي',
                'fr' => 'Titre français',
                'en' => 'English title',
            ],
            'documentation' => [
                'description' => [
                    'ar' => 'وصف عربي',
                    'fr' => 'Description française',
                    'en' => 'English description',
                ],
            ],
        ],
    );

    $this->actingAs($user)
        ->getJson('/api/blueprints/discover?locale=fr')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Titre français')
        ->assertJsonPath('data.0.description', 'Description française');

    $this->actingAs($user)
        ->getJson('/api/blueprints/discover?locale=ar')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'عنوان عربي')
        ->assertJsonPath('data.0.description', 'وصف عربي');
});

it('returns an empty discovery list when no discoverable blueprints exist', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJson(['data' => []]);
});

it('rejects unauthenticated blueprint discovery', function () {
    $response = $this->getJson('/api/blueprints/discover');

    $response->assertUnauthorized();
});

function createDiscoverableBlueprint(
    EloquentBlueprintRepository $repository,
    string $visibility = 'public',
    array $metadataOverrides = [],
) {
    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-show',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: array_replace_recursive([
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
        ], $metadataOverrides),
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'status' => 'ok',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'assessment-rubric',
            'schema_version' => 1,
        ],
        policies: [
            'visibility' => $visibility,
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

    return (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );
}
