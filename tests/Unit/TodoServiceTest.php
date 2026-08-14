<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\TodoData;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the TodoService CRUD operations against sqlite :memory:.
 *
 * The service is the only layer that touches the Model, so it is exercised
 * directly here rather than through the HTTP stack.
 */
final class TodoServiceTest extends TestCase
{
    use RefreshDatabase;

    private TodoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TodoService;
    }

    public function test_all_returns_every_persisted_todo(): void
    {
        Todo::factory()->count(3)->create();

        $result = $this->service->all();

        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(Todo::class, $result);
    }

    public function test_all_returns_empty_collection_when_no_todos(): void
    {
        $result = $this->service->all();

        $this->assertCount(0, $result);
    }

    public function test_create_persists_a_new_todo(): void
    {
        $data = new TodoData(
            title: 'Service created',
            description: 'Some details',
            completed: true,
            due_date: '2026-09-15',
        );

        $todo = $this->service->create($data);

        $this->assertInstanceOf(Todo::class, $todo);
        $this->assertTrue($todo->completed);
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Service created',
            'description' => 'Some details',
            'completed' => true,
        ]);

        $this->assertSame('2026-09-15', $todo->fresh()->due_date?->format('Y-m-d'));
    }

    public function test_find_returns_the_matching_todo(): void
    {
        $created = Todo::factory()->create(['title' => 'Locate me']);

        $found = $this->service->find($created->id);

        $this->assertInstanceOf(Todo::class, $found);
        $this->assertSame($created->id, $found->id);
        $this->assertSame('Locate me', $found->title);
    }

    public function test_find_throws_model_not_found_for_missing_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->find(999999);
    }

    public function test_update_persists_changes(): void
    {
        $todo = Todo::factory()->create([
            'title' => 'Before',
            'completed' => false,
        ]);

        $data = new TodoData(
            title: 'After',
            description: null,
            completed: true,
            due_date: null,
        );

        $updated = $this->service->update($todo, $data);

        $this->assertSame('After', $updated->title);
        $this->assertTrue($updated->completed);
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'After',
            'completed' => true,
        ]);
    }

    public function test_delete_removes_the_todo(): void
    {
        $todo = Todo::factory()->create();

        $this->service->delete($todo);

        $this->assertDatabaseMissing('todos', [
            'id' => $todo->id,
        ]);
    }
}
