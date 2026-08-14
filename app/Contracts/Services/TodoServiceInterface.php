<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\TodoData;
use App\Models\Todo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TodoServiceInterface
{
    /**
     * Retrieve all todos, newest first.
     *
     * @return Collection<int, Todo>
     */
    public function all(): Collection;

    /**
     * Retrieve a paginated listing of todos, newest first.
     */
    public function paginate(int $perPage): LengthAwarePaginator;

    /**
     * Persist a new todo.
     */
    public function create(TodoData $data): Todo;

    /**
     * Retrieve a single todo by its identifier.
     */
    public function find(int $id): Todo;

    /**
     * Replace an existing todo with the full set of DTO attributes.
     */
    public function update(Todo $todo, TodoData $data): Todo;

    /**
     * Apply ONLY the provided attributes to an existing todo, leaving absent
     * keys untouched.
     *
     * @param  array<string, mixed>  $changes
     */
    public function applyChanges(Todo $todo, array $changes): Todo;

    /**
     * Delete an existing todo.
     */
    public function delete(Todo $todo): void;
}
