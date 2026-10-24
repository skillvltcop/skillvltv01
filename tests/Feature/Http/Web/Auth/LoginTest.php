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


it('regenerates the session identifier after successful authentication', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $this->get('/login')->assertOk();
    $sessionIdBeforeLogin = session()->getId();

    $response = $this->post('/login', [
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionIdBeforeLogin);
});

it('does not persist the login unless remember me is selected', function () {
    User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response = $this->post('/login', [
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $response->assertCookieMissing(auth()->guard('web')->getRecallerName());
});

it('persists the login when remember me is selected', function () {
    User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response = $this->post('/login', [
        'email' => 'teacher@example.com',
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect('/');
    $response->assertCookie(auth()->guard('web')->getRecallerName());
});

it('rate limits repeated browser login attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->from('/login')->post('/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');
    }

    $this->post('/login', [
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
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
                'max_score' => 100,
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

it('validates required browser login fields', function () {
    $response = $this
        ->from('/login')
        ->post('/login', []);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['email', 'password']);
    $this->assertGuest();
});

it('logs out the browser session and clears its session data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'fr'])
        ->get('/');

    $sessionIdBeforeLogout = session()->getId();
    $csrfTokenBeforeLogout = session()->token();

    $response = $this->post('/logout');

    $response->assertRedirect('/login');
    $response->assertSessionMissing('locale');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionIdBeforeLogout);
    expect(session()->token())->not->toBe($csrfTokenBeforeLogout);
});

it('requires browser authentication to log out', function () {
    $response = $this
        ->from('/teacher/tools')
        ->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

it('redirects authenticated users away from the login screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/');
});

it('allows guests to change the interface language on the login screen', function () {
    $this->get('/login')->assertOk();

    $this->from('/login')
        ->post('/locale', ['locale' => 'fr'])
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'fr');

    $this->get('/login')->assertSee('Connexion | SkillVLT');
});
