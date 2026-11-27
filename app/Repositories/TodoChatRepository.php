<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Services\TodoChatServiceInterface;
use App\DTOs\TodoChatData;
use App\DTOs\TodoChatReplyData;
use App\Models\User;

final readonly class TodoChatRepository
{
    public function __construct(
        private TodoChatServiceInterface $service,
    ) {}

    /**
     * Answer the owner's chat message, managing their todos through the AI model.
     */
    public function reply(User $owner, TodoChatData $data): TodoChatReplyData
    {
        return $this->service->reply(owner: $owner, data: $data);
    }
}
