<?php

declare(strict_types=1);

use App\Models\Todo;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

// -------------------------------------------------------------------------
// 401 — every todo route needs auth:sanctum
// -------------------------------------------------------------------------

it('returns 401 when unauthenticated', function (string $method, string $uri): void {
    $todo = Todo::factory()->for($this->owner)->create(['title' => 'Untouched']);

    $this->json($method, str_replace('{id}', (string) $todo->id, $uri), ['title' => 'Changed'])
        ->assertUnauthorized();

    $this->assertDatabaseHas('todos', ['id' => $todo->id, 'title' => 'Untouched']);
    expect(Todo::query()->count())->toBe(1);
})->with([
    'index' => ['GET', '/api/todos'],
    'store' => ['POST', '/api/todos'],
    'show' => ['GET', '/api/todos/{id}'],
    'update' => ['PUT', '/api/todos/{id}'],
    'patch' => ['PATCH', '/api/todos/{id}'],
    'destroy' => ['DELETE', '/api/todos/{id}'],
]);

// -------------------------------------------------------------------------
// index — scoped to the current user, newest first, paginated
// -------------------------------------------------------------------------

it('lists only the current user\'s todos', function (): void {
    $ownTodos = Todo::factory()->for($this->owner)->count(2)->create();
    Todo::factory()->for($this->otherUser)->count(3)->create();

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/todos')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe($ownTodos->pluck('id')->sort()->values()->all());
});

it('shows each user only their own todos', function (): void {
    Todo::factory()->for($this->owner)->create(['title' => 'Owner todo']);
    Todo::factory()->for($this->otherUser)->create(['title' => 'Other todo']);

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/todos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Owner todo');

    Sanctum::actingAs($this->otherUser);
    $this->getJson('/api/todos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Other todo');
});

it('lists todos newest first', function (): void {
    Todo::factory()->for($this->owner)->create(['title' => 'Oldest', 'created_at' => now()->subDays(2)]);
    Todo::factory()->for($this->owner)->create(['title' => 'Newest', 'created_at' => now()]);
    Todo::factory()->for($this->owner)->create(['title' => 'Middle', 'created_at' => now()->subDay()]);

    Sanctum::actingAs($this->owner);

    $titles = collect($this->getJson('/api/todos')->assertOk()->json('data'))->pluck('title')->all();

    expect($titles)->toBe(['Newest', 'Middle', 'Oldest']);
});

it('paginates the current user\'s todos', function (): void {
    $perPage = (int) config('todo.pagination.per_page');
    Todo::factory()->for($this->owner)->count($perPage + 2)->create();
    Todo::factory()->for($this->otherUser)->count(4)->create();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/todos?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', $perPage + 2)
        ->assertJsonPath('meta.current_page', 2);
});

// -------------------------------------------------------------------------
// store — owned by the current user
// -------------------------------------------------------------------------

it('creates a todo owned by the current user', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/todos', [
        'title' => 'My new todo',
        'description' => 'Details',
        'due_date' => '2026-12-31',
    ])->assertCreated();

    $this->assertDatabaseHas('todos', [
        'id' => $response->json('data.id'),
        'user_id' => $this->owner->id,
        'title' => 'My new todo',
        'description' => 'Details',
        'completed' => false,
    ]);
});

it('ignores a user_id in the payload when creating', function (): void {
    Sanctum::actingAs($this->owner);

    $id = $this->postJson('/api/todos', [
        'title' => 'Sneaky',
        'user_id' => $this->otherUser->id,
    ])->assertCreated()->json('data.id');

    $this->assertDatabaseHas('todos', ['id' => $id, 'user_id' => $this->owner->id]);
    expect($this->otherUser->todos()->count())->toBe(0);
});

it('returns 422 with the field on invalid store input', function (array $payload, string $field): void {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/todos', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    $this->assertDatabaseCount('todos', 0);
})->with([
    'missing title' => [['description' => 'no title'], 'title'],
    'title too long' => [['title' => str_repeat('a', 256)], 'title'],
    'bad due date' => [['title' => 'x', 'due_date' => 'not-a-date'], 'due_date'],
    'bad completed' => [['title' => 'x', 'completed' => 'maybe'], 'completed'],
]);

// -------------------------------------------------------------------------
// show
// -------------------------------------------------------------------------

it('shows the owner their todo', function (): void {
    $todo = Todo::factory()->for($this->owner)->create(['title' => 'Mine']);
    Sanctum::actingAs($this->owner);

    $this->getJson("/api/todos/{$todo->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $todo->id)
        ->assertJsonPath('data.title', 'Mine');
});

it('returns 403 when viewing another user\'s todo', function (): void {
    $todo = Todo::factory()->for($this->otherUser)->create();
    Sanctum::actingAs($this->owner);

    $this->getJson("/api/todos/{$todo->id}")->assertForbidden();
});

// -------------------------------------------------------------------------
// update
// -------------------------------------------------------------------------

it('updates the owner\'s todo', function (): void {
    $todo = Todo::factory()->for($this->owner)->create(['title' => 'Before', 'completed' => false]);
    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/todos/{$todo->id}", ['title' => 'After', 'completed' => true])
        ->assertOk()
        ->assertJsonPath('data.title', 'After')
        ->assertJsonPath('data.completed', true);

    $this->assertDatabaseHas('todos', [
        'id' => $todo->id,
        'user_id' => $this->owner->id,
        'title' => 'After',
        'completed' => true,
    ]);
});

it('returns 403 when updating another user\'s todo and leaves it unchanged', function (string $method): void {
    $todo = Todo::factory()->for($this->otherUser)->create(['title' => 'Not yours']);
    Sanctum::actingAs($this->owner);

    $this->json($method, "/api/todos/{$todo->id}", ['title' => 'Hijacked'])
        ->assertForbidden();

    $this->assertDatabaseHas('todos', [
        'id' => $todo->id,
        'user_id' => $this->otherUser->id,
        'title' => 'Not yours',
    ]);
})->with(['PUT', 'PATCH']);

it('returns 403 before validation for another user\'s todo', function (): void {
    $todo = Todo::factory()->for($this->otherUser)->create();
    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/todos/{$todo->id}", ['title' => str_repeat('a', 256)])
        ->assertForbidden();
});

it('cannot move a todo to another user via user_id', function (): void {
    $todo = Todo::factory()->for($this->owner)->create();
    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/todos/{$todo->id}", ['title' => 'Still mine', 'user_id' => $this->otherUser->id])
        ->assertOk();

    $this->assertDatabaseHas('todos', ['id' => $todo->id, 'user_id' => $this->owner->id, 'title' => 'Still mine']);
});

it('returns 422 with the field on invalid update input', function (array $payload, string $field): void {
    $todo = Todo::factory()->for($this->owner)->create(['title' => 'Original']);
    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/todos/{$todo->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    $this->assertDatabaseHas('todos', ['id' => $todo->id, 'title' => 'Original']);
})->with([
    'empty title' => [['title' => ''], 'title'],
    'title too long' => [['title' => str_repeat('a', 256)], 'title'],
    'bad due date' => [['due_date' => 'not-a-date'], 'due_date'],
]);

// -------------------------------------------------------------------------
// destroy
// -------------------------------------------------------------------------

it('deletes the owner\'s todo', function (): void {
    $todo = Todo::factory()->for($this->owner)->create();
    $keep = Todo::factory()->for($this->owner)->create();
    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/todos/{$todo->id}")->assertNoContent();

    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    $this->assertDatabaseHas('todos', ['id' => $keep->id]);
});

it('returns 403 when deleting another user\'s todo and keeps it', function (): void {
    $todo = Todo::factory()->for($this->otherUser)->create();
    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/todos/{$todo->id}")->assertForbidden();

    $this->assertDatabaseHas('todos', ['id' => $todo->id, 'user_id' => $this->otherUser->id]);
});

// -------------------------------------------------------------------------
// 404 — missing todo
// -------------------------------------------------------------------------

it('returns 404 for a missing todo', function (string $method): void {
    Sanctum::actingAs($this->owner);

    $this->json($method, '/api/todos/999999', ['title' => 'Nope'])->assertNotFound();
})->with(['GET', 'PUT', 'PATCH', 'DELETE']);

// -------------------------------------------------------------------------
// Relationships and cascade
// -------------------------------------------------------------------------

it('links todos and users both ways', function (): void {
    $todo = Todo::factory()->for($this->owner)->create();

    expect($todo->user->is($this->owner))->toBeTrue()
        ->and($this->owner->todos()->pluck('id')->all())->toBe([$todo->id]);
});

it('deletes a user\'s todos when the user is deleted', function (): void {
    Todo::factory()->for($this->owner)->count(2)->create();
    $otherTodo = Todo::factory()->for($this->otherUser)->create();

    $this->owner->delete();

    $this->assertDatabaseMissing('todos', ['user_id' => $this->owner->id]);
    $this->assertDatabaseHas('todos', ['id' => $otherTodo->id]);
});
