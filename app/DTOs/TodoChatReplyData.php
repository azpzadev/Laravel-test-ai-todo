<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TodoChatReplyData
{
    /**
     * @param  list<array{tool: string, status: string, todo_id: int|null}>  $actions  Tool calls the assistant ran, in order.
     */
    public function __construct(
        public string $reply,
        public array $actions,
    ) {}
}
