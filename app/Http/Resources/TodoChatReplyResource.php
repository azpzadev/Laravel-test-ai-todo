<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\TodoChatReplyData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TodoChatReplyData
 */
final class TodoChatReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reply' => $this->reply,
            'actions' => $this->actions,
        ];
    }
}
