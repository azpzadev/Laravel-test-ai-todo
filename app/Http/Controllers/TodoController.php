<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\TodoData;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Repositories\TodoRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TodoController extends Controller
{
    public function __construct(
        private readonly TodoRepository $repository,
    ) {}

    /**
     * Display a paginated listing of the current user's todos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) config('todo.pagination.per_page');

        return TodoResource::collection($this->repository->paginate(
            owner: $request->user(),
            perPage: $perPage,
        ));
    }

    /**
     * Store a newly created todo.
     */
    public function store(StoreTodoRequest $request): TodoResource
    {
        $todo = $this->repository->create(
            owner: $request->user(),
            data: TodoData::fromRequest($request),
        );

        return TodoResource::make($todo);
    }

    /**
     * Display the specified todo.
     */
    public function show(Todo $todo): TodoResource
    {
        Gate::authorize('view', $todo);

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
        Gate::authorize('delete', $todo);

        $this->repository->delete(todo: $todo);

        return response()->noContent();
    }
}
