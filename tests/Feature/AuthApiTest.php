<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

// -------------------------------------------------------------------------
// POST /api/register
// -------------------------------------------------------------------------

it('registers a user and returns a bearer token', function (): void {
    $response = $this->postJson('/api/register', [
        'name' => 'Ko Pyae',
        'email' => 'kopyae@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'token',
            'token_type',
        ])
        ->assertJsonPath('data.email', 'kopyae@example.com')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('email', 'kopyae@example.com')->firstOrFail();

    expect(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

it('returns a token that authenticates later requests', function (): void {
    $token = $this->postJson('/api/register', [
        'name' => 'Token User',
        'email' => 'token@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'token@example.com');
});

it('rejects invalid registration input with 422', function (array $payload, string $field): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(User::query()->count())->toBe(1);
})->with([
    'missing name' => [['email' => 'a@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'], 'name'],
    'invalid email' => [['name' => 'A', 'email' => 'not-an-email', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'], 'email'],
    'duplicate email' => [['name' => 'A', 'email' => 'taken@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'], 'email'],
    'password not confirmed' => [['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password', 'password_confirmation' => 'different'], 'password'],
    'password too short' => [['name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);

// -------------------------------------------------------------------------
// POST /api/login
// -------------------------------------------------------------------------

it('logs in with valid credentials and issues a token', function (): void {
    $user = User::factory()->create(['email' => 'login@example.com']);

    $response = $this->postJson('/api/login', [
        'email' => 'login@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('token_type', 'Bearer');

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->tokens()->count())->toBe(1);
});

it('rejects wrong credentials with 422 on email and issues no token', function (string $email, string $password): void {
    User::factory()->create(['email' => 'login@example.com']);

    $this->postJson('/api/login', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'wrong password' => ['login@example.com', 'wrong-password'],
    'unknown email' => ['nobody@example.com', 'password'],
]);

it('rejects missing login fields with 422', function (string $field): void {
    $payload = ['email' => 'login@example.com', 'password' => 'password'];
    unset($payload[$field]);

    $this->postJson('/api/login', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with(['email', 'password']);

// -------------------------------------------------------------------------
// POST /api/logout
// -------------------------------------------------------------------------

it('revokes only the current token on logout', function (): void {
    $user = User::factory()->create();
    $currentToken = $user->createToken('api')->plainTextToken;
    $user->createToken('other-device');

    $this->withToken($currentToken)
        ->postJson('/api/logout')
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['other-device']);

    // Forget the cached guard user so the next request re-reads the token.
    $this->app['auth']->forgetGuards();

    $this->withToken($currentToken)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('returns 401 on logout without a token', function (): void {
    $this->postJson('/api/logout')->assertUnauthorized();
});

// -------------------------------------------------------------------------
// GET /api/user
// -------------------------------------------------------------------------

it('returns the authenticated user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

it('returns 401 for the current user without a token', function (): void {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns 401 for an invalid token', function (): void {
    $this->withToken('1|not-a-real-token')
        ->getJson('/api/user')
        ->assertUnauthorized();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});
