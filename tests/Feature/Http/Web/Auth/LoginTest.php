<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('authenticates a browser session', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response = $this->post('/login', [
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid browser credentials', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('uses the browser session to access the teacher discovery api', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    (new AssessmentPositioningBlueprintSeeder())->run();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this
        ->withSession(['locale' => 'en'])
        ->withHeader('Origin', 'http://localhost')
        ->getJson('/api/teacher/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJsonPath(
        'data.0.title',
        'Assessment Positioning',
    );
});

it('uses the browser session to execute the teacher blueprint', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    (new AssessmentPositioningBlueprintSeeder())->run();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->postJson(
        '/api/teacher/tools/assessment-positioning/execute',
        [
            'input' => [
                'score' => 59,
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.positioning', 'needs_support');
    $response->assertJsonMissingPath('blueprint_id');
    $response->assertJsonMissingPath('revision_id');
});
