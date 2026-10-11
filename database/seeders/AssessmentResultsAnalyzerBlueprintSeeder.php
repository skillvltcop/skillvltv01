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

final class AssessmentResultsAnalyzerBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $repository = new EloquentBlueprintRepository();
        $existing = $repository->findByCanonicalName(
            'skillvlt.edu.assessment',
            'assessment-results-analyzer',
        );

        if ($existing !== null) {
            if ($existing->revisions() !== []) {
                return;
            }

            $blueprint = $existing;
        } else {
            $blueprint = (new CreateBlueprint($repository))->handle(
                canonicalName: 'assessment-results-analyzer',
                namespace: 'skillvlt.edu.assessment',
                ownership: [
                    'type' => 'system',
                    'id' => 'skillvlt',
                ],
                metadata: [
                    'title' => [
                        'ar' => 'محلل نتائج التقويم',
                        'fr' => 'Analyseur des résultats d’évaluation',
                        'en' => 'Assessment Results Analyzer',
                    ],
                    'description' => [
                        'ar' => 'تحليل نتائج مجموعة من المتعلمين وحساب المتوسط ونسبة النجاح وتحديد من يحتاج إلى الدعم.',
                        'fr' => 'Analyse les résultats d’un groupe, calcule la moyenne et le taux de réussite, et repère les élèves ayant besoin de soutien.',
                        'en' => 'Analyzes a group of learner results, calculates averages and success rates, and identifies learners needing support.',
                    ],
                    'purpose' => [
                        'ar' => 'أدخل نتائج المتعلمين لتحصل على ملخص جماعي وتحديد فردي للحاجة إلى الدعم.',
                        'fr' => 'Saisissez les résultats des apprenants pour obtenir un bilan collectif et repérer les besoins de soutien.',
                        'en' => 'Enter learner results to get a group summary and identify individual support needs.',
                    ],
                    'target_level' => ['ar' => '5-6', 'fr' => '5-6', 'en' => '5-6'],
                ],
            );
        }

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
                        'type' => 'map',
                        'source' => 'input.learners',
                        'assign_to' => 'results',
                        'steps' => [
                            [
                                'type' => 'calculate',
                                'operation' => 'divide',
                                'left' => 'item.score',
                                'right' => 'item.max_score',
                                'assign_to' => 'ratio',
                            ],
                            [
                                'type' => 'calculate',
                                'operation' => 'multiply',
                                'left' => 'state.ratio',
                                'right' => 100,
                                'assign_to' => 'percentage',
                            ],
                            [
                                'type' => 'evaluate_rule',
                                'condition' => [
                                    'field' => 'state.percentage',
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
                                    'name' => '{item.name}',
                                    'score' => '{item.score}',
                                    'max_score' => '{item.max_score}',
                                    'percentage' => '{state.percentage}',
                                    'positioning' => '{state.positioning}',
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'aggregate',
                        'source' => 'state.results',
                        'operation' => 'count',
                        'assign_to' => 'count',
                    ],
                    [
                        'type' => 'aggregate',
                        'source' => 'state.results',
                        'operation' => 'average',
                        'field' => 'percentage',
                        'assign_to' => 'average_percentage',
                    ],
                    [
                        'type' => 'aggregate',
                        'source' => 'state.results',
                        'operation' => 'count_matching',
                        'field' => 'positioning',
                        'operator' => 'eq',
                        'value' => 'ready',
                        'assign_to' => 'ready_count',
                    ],
                    [
                        'type' => 'calculate',
                        'operation' => 'subtract',
                        'left' => 'state.count',
                        'right' => 'state.ready_count',
                        'assign_to' => 'needs_support_count',
                    ],
                    [
                        'type' => 'calculate',
                        'operation' => 'divide',
                        'left' => 'state.ready_count',
                        'right' => 'state.count',
                        'assign_to' => 'success_ratio',
                    ],
                    [
                        'type' => 'calculate',
                        'operation' => 'multiply',
                        'left' => 'state.success_ratio',
                        'right' => 100,
                        'assign_to' => 'success_rate',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'learners' => '{state.results}',
                            'count' => '{state.count}',
                            'average_percentage' => '{state.average_percentage}',
                            'ready_count' => '{state.ready_count}',
                            'needs_support_count' => '{state.needs_support_count}',
                            'success_rate' => '{state.success_rate}',
                            'support_threshold' => 60,
                        ],
                    ],
                ],
            ],
            outputs: [
                'type' => 'assessment-results-analysis',
                'schema_version' => 1,
            ],
            policies: ['visibility' => 'public'],
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
