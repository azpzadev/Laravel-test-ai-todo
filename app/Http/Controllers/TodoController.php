<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\TodoData;
use App\Http\Requests\IndexTodoRequest;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Repositories\TodoRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class TodoController extends Controller
{
    public function __construct(
        private readonly TodoRepository $repository,
    ) {}

    /**
     * Display a paginated listing of the todos.
     */
    public function index(IndexTodoRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) config('todo.pagination.per_page');

        return TodoResource::collection(
            $this->repository->paginate(
                perPage: $perPage,
                completed: $request->completedFilter(),
            ),
        );
    }

    /**
     * Store a newly created todo.
     */
    public function store(StoreTodoRequest $request): TodoResource
    {
        $todo = $this->repository->create(data: TodoData::fromRequest($request));

        return TodoResource::make($todo);
    }

    /**
     * Display the specified todo.
     */
    public function show(Todo $todo): TodoResource
    {
        return TodoResource::make($todo);
    }

    /**
     * Update the specified todo.
     */
    public function update(UpdateTodoRequest $request, Todo $todo): TodoResource
    {
        $updated = $this->repository->applyChanges(
            todo: $todo,
            changes: TodoData::changesFromRequest($request),
        );

        return TodoResource::make($updated);
    }

    /**
     * Remove the specified todo.
     */
    public function destroy(Todo $todo): Response
    {
        $this->repository->delete(todo: $todo);

        return response()->noContent();
    }
}
