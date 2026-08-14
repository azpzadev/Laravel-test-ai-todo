# Todo API

A small, strictly-layered **Todo REST API** built on Laravel 13 (PHP 8.3). It exposes
full CRUD over a `todos` resource and is built to demonstrate a clean, testable
architecture: validation, typed DTOs, a service interface behind a repository, and
JSON resources — with no business logic in the controller and no database access
outside the service layer.

> Architecture details live in [`docs/architecture.md`](docs/architecture.md).

---

## Features

- Full CRUD for todos (`title`, `description`, `completed`, `due_date`).
- Partial updates (`PATCH`) that never clobber absent fields.
- Paginated listing (newest first), page size driven by config — never hardcoded.
- Centralised, translatable validation messages (`config/todo.php`).
- Structured logging on every create / read / update / delete.
- Feature, regression, and unit test coverage (Pest).

---

## Requirements

- PHP `^8.3`
- Composer
- A database (SQLite by default for local/testing)

## Getting Started

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite example)
touch database/database.sqlite   # then set DB_CONNECTION=sqlite in .env
php artisan migrate

# 4. Serve
php artisan serve
```

The API is then available under `http://localhost:8000/api`.

---

## API Reference

Base path: `/api` · Resource: `todos` · Media type: `application/json`

| Method      | URI                | Action  | Description                         |
|-------------|--------------------|---------|-------------------------------------|
| `GET`       | `/api/todos`       | index   | Paginated list, newest first        |
| `POST`      | `/api/todos`       | store   | Create a todo                       |
| `GET`       | `/api/todos/{id}`  | show    | Fetch a single todo                 |
| `PUT/PATCH` | `/api/todos/{id}`  | update  | Update a todo (partial-safe)        |
| `DELETE`    | `/api/todos/{id}`  | destroy | Delete a todo (`204 No Content`)    |

### Fields & validation

| Field         | Type            | Rules (store)                    | Notes                                |
|---------------|-----------------|----------------------------------|--------------------------------------|
| `title`       | string          | required, string, max:255        | Short task label                     |
| `description` | string \| null  | nullable, string, max:5000       | Optional long-form details           |
| `completed`   | boolean         | sometimes, boolean               | Defaults to `false`                  |
| `due_date`    | date \| null    | nullable, date (`Y-m-d`)         | Optional due date                    |

On update, every rule additionally accepts `sometimes` so a `PATCH` can send only the
fields it wants to change; omitted fields keep their stored values.

### Example — create

```bash
curl -X POST http://localhost:8000/api/todos \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"Write docs","description":"Todo README","due_date":"2026-09-01"}'
```

```json
{
  "data": {
    "id": 1,
    "title": "Write docs",
    "description": "Todo README",
    "completed": false,
    "due_date": "2026-09-01",
    "created_at": "2026-08-14T00:00:00.000000Z",
    "updated_at": "2026-08-14T00:00:00.000000Z"
  }
}
```

The `index` endpoint wraps the same shape in a paginated envelope (`data`, `links`,
`meta`), with page size taken from `config('todo.pagination.per_page')` (default `15`).

---

## Architecture (at a glance)

The request flows in one direction only — the controller never touches the database,
and only the service touches the model:

```
Request → Controller → Repository → Service (Interface) → Model
             │             │              │                  │
        validate &     delegate to     all DB / CRUD +    attributes,
         dispatch      the interface   structured logs    casts, docs
```

- **FormRequest** — validates input; messages come from `config/todo.php`.
- **DTO (`TodoData`)** — typed, immutable carrier; also derives partial-update change-sets.
- **Controller** — validation + dispatch only; returns `TodoResource`.
- **Repository** — depends on `TodoServiceInterface` via DI; no direct DB calls.
- **Service** — the only layer that touches the `Todo` model; owns all CRUD + logging.
- **Resource** — shapes the exact JSON the client receives.

See [`docs/architecture.md`](docs/architecture.md) for the full breakdown, file map,
and the reasoning behind each layer.

---

## Configuration

`config/todo.php` centralises everything that would otherwise be hardcoded:

- `todo.messages` — validation messages for the form requests.
- `todo.pagination.per_page` — listing page size.

---

## Testing

```bash
php artisan test            # full suite (Pest)
php artisan test --filter Todo
```

| Suite | File | Covers |
|-------|------|--------|
| Feature | `tests/Feature/TodoApiTest.php` | Each endpoint, happy path + validation failures |
| Feature | `tests/Feature/TodoRegressionTest.php` | Partial-update safety and edge cases |
| Unit | `tests/Unit/TodoServiceTest.php` | Service CRUD behaviour |

---

## Code Style

Formatted with [Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint --dirty
```

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
