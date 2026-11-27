<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\TodoChatServiceInterface;
use App\Contracts\Services\TodoServiceInterface;
use App\DTOs\TodoChatData;
use App\DTOs\TodoChatReplyData;
use App\DTOs\TodoData;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lets an OpenAI-compatible chat model manage the owner's todos through tool
 * calls. Every tool is scoped to the owner, validated with the same rules as
 * the REST endpoints, and executed through TodoService.
 */
final readonly class TodoChatService implements TodoChatServiceInterface
{
    public function __construct(
        private TodoServiceInterface $todos,
    ) {}

    /**
     * Answer the owner's chat message, running tool calls until the model
     * gives a final text reply or the round limit is reached.
     */
    public function reply(User $owner, TodoChatData $data): TodoChatReplyData
    {
        $this->ensureConfigured();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $data->message],
        ];

        $actions = [];
        $maxToolRounds = (int) config('todo.chat.max_tool_rounds');

        for ($round = 0; $round < $maxToolRounds; $round++) {
            $assistantMessage = $this->requestCompletion(messages: $messages);

            /** @var list<array<string, mixed>> $toolCalls */
            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if ($toolCalls === []) {
                return new TodoChatReplyData(
                    reply: trim((string) ($assistantMessage['content'] ?? '')),
                    actions: $actions,
                );
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $toolName = (string) ($toolCall['function']['name'] ?? '');
                $result = $this->runTool(
                    owner: $owner,
                    toolName: $toolName,
                    rawArguments: $toolCall['function']['arguments'] ?? null,
                );

                $actions[] = [
                    'tool' => $toolName,
                    'status' => $result['ok'] ? 'ok' : 'error',
                    'todo_id' => $result['todo']['id'] ?? null,
                ];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => (string) json_encode($result),
                ];
            }
        }

        Log::warning('todo.chat.round_limit_reached', ['user_id' => $owner->id]);

        return new TodoChatReplyData(
            reply: 'Sorry, I could not finish that request. Please try again with a simpler message.',
            actions: $actions,
        );
    }

    /**
     * Fail fast with 503 when the AI provider has not been configured.
     */
    private function ensureConfigured(): void
    {
        $isConfigured = filled(config('services.openai_compatible.url'))
            && filled(config('services.openai_compatible.key'))
            && filled(config('services.openai_compatible.model'));

        if (! $isConfigured) {
            throw new HttpException(503, 'The AI assistant is not configured.');
        }
    }

    /**
     * Send the conversation to the chat completions API and return the
     * assistant message of the first choice.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function requestCompletion(array $messages): array
    {
        try {
            $response = Http::baseUrl((string) config('services.openai_compatible.url'))
                ->withToken((string) config('services.openai_compatible.key'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout((int) config('services.openai_compatible.timeout'))
                ->post('chat/completions', [
                    'model' => (string) config('services.openai_compatible.model'),
                    'messages' => $messages,
                    'tools' => $this->toolDefinitions(),
                    'tool_choice' => 'auto',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('todo.chat.provider_failed', [
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            throw new HttpException(502, 'The AI assistant is unavailable right now.');
        }

        $assistantMessage = $response->json('choices.0.message');

        if (! is_array($assistantMessage)) {
            Log::warning('todo.chat.invalid_provider_response');

            throw new HttpException(502, 'The AI assistant is unavailable right now.');
        }

        return $assistantMessage;
    }

    /**
     * Execute one tool call for the owner and return a JSON-serialisable
     * result the model can read.
     *
     * @return array<string, mixed>
     */
    private function runTool(User $owner, string $toolName, mixed $rawArguments): array
    {
        $arguments = is_array($rawArguments)
            ? $rawArguments
            : json_decode((string) $rawArguments, true);

        if (! is_array($arguments)) {
            return ['ok' => false, 'error' => 'The tool arguments must be a JSON object.'];
        }

        $result = match ($toolName) {
            'list_todos' => $this->listTodos(owner: $owner),
            'create_todo' => $this->createTodo(owner: $owner, arguments: $arguments),
            'update_todo' => $this->updateTodo(owner: $owner, arguments: $arguments),
            'delete_todo' => $this->deleteTodo(owner: $owner, arguments: $arguments),
            default => ['ok' => false, 'error' => "Unknown tool [{$toolName}]."],
        };

        Log::info('todo.chat.tool_called', [
            'user_id' => $owner->id,
            'tool' => $toolName,
            'ok' => $result['ok'],
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function listTodos(User $owner): array
    {
        $todos = $this->todos->paginate(
            owner: $owner,
            perPage: (int) config('todo.chat.list_limit'),
        );

        return [
            'ok' => true,
            'total' => $todos->total(),
            'todos' => collect($todos->items())
                ->map(fn (Todo $todo): array => $this->presentTodo(todo: $todo))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createTodo(User $owner, array $arguments): array
    {
        $validator = Validator::make(
            $arguments,
            (new StoreTodoRequest)->rules(),
            config('todo.messages'),
        );

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        $todo = $this->todos->create(
            owner: $owner,
            data: TodoData::fromValidated($validator->validated()),
        );

        return ['ok' => true, 'todo' => $this->presentTodo(todo: $todo)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateTodo(User $owner, array $arguments): array
    {
        $todo = $this->findOwnedTodo(owner: $owner, arguments: $arguments, ability: 'update');

        if ($todo === null) {
            return ['ok' => false, 'error' => 'Todo not found.'];
        }

        $changesInput = $arguments;
        unset($changesInput['id']);

        $validator = Validator::make(
            $changesInput,
            (new UpdateTodoRequest)->rules(),
            config('todo.messages'),
        );

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        $updated = $this->todos->applyChanges(
            todo: $todo,
            changes: TodoData::changesFromValidated($validator->validated()),
        );

        return ['ok' => true, 'todo' => $this->presentTodo(todo: $updated)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteTodo(User $owner, array $arguments): array
    {
        $todo = $this->findOwnedTodo(owner: $owner, arguments: $arguments, ability: 'delete');

        if ($todo === null) {
            return ['ok' => false, 'error' => 'Todo not found.'];
        }

        $presented = $this->presentTodo(todo: $todo);

        $this->todos->delete(todo: $todo);

        return ['ok' => true, 'todo' => $presented];
    }

    /**
     * Find a todo by the "id" argument, only when the owner may perform the
     * ability on it. Someone else's todo is reported as not found so the
     * model cannot probe other users' ids.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function findOwnedTodo(User $owner, array $arguments, string $ability): ?Todo
    {
        $id = $arguments['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $todo = $owner->todos()->find((int) $id);

        if (! $todo instanceof Todo || ! $owner->can($ability, $todo)) {
            return null;
        }

        return $todo;
    }

    /**
     * Present a todo to the model in the same shape as the REST API.
     *
     * @return array<string, mixed>
     */
    private function presentTodo(Todo $todo): array
    {
        return TodoResource::make($todo)->resolve(new Request);
    }

    private function systemPrompt(): string
    {
        $today = now()->toDateString();

        return <<<PROMPT
            You are a helpful assistant that manages the user's todo list.
            Today is {$today}. Use the tools to list, create, update and delete todos.
            Send dates as YYYY-MM-DD. Look up todo ids with list_todos before updating
            or deleting, and only delete when the user clearly asks for it.
            Never invent todos or ids. Reply briefly, in the same language the user writes in.
            PROMPT;
    }

    /**
     * Tool (function) definitions in the OpenAI chat completions format.
     *
     * @return list<array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        $todoFields = [
            'title' => ['type' => 'string', 'description' => 'Short title, max 255 characters.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'Optional details.'],
            'completed' => ['type' => 'boolean', 'description' => 'Whether the todo is done.'],
            'due_date' => ['type' => ['string', 'null'], 'description' => 'Due date as YYYY-MM-DD.'],
        ];

        $idField = ['id' => ['type' => 'integer', 'description' => 'The todo id from list_todos.']];

        return [
            $this->toolDefinition(
                name: 'list_todos',
                description: "List the user's todos, newest first.",
                properties: [],
                required: [],
            ),
            $this->toolDefinition(
                name: 'create_todo',
                description: 'Create a new todo for the user.',
                properties: $todoFields,
                required: ['title'],
            ),
            $this->toolDefinition(
                name: 'update_todo',
                description: 'Change an existing todo. Send only the fields that change.',
                properties: $idField + $todoFields,
                required: ['id'],
            ),
            $this->toolDefinition(
                name: 'delete_todo',
                description: 'Delete an existing todo.',
                properties: $idField,
                required: ['id'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function toolDefinition(string $name, string $description, array $properties, array $required): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => $required,
                ],
            ],
        ];
    }
}
