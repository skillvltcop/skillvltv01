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
        40 => 'needs_support',
        60 => 'ready',
        90 => 'ready',
    ];

    foreach ($cases as $score => $expectedPositioning) {
        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/blueprints/{$blueprint->id()}/execute",
                [
                    'revision_id' => $revisionId,
                    'input' => [
                        'score' => $score,
                    ],
                    'context' => [],
                ],
            );

        $response->assertSuccessful();

        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('output.score', (string) $score);
        $response->assertJsonPath('output.positioning', $expectedPositioning);
    }
});
