# Architecture

This project implements a single **Todo** REST resource using a strict, one-directional
layered architecture. The guiding rule is **centralized control, separated
implementation**: each layer has one responsibility, and dependencies never flow
backwards.

## Dependency flow

```
Request → Controller → Repository → Service (Interface) → Model → Database
```

- The **Controller** validates and dispatches — no business logic, no DB access.
- The **Repository** depends on the **Service Interface** (not a concrete class) via DI.
- The **Service** is the *only* layer that touches the Eloquent model and the database.
- The **Model** holds attributes, casts, and documentation only — no queries live in it.

Data crossing into the domain is always a typed **DTO**; data crossing back out to the
client is always a **Resource**. The raw `Request` never reaches the service.

## Layer responsibilities

| Layer | File | Responsibility |
|-------|------|----------------|
| Route | `routes/api.php` | `Route::apiResource('todos', TodoController::class)` — the 5 REST verbs |
| FormRequest | `app/Http/Requests/StoreTodoRequest.php`, `UpdateTodoRequest.php` | Validation rules + messages (from config) |
| DTO | `app/DTOs/TodoData.php` | Immutable, typed input; builds full payloads and partial change-sets |
| Controller | `app/Http/Controllers/TodoController.php` | Validate → dispatch to repository → return resource |
| Resource | `app/Http/Resources/TodoResource.php` | Exact JSON shape returned to the client |
| Repository | `app/Repositories/TodoRepository.php` | Depends on the service interface; no direct DB calls |
| Interface | `app/Contracts/Services/TodoServiceInterface.php` | The contract the repository binds to |
| Service | `app/Services/TodoService.php` | All CRUD + structured logging; the only Model-touching layer |
| Model | `app/Models/Todo.php` | Attributes, casts, column docs — no DB operations |
| Migration | `database/migrations/2026_08_14_000000_create_todos_table.php` | `todos` schema + `created_at` index |
| Config | `config/todo.php` | Validation messages + pagination size (no hardcoding) |
| Provider | `app/Providers/AppServiceProvider.php` | Binds `TodoServiceInterface` → `TodoService` |

## Request lifecycle

### Create (`POST /api/todos`)

1. `StoreTodoRequest` validates the body; messages come from `config('todo.messages')`.
2. `TodoData::fromRequest()` builds a typed, immutable DTO from the validated data.
3. `TodoController::store()` hands the DTO to `TodoRepository::create()`.
4. The repository forwards to `TodoServiceInterface::create()`.
5. `TodoService::create()` persists via the model, logs `todo.created`, returns the `Todo`.
6. `TodoResource` shapes the JSON response.

### Partial update (`PATCH /api/todos/{id}`)

Update is **partial-safe**. `UpdateTodoRequest` marks every field `sometimes`, and
`TodoData::changesFromRequest()` builds a change-set containing *only* the keys that were
actually present in the request. `TodoService::applyChanges()` fills just those keys, so
omitted fields keep their stored values — a `PATCH` never clobbers data it did not mention.

## Design principles enforced

- **Single source of truth for DB access.** Only `TodoService` queries the model; the
  controller and repository never call Eloquent directly.
- **Typed boundaries.** Every method declares parameter and return types; input is a DTO,
  never a raw `$request` or untyped array reaching the domain.
- **No hardcoding.** Page size and all validation messages live in `config/todo.php`.
- **Structured logging.** Every CRUD operation logs a keyed event (`todo.created`,
  `todo.listed`, `todo.viewed`, `todo.updated`, `todo.deleted`).
- **One resource per response shape.** `TodoResource` returns exactly the fields the
  client needs and nothing more.
- **Interface-driven DI.** The repository depends on `TodoServiceInterface`, bound to the
  concrete `TodoService` in `AppServiceProvider`, keeping the service swappable and testable.

## Data model

`todos` table:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | |
| `title` | string(255) | required |
| `description` | text, nullable | |
| `completed` | boolean | default `false` |
| `due_date` | date, nullable | serialized as `Y-m-d` |
| `created_at` / `updated_at` | timestamps | `created_at` is indexed (listing sorts by it) |

## Testing strategy

- **Feature tests** exercise each endpoint end-to-end, covering happy paths and
  validation failures (`tests/Feature/TodoApiTest.php`), plus regression cases for
  partial-update safety (`tests/Feature/TodoRegressionTest.php`).
- **Unit tests** cover the service's CRUD behaviour in isolation
  (`tests/Unit/TodoServiceTest.php`).
- Tests run on Pest with `RefreshDatabase` against the test database.
