<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('rejects invalid assessment positioning inputs before execution', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $invalidCases = [
        [
            'input' => ['max_score' => 10],
            'error' => 'input.score',
        ],
        [
            'input' => ['score' => -1, 'max_score' => 10],
            'error' => 'input.score',
        ],
        [
            'input' => ['score' => 5],
            'error' => 'input.max_score',
        ],
        [
            'input' => ['score' => 5, 'max_score' => 0],
            'error' => 'input.max_score',
        ],
        [
            'input' => ['score' => 11, 'max_score' => 10],
            'error' => 'input.score',
        ],
        [
            'input' => ['score' => '1e309', 'max_score' => 10],
            'error' => 'input.score',
        ],
        [
            'input' => ['score' => 5, 'max_score' => '1e309'],
            'error' => 'input.max_score',
        ],
    ];

    foreach ($invalidCases as $case) {
        $response = $this
            ->actingAs($user)
            ->postJson('/api/teacher/tools/assessment-positioning/execute', [
                'input' => $case['input'],
                'context' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$case['error']]);
    }
});
