<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TodoApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_200_with_all_todos_in_correct_structure(): void
    {
        Todo::factory()->count(3)->create();

        $response = $this->getJson('/api/todos');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'description',
                    'completed',
                    'due_date',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_index_returns_empty_data_when_no_todos_exist(): void
    {
        $response = $this->getJson('/api/todos');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_todo_and_returns_201_with_correct_shape(): void
    {
        $payload = [
            'title' => 'Buy groceries',
            'description' => 'Milk, eggs, bread',
            'completed' => true,
            'due_date' => '2026-09-01',
        ];

        $response = $this->postJson('/api/todos', $payload);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'title' => 'Buy groceries',
                'description' => 'Milk, eggs, bread',
                'completed' => true,
                'due_date' => '2026-09-01',
            ],
        ]);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'completed',
                'due_date',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertDatabaseHas('todos', [
            'title' => 'Buy groceries',
            'description' => 'Milk, eggs, bread',
            'completed' => true,
        ]);

        $stored = Todo::query()->where('title', 'Buy groceries')->firstOrFail();
        $this->assertTrue($stored->completed);
        $this->assertSame('2026-09-01', $stored->due_date?->format('Y-m-d'));
    }

    public function test_store_defaults_completed_to_false_when_omitted(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'No completed flag',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.completed', false);

        $this->assertDatabaseHas('todos', [
            'title' => 'No completed flag',
            'completed' => false,
        ]);
    }

    public function test_store_casts_due_date_to_ymd_format(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Date cast check',
            'due_date' => '2026-12-25',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.due_date', '2026-12-25');
    }

    public function test_store_allows_null_description_and_null_due_date(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Minimal todo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.description', null);
        $response->assertJsonPath('data.due_date', null);
    }

    // ---------------------------------------------------------------------
    // store validation
    // ---------------------------------------------------------------------

    public function test_store_fails_with_422_when_title_missing(): void
    {
        $response = $this->postJson('/api/todos', [
            'description' => 'no title here',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_store_fails_with_422_when_title_too_long(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_store_fails_with_422_when_due_date_not_a_date(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Bad date',
            'due_date' => 'not-a-date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['due_date']);
    }

    public function test_store_fails_with_422_when_completed_not_boolean(): void
    {
        $response = $this->postJson('/api/todos', [
            'title' => 'Bad completed',
            'completed' => 'maybe',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['completed']);
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_200_with_correct_todo(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Findable',
            'due_date' => '2026-10-10',
        ]);

        $response = $this->getJson("/api/todos/{$todo->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $todo->id);
        $response->assertJsonPath('data.title', 'Findable');
        $response->assertJsonPath('data.due_date', '2026-10-10');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/todos/999999');

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_persists_full_changes_and_returns_200(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Old title',
            'completed' => false,
        ]);

        $response = $this->putJson("/api/todos/{$todo->id}", [
            'title' => 'New title',
            'description' => 'New description',
            'completed' => true,
            'due_date' => '2026-11-11',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
        $response->assertJsonPath('data.completed', true);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'New title',
            'description' => 'New description',
            'completed' => true,
        ]);

        $stored = $todo->fresh();
        $this->assertTrue($stored->completed);
        $this->assertSame('2026-11-11', $stored->due_date?->format('Y-m-d'));
    }

    public function test_update_persists_partial_change_via_patch(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Original',
        ]);

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Patched title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Patched title');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Patched title',
        ]);
    }

    public function test_update_returns_404_for_unknown_id(): void
    {
        $response = $this->putJson('/api/todos/999999', [
            'title' => 'Nope',
        ]);

        $response->assertNotFound();
    }

    public function test_update_fails_with_422_when_title_too_long(): void
    {
        $todo = Todo::factory()->create();

        $response = $this->putJson("/api/todos/{$todo->id}", [
            'title' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_update_fails_with_422_when_title_present_but_empty(): void
    {
        $todo = Todo::factory()->create();

        $response = $this->putJson("/api/todos/{$todo->id}", [
            'title' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    /**
     * Known concern from the developer: the DTO's `completed` is a non-nullable
     * bool defaulting to false, and update() always writes the full toArray().
     * A PATCH that omits `completed` must NOT silently reset a stored
     * completed=true back to false. This test asserts the CORRECT behaviour
     * (preservation). If it fails, it is a legitimate app finding to report.
     */
    public function test_patch_with_only_title_preserves_existing_completed_true(): void
    {
        $todo = Todo::factory()->completed()->create([
            'title' => 'Was completed',
        ]);

        $this->assertTrue($todo->fresh()->completed);

        $response = $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Renamed but still done',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.completed', true);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Renamed but still done',
            'completed' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_returns_204_and_removes_row(): void
    {
        $todo = Todo::factory()->create();

        $response = $this->deleteJson("/api/todos/{$todo->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('todos', [
            'id' => $todo->id,
        ]);
    }

    public function test_destroy_returns_404_for_unknown_id(): void
    {
        $response = $this->deleteJson('/api/todos/999999');

        $response->assertNotFound();
    }
}
