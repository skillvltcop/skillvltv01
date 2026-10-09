<?php

use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes the seeded assessment positioning system blueprint through the HTTP API', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $blueprints = (new EloquentBlueprintRepository())->discover();

    expect($blueprints)->toHaveCount(1);

    $blueprint = $blueprints[0];

    $revisionId = (string) $blueprint->currentRevisionId();

    $cases = [
        [40, 100, 'needs_support'],
        [60, 100, 'ready'],
        [90, 100, 'ready'],
    ];

    foreach ($cases as [$score, $maxScore, $expectedPositioning]) {
        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/blueprints/{$blueprint->id()}/execute",
                [
                    'revision_id' => $revisionId,
                    'input' => [
                        'score' => $score,
                        'max_score' => $maxScore,
                    ],
                    'context' => [],
                ],
            );

        $response->assertSuccessful();

        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('output.score', $score);
        $response->assertJsonPath('output.positioning', $expectedPositioning);
    }
});

it('returns 422 when a required input field is missing through the blueprint API', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $blueprint = (new EloquentBlueprintRepository())->discover()[0];

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $blueprint->currentRevisionId(),
                'input' => ['score' => 59],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();
    $response->assertJsonPath(
        'message',
        'Input is missing required field(s): max_score.',
    );
});
