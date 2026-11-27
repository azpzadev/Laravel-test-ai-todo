<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\TodoChatData;
use App\Http\Requests\TodoChatRequest;
use App\Http\Resources\TodoChatReplyResource;
use App\Repositories\TodoChatRepository;

final class TodoChatController extends Controller
{
    public function __construct(
        private readonly TodoChatRepository $repository,
    ) {}

    /**
     * Send a chat message to the AI assistant that manages the user's todos.
     */
    public function __invoke(TodoChatRequest $request): TodoChatReplyResource
    {
        $reply = $this->repository->reply(
            owner: $request->user(),
            data: TodoChatData::fromRequest($request),
        );

        return TodoChatReplyResource::make($reply);
    }
}
