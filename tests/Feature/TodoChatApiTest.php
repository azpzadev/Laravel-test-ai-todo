<?php

declare(strict_types=1);

use App\Models\Todo;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

const CHAT_COMPLETIONS_URL = 'https://llm.test/v1/chat/completions';

/**
 * A chat completions response whose assistant message is plain text.
 *
 * @return array<string, mixed>
 */
function chatTextReply(string $content): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
}

/**
 * A chat completions response whose assistant message asks for one tool call.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function chatToolCall(string $toolName, array $arguments, string $callId = 'call_1'): array
{
    return ['choices' => [['message' => [
        'role' => 'assistant',
        'content' => null,
        'tool_calls' => [[
            'id' => $callId,
            'type' => 'function',
            'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
        ]],
    ]]]];
}

/**
 * Decode the tool result the app sent back to the model in the given request.
 *
 * @return array<string, mixed>
 */
function sentToolResult(Request $request): array
{
    $toolMessage = collect($request['messages'])->last(fn (array $message): bool => $message['role'] === 'tool');

    return json_decode($toolMessage['content'], true);
}

beforeEach(function (): void {
    config([
        'services.openai_compatible.url' => 'https://llm.test/v1',
        'services.openai_compatible.key' => 'test-key',
        'services.openai_compatible.model' => 'test-model',
    ]);

    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

// -------------------------------------------------------------------------
// auth & validation
// -------------------------------------------------------------------------

it('returns 401 when unauthenticated', function (): void {
    Http::fake();

    $this->postJson('/api/chat', ['message' => 'Add a todo'])->assertUnauthorized();

    Http::assertNothingSent();
});

it('validates the message', function (mixed $message): void {
    Http::fake();
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => $message])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    Http::assertNothingSent();
})->with([
    'missing' => [null],
    'not a string' => [['array']],
    'too long' => [str_repeat('a', 2001)],
]);

// -------------------------------------------------------------------------
// plain reply
// -------------------------------------------------------------------------

it('returns the assistant reply and sends the configured model, key and tools', function (): void {
    Http::fake([CHAT_COMPLETIONS_URL => Http::response(chatTextReply('Hello! How can I help?'))]);
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Hi'])
        ->assertOk()
        ->assertExactJson(['data' => ['reply' => 'Hello! How can I help?', 'actions' => []]]);

    Http::assertSent(function (Request $request): bool {
        $toolNames = collect($request['tools'])->pluck('function.name')->all();

        return $request->url() === CHAT_COMPLETIONS_URL
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test-model'
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Hi']
            && $toolNames === ['list_todos', 'create_todo', 'update_todo', 'delete_todo'];
    });
});

// -------------------------------------------------------------------------
// tools
// -------------------------------------------------------------------------

it('creates a todo for the current user through the create_todo tool', function (): void {
    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('create_todo', ['title' => 'Buy milk', 'due_date' => '2026-11-28']))
        ->push(chatTextReply('Added "Buy milk" for tomorrow.'));
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/chat', ['message' => 'Add buy milk for tomorrow'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'Added "Buy milk" for tomorrow.')
        ->assertJsonPath('data.actions.0.tool', 'create_todo')
        ->assertJsonPath('data.actions.0.status', 'ok');

    $todo = Todo::query()->sole();
    expect($todo->user_id)->toBe($this->owner->id)
        ->and($todo->title)->toBe('Buy milk')
        ->and($todo->due_date->toDateString())->toBe('2026-11-28')
        ->and($todo->completed)->toBeFalse()
        ->and($response->json('data.actions.0.todo_id'))->toBe($todo->id);

    Http::assertSentCount(2);
    $secondRequest = Http::recorded()[1][0];
    expect(sentToolResult($secondRequest))->toMatchArray(['ok' => true])
        ->and($secondRequest['messages'][2]['tool_calls'][0]['id'])->toBe('call_1')
        ->and(collect($secondRequest['messages'])->last()['tool_call_id'])->toBe('call_1');
});

it('lists only the current user\'s todos through the list_todos tool', function (): void {
    $ownTodo = Todo::factory()->for($this->owner)->create(['title' => 'Mine']);
    Todo::factory()->for($this->otherUser)->create(['title' => 'Not mine']);

    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('list_todos', []))
        ->push(chatTextReply('You have 1 todo: Mine.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'What are my todos?'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'You have 1 todo: Mine.');

    $result = sentToolResult(Http::recorded()[1][0]);
    expect($result['total'])->toBe(1)
        ->and(collect($result['todos'])->pluck('id')->all())->toBe([$ownTodo->id]);
});

it('updates only the given fields through the update_todo tool', function (): void {
    $todo = Todo::factory()->for($this->owner)->create([
        'title' => 'Write report',
        'description' => 'Quarterly',
        'completed' => false,
    ]);

    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('update_todo', ['id' => $todo->id, 'completed' => true]))
        ->push(chatTextReply('Marked as done.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Mark write report as done'])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'ok')
        ->assertJsonPath('data.actions.0.todo_id', $todo->id);

    $this->assertDatabaseHas('todos', [
        'id' => $todo->id,
        'title' => 'Write report',
        'description' => 'Quarterly',
        'completed' => true,
    ]);
});

it('deletes a todo through the delete_todo tool', function (): void {
    $todo = Todo::factory()->for($this->owner)->create();

    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('delete_todo', ['id' => $todo->id]))
        ->push(chatTextReply('Deleted.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Delete it'])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'ok');

    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

it('never lets the tools change another user\'s todo', function (string $toolName, array $extraArguments): void {
    $foreignTodo = Todo::factory()->for($this->otherUser)->create(['title' => 'Untouched']);

    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall($toolName, ['id' => $foreignTodo->id] + $extraArguments))
        ->push(chatTextReply('I could not find that todo.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Change todo '.$foreignTodo->id])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'error')
        ->assertJsonPath('data.actions.0.todo_id', null);

    expect(sentToolResult(Http::recorded()[1][0]))->toBe(['ok' => false, 'error' => 'Todo not found.']);
    $this->assertDatabaseHas('todos', ['id' => $foreignTodo->id, 'title' => 'Untouched']);
})->with([
    'update' => ['update_todo', ['title' => 'Hacked']],
    'delete' => ['delete_todo', []],
]);

it('reports a missing todo id as not found', function (): void {
    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('delete_todo', ['id' => 999]))
        ->push(chatTextReply('That todo does not exist.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Delete todo 999'])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'error');

    expect(sentToolResult(Http::recorded()[1][0]))->toBe(['ok' => false, 'error' => 'Todo not found.']);
});

it('sends validation errors back to the model and saves nothing', function (): void {
    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push(chatToolCall('create_todo', ['title' => str_repeat('a', 256), 'due_date' => 'not-a-date']))
        ->push(chatTextReply('The title is too long.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Add a long todo'])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'error');

    $result = sentToolResult(Http::recorded()[1][0]);
    expect($result['ok'])->toBeFalse()
        ->and($result['errors'])->toHaveKeys(['title', 'due_date']);
    expect(Todo::query()->count())->toBe(0);
});

it('rejects unknown tools and malformed arguments', function (array $toolCallResponse, string $expectedError): void {
    Http::fakeSequence(CHAT_COMPLETIONS_URL)
        ->push($toolCallResponse)
        ->push(chatTextReply('Sorry.'));
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Do something'])
        ->assertOk()
        ->assertJsonPath('data.actions.0.status', 'error');

    expect(sentToolResult(Http::recorded()[1][0]))->toBe(['ok' => false, 'error' => $expectedError]);
    expect(Todo::query()->count())->toBe(0);
})->with([
    'unknown tool' => [chatToolCall('drop_database', []), 'Unknown tool [drop_database].'],
    'malformed json' => [
        ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'create_todo', 'arguments' => '{not json'],
        ]]]]]],
        'The tool arguments must be a JSON object.',
    ],
]);

it('stops after the configured number of tool rounds', function (): void {
    config(['todo.chat.max_tool_rounds' => 2]);
    Http::fake([CHAT_COMPLETIONS_URL => Http::response(chatToolCall('list_todos', []))]);
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Loop forever'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'Sorry, I could not finish that request. Please try again with a simpler message.')
        ->assertJsonCount(2, 'data.actions');

    Http::assertSentCount(2);
});

// -------------------------------------------------------------------------
// provider failures
// -------------------------------------------------------------------------

it('returns 503 when the AI provider is not configured', function (): void {
    config(['services.openai_compatible.key' => null]);
    Http::fake();
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Hi'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'The AI assistant is not configured.');

    Http::assertNothingSent();
});

it('returns 502 when the AI provider fails', function (Closure $fakeProvider): void {
    $fakeProvider();
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/chat', ['message' => 'Hi'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI assistant is unavailable right now.');

    expect(Todo::query()->count())->toBe(0);
})->with([
    'server error' => [fn () => Http::fake([CHAT_COMPLETIONS_URL => Http::response(['error' => 'boom'], 500)])],
    'connection error' => [fn () => Http::fake([CHAT_COMPLETIONS_URL => fn () => throw new ConnectionException('timeout')])],
    'unexpected body' => [fn () => Http::fake([CHAT_COMPLETIONS_URL => Http::response(['choices' => []])])],
]);
