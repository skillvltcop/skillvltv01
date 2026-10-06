<?php

use App\Models\User;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('renders the assessment score calculator teacher page through the generic tool route', function () {
    $user = User::factory()->create();

    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get('/teacher/tools/assessment-score-calculator');

    $response->assertSuccessful();
    $response->assertSee('Assessment Score Calculator');
    $response->assertSee('/api/teacher/blueprints/discover', false);
    $response->assertSee('const toolSlug =', false);
    $response->assertSee('executionEndpoint', false);
    $response->assertSee('Calculate result');
    $response->assertSee('Correct answers');
    $response->assertSee('Total');
});

it('renders the localized shell for supported locales', function () {
    $user = User::factory()->create();

    $expected = [
        'ar' => [
            'title' => 'حاسبة نتيجة التقويم',
            'execute' => 'حساب النتيجة',
        ],
        'fr' => [
            'title' => 'Calculateur de score d’évaluation',
            'execute' => 'Calculer le résultat',
        ],
        'en' => [
            'title' => 'Assessment Score Calculator',
            'execute' => 'Calculate result',
        ],
    ];

    foreach ($expected as $locale => $texts) {
        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => $locale])
            ->get('/teacher/tools/assessment-score-calculator');

        $response->assertSuccessful();
        $response->assertSee($texts['title']);
        $response->assertSee($texts['execute']);
    }
});

it('requires browser authentication', function () {
    $response = $this->get('/teacher/tools/assessment-score-calculator');

    $response->assertRedirect('/login');
});
