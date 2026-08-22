<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('executes an activated blueprint revision from creation to completion', function () {
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

    $blueprint = $repository->find($blueprint->id());

    expect($blueprint)->not->toBeNull();

    $engine = new ExecutionEngine(
        runner: new BehaviorRunner(),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
            'answers' => [
                1 => 'A',
                2 => 'B',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($execution->status())
        ->toBe(ExecutionStatus::COMPLETED);

    expect((string) $execution->blueprintId())
        ->toBe((string) $blueprint->id());

    expect((string) $execution->revisionId())
        ->toBe((string) $revision->id());

    expect($execution->output())
        ->toBe([
            'steps' => [
                'validate',
                'score',
            ],
        ]);
});