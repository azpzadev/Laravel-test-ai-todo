<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\TodoServiceInterface;
use App\DTOs\TodoData;
use App\Models\Todo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class TodoService implements TodoServiceInterface
{
    /**
     * Retrieve all todos, newest first.
     *
     * @return Collection<int, Todo>
     */
    public function all(): Collection
    {
        return Todo::query()->latest()->get();
    }

    /**
     * Retrieve a paginated listing of todos, newest first. When $completed is
     * provided the listing is restricted to that completion state; null lists
     * every todo.
     */
    public function paginate(int $perPage, ?bool $completed = null): LengthAwarePaginator
    {
        $todos = Todo::query()
            ->when(! is_null($completed), fn ($query) => $query->where('completed', $completed))
            ->latest()
            ->paginate($perPage);

        Log::info('todo.listed', ['count' => $todos->count(), 'completed' => $completed]);

        return $todos;
    }

    /**
     * Persist a new todo.
     */
    public function create(TodoData $data): Todo
    {
        $todo = Todo::query()->create($data->toArray());

        Log::info('todo.created', ['id' => $todo->id]);

        return $todo;
    }

    /**
     * Retrieve a single todo by its identifier.
     */
    public function find(int $id): Todo
    {
        $todo = Todo::query()->findOrFail($id);

        Log::info('todo.viewed', ['id' => $todo->id]);

        return $todo;
    }

    /**
     * Replace an existing todo with the full set of attributes carried by the
     * DTO. Used for whole-record updates where every column is provided.
     */
    public function update(Todo $todo, TodoData $data): Todo
    {
        return $this->applyChanges(todo: $todo, changes: $data->toArray());
    }

    /**
     * Apply ONLY the provided attributes to an existing todo so that absent
     * keys keep their stored values. The change-set is already validated and
     * typed by the caller — the raw request never reaches here.
     *
     * @param  array<string, mixed>  $changes
     */
    public function applyChanges(Todo $todo, array $changes): Todo
    {
        $todo->fill($changes)->save();

        Log::info('todo.updated', [
            'id' => $todo->id,
            'changed' => array_keys($todo->getChanges()),
        ]);

        $todo->refresh();

        return $todo;
    }

    /**
     * Delete an existing todo.
     */
    public function delete(Todo $todo): void
    {
        $id = $todo->id;

        $todo->delete();

        Log::info('todo.deleted', ['id' => $id]);
    }
}
