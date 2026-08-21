<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;

it('creates and persists a blueprint', function () {
    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with(Mockery::type(Blueprint::class));

    $command = new CreateBlueprint($repository);

    $blueprint = $command->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
    );

    expect($blueprint)
        ->toBeInstanceOf(Blueprint::class);

    expect((string) $blueprint->canonicalName())
        ->toBe('assessment-rubric-core');

    expect((string) $blueprint->namespace())
        ->toBe('skillvlt.edu.assessment');
});

it('preserves ownership and metadata', function () {
    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with(Mockery::on(function (Blueprint $blueprint) {
            expect($blueprint->ownership())
                ->toBe([
                    'type' => 'system',
                    'id' => 'skillvlt',
                ]);

            expect($blueprint->metadata())
                ->toBe([
                    'category' => 'education',
                    'visibility' => 'public',
                ]);

            return true;
        }));

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

    expect($blueprint)
        ->toBeInstanceOf(Blueprint::class);
});