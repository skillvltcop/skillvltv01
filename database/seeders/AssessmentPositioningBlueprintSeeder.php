<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Database\Seeder;

final class AssessmentPositioningBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $repository = new EloquentBlueprintRepository();

        $existing = collect(
            $repository->discover()
        )->first(
            fn ($blueprint): bool =>
                (string) $blueprint->canonicalName() === 'assessment-positioning'
        );

        if ($existing !== null) {
            return;
        }

        $blueprint = (new CreateBlueprint($repository))->handle(
            canonicalName: 'assessment-positioning',
            namespace: 'skillvlt.edu.assessment',
            ownership: [
                'type' => 'system',
                'id' => 'skillvlt',
            ],
            metadata: [
                'title' => 'Assessment Positioning',
                'description' => 'Determines whether a learner needs support from an assessment score.',
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
                    'required' => ['score'],
                ],
            ],
            logic: [
                'type' => 'steps',
                'version' => 1,
                'steps' => [
                    [
                        'type' => 'evaluate_rule',
                        'condition' => [
                            'field' => 'input.score',
                            'operator' => 'gte',
                            'value' => 60,
                        ],
                        'assign_to' => 'positioning',
                        'true_value' => 'ready',
                        'false_value' => 'needs_support',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'score' => '{input.score}',
                            'positioning' => '{state.positioning}',
                        ],
                    ],
                ],
            ],
            outputs: [
                'type' => 'assessment-positioning',
                'schema_version' => 1,
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
    }
}
