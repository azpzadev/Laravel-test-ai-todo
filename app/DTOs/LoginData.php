<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * Build the DTO from a validated FormRequest.
     */
    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            email: (string) $validated['email'],
            password: (string) $validated['password'],
        );
    }
}
