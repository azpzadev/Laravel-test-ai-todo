<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class TodoChatData
{
    public function __construct(
        public string $message,
    ) {}

    /**
     * Build the DTO from a validated FormRequest.
     */
    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            message: (string) $validated['message'],
        );
    }
}
