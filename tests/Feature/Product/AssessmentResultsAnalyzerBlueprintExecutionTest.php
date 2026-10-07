<?php

declare(strict_types=1);

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('probes the current DSL for assessment results analysis', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-results-analyzer',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) test()->createUser()->id,
        ],
        metadata: [
            'title' => 'Assessment Results Analyzer',
            'description' => 'Analyzes a group of assessment results.',
        ],
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
                'required' => ['learners'],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'calculate',
                    'operation' => 'divide',
                    'left' => 'input.learners.0.score',
                    'right' => 'input.learners.0.max_score',
                    'assign_to' => 'percentage',
                ],
                [
                    'type' => 'calculate',
                    'operation' => 'multiply',
                    'left' => 'state.percentage',
                    'right' => 100,
                    'assign_to' => 'percentage',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'count' => '{input.learners.0.name}',
                        'average_percentage' => '{state.percentage}',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'assessment-results-analyzer',
            'schema_version' => 1,
        ],
        policies: [
            'visibility' => 'private',
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

    $user = test()->actingAsUser();
    $result = (new ExecuteBlueprint($repository, new BehaviorRunner()))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
        userId: (string) $user->id,
        input: [
            'learners' => [
                ['name' => 'أحمد', 'score' => 14, 'max_score' => 20],
                ['name' => 'سارة', 'score' => 18, 'max_score' => 20],
                ['name' => 'يوسف', 'score' => 9, 'max_score' => 20],
                ['name' => 'مريم', 'score' => 12, 'max_score' => 20],
            ],
        ],
        context: [],
    );

    expect($result->status()->value)->toBe('completed')
        ->and($result->output())->toMatchArray([
            'average_percentage' => 70,
        ]);
});
