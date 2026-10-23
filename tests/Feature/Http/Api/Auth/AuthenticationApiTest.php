<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('logs in a user through the HTTP API', function () {
    $user = User::factory()->create([
        'name' => 'Zakaria',
        'email' => 'zakaria@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'zakaria@example.com',
        'password' => 'password',
    ]);

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'token',
        'user' => [
            'id',
            'name',
            'email',
        ],
    ]);

    expect($response->json('token'))
        ->not
        ->toBeNull();

    $response->assertJsonPath(
        'user.id',
        $user->id,
    );

    $response->assertJsonPath(
        'user.name',
        'Zakaria',
    );

    $response->assertJsonPath(
        'user.email',
        'zakaria@example.com',
    );
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'zakaria@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'zakaria@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized();

    $response->assertJson([
        'message' => 'Invalid credentials.',
    ]);
});

it('rate limits repeated API login attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

it('validates required login fields', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'email',
        'password',
    ]);
});

it('returns the authenticated user through the HTTP API', function () {
    $user = User::factory()->create([
        'name' => 'Zakaria',
        'email' => 'zakaria@example.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/me');

    $response->assertSuccessful();

    $response->assertJson([
        'id' => $user->id,
        'name' => 'Zakaria',
        'email' => 'zakaria@example.com',
    ]);
});

it('rejects unauthenticated access to the authenticated user endpoint', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertUnauthorized();
});

it('logs out the authenticated user and revokes the current token', function () {
    $user = User::factory()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    $this->assertDatabaseCount('personal_access_tokens', 1);

    $response = $this
        ->withToken($token)
        ->postJson('/api/auth/logout');

    $response->assertSuccessful();

    $response->assertJson([
        'message' => 'Logged out successfully.',
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects unauthenticated logout requests', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertUnauthorized();
});

it('logs out a stateful browser session through the API logout endpoint', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/');

    $this->withHeader('Origin', 'http://localhost')
        ->postJson('/api/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Logged out successfully.');

    $this->withHeader('Origin', 'http://localhost')
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});
