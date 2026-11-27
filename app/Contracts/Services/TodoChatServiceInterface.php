<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\TodoChatData;
use App\DTOs\TodoChatReplyData;
use App\Models\User;

interface TodoChatServiceInterface
{
    /**
     * Answer the owner's chat message, letting the AI model list, create,
     * update and delete the owner's todos through tool calls.
     */
    public function reply(User $owner, TodoChatData $data): TodoChatReplyData;
}
