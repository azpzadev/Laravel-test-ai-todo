<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Regression coverage for the just-fixed Todo CRUD behaviour:
 *  - partial PATCH preserves unsent fields (description, due_date)
 *  - explicit `description: null` still sets null (absent vs present-null)
 *  - description max:5000 boundary on store and update
 *  - paginated index envelope + configured page size
 *  - structured CRUD audit-trail logging (todo.created / todo.deleted)
 *
 * Style mirrors tests/Feature/TodoApiTest.php (PHPUnit class-based,
 * RefreshDatabase, sqlite :memory:).
 */
final class TodoRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Partial PATCH preserves unsent fields
    // ---------------------------------------------------------------------

    public function test_patch_with_only_title_preserves_existing_description(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Original title',
            'description' => 'Keep me intact',
        ]);

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Changed title only',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Changed title only');
        $response->assertJsonPath('data.description', 'Keep me intact');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Changed title only',
            'description' => 'Keep me intact',
        ]);
    }

    public function test_patch_with_only_title_preserves_existing_due_date(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Original title',
            'due_date' => '2026-10-20',
        ]);

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Only the title moved',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Only the title moved');
        $response->assertJsonPath('data.due_date', '2026-10-20');

        $stored = $todo->fresh();
        $this->assertSame('2026-10-20', $stored->due_date?->format('Y-m-d'));
    }

    public function test_patch_with_explicit_null_description_sets_it_to_null(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Has a description',
            'description' => 'This should be cleared',
        ]);

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'description' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', null);
        // Title must remain untouched by the null-description PATCH.
        $response->assertJsonPath('data.title', 'Has a description');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Has a description',
            'description' => null,
        ]);
    }

    // ---------------------------------------------------------------------
    // description max:5000 boundary — store
    // ---------------------------------------------------------------------

    public function test_store_accepts_description_at_5000_char_boundary(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Max length description',
            'description' => str_repeat('a', 5000),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('todos', [
            'title' => 'Max length description',
        ]);
    }

    public function test_store_rejects_description_over_5000_chars_with_422(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Too long description',
            'description' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    // ---------------------------------------------------------------------
    // description max:5000 boundary — update
    // ---------------------------------------------------------------------

    public function test_update_accepts_description_at_5000_char_boundary(): void
    {
        $todo = Todo::factory()->create();

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'description' => str_repeat('b', 5000),
        ]);

        $response->assertOk();
        $this->assertSame(5000, strlen((string) $todo->fresh()->description));
    }

    public function test_update_rejects_description_over_5000_chars_with_422(): void
    {
        $todo = Todo::factory()->create();

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'description' => str_repeat('b', 5001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    // ---------------------------------------------------------------------
    // Pagination envelope
    // ---------------------------------------------------------------------

    public function test_index_returns_paginated_envelope_with_configured_per_page(): void
    {
        Todo::factory()->count(3)->create();

        $response = $this->getJson('/api/todos');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

        $expectedPerPage = (int) config('todo.pagination.per_page');
        $this->assertSame(15, $expectedPerPage);
        $response->assertJsonPath('meta.per_page', $expectedPerPage);
    }

    public function test_index_paginates_across_pages_when_over_per_page(): void
    {
        $perPage = (int) config('todo.pagination.per_page');
        $total = $perPage + 5;

        Todo::factory()->count($total)->create();

        $firstPage = $this->getJson('/api/todos');
        $firstPage->assertOk();
        $firstPage->assertJsonCount($perPage, 'data');
        $firstPage->assertJsonPath('meta.total', $total);
        $firstPage->assertJsonPath('meta.current_page', 1);

        $secondPage = $this->getJson('/api/todos?page=2');
        $secondPage->assertOk();
        $secondPage->assertJsonCount($total - $perPage, 'data');
        $secondPage->assertJsonPath('meta.current_page', 2);
    }

    // ---------------------------------------------------------------------
    // Structured CRUD audit-trail logging
    // ---------------------------------------------------------------------

    public function test_store_emits_todo_created_audit_log(): void
    {
        Log::spy();

        $response = $this->postJson('/api/todos', [
            'title' => 'Audit me',
        ]);

        $response->assertCreated();
        $id = $response->json('data.id');

        Log::shouldHaveReceived('info')
            ->once()
            ->with('todo.created', ['id' => $id]);
    }

    public function test_destroy_emits_todo_deleted_audit_log(): void
    {
        $todo = Todo::factory()->create();

        Log::spy();

        $response = $this->deleteJson("/api/todos/{$todo->id}");

        $response->assertNoContent();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('todo.deleted', ['id' => $todo->id]);
    }
}
