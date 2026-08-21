<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Models\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and persists a blueprint through the application layer', function () {
    $repository = new EloquentBlueprintRepository();

    expect($repository)
        ->toBeInstanceOf(BlueprintRepository::class);

    $command = new CreateBlueprint($repository);

    $blueprint = $command->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'category' => 'education',
            'visibility' => 'public',
        ],
    );

    expect($blueprint->id())
        ->not->toBeNull();

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'canonical_name' => 'assessment-rubric-core',
        'namespace' => 'skillvlt.edu.assessment',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);
});