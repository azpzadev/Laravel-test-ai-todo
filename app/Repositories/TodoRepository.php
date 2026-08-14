<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Services\TodoServiceInterface;
use App\DTOs\TodoData;
use App\Models\Todo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class TodoRepository
{
    public function __construct(
        private TodoServiceInterface $service,
    ) {}

    /**
     * Retrieve all todos, newest first.
     *
     * @return Collection<int, Todo>
     */
    public function all(): Collection
    {
        return $this->service->all();
    }

    /**
     * Retrieve a paginated listing of todos, newest first.
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->service->paginate(perPage: $perPage);
    }

    /**
     * Persist a new todo.
     */
    public function create(TodoData $data): Todo
    {
        return $this->service->create(data: $data);
    }

    /**
     * Retrieve a single todo by its identifier.
     */
    public function find(int $id): Todo
    {
        return $this->service->find(id: $id);
    }

    /**
     * Replace an existing todo with the full set of DTO attributes.
     */
    public function update(Todo $todo, TodoData $data): Todo
    {
        return $this->service->update(todo: $todo, data: $data);
    }

    /**
     * Apply ONLY the provided attributes to an existing todo, leaving absent
     * keys untouched.
     *
     * @param  array<string, mixed>  $changes
     */
    public function applyChanges(Todo $todo, array $changes): Todo
    {
        return $this->service->applyChanges(todo: $todo, changes: $changes);
    }

    /**
     * Delete an existing todo.
     */
    public function delete(Todo $todo): void
    {
        $this->service->delete(todo: $todo);
    }
}
