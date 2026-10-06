<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('renders the assessment positioning teacher page through the generic tool route', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get('/teacher/tools/assessment-positioning');

    $response->assertSuccessful();
    $response->assertSee('Assessment Positioning Tool');
    $response->assertSee('/api/teacher/blueprints/discover', false);
    $response->assertSee('/api/teacher/tools/assessment-positioning/execute', false);
    $response->assertSee('Determine positioning');
});


it('renders the localized shell for supported locales', function () {
    $user = User::factory()->create();

    $expected = [
        'ar' => [
            'title' => 'أداة التموضع التقويمي',
            'execute' => 'تحديد التموضع',
        ],
        'fr' => [
            'title' => 'Outil de positionnement évaluatif',
            'execute' => 'Déterminer le positionnement',
        ],
        'en' => [
            'title' => 'Assessment Positioning Tool',
            'execute' => 'Determine positioning',
        ],
    ];

    foreach ($expected as $locale => $texts) {
        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => $locale])
            ->get('/teacher/tools/assessment-positioning');

        $response->assertSuccessful();
        $response->assertSee($texts['title']);
        $response->assertSee($texts['execute']);
    }
});

it('rejects unknown teacher tool slugs', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/teacher/tools/unknown-tool');

    $response->assertNotFound();
});

it('requires browser authentication', function () {
    $response = $this->get('/teacher/tools/assessment-positioning');

    $response->assertRedirect('/login');
});
