<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

/**
 * The result of a successful register or login: the user and the plain-text
 * API token. The plain-text token is only available at issue time.
 */
final readonly class IssuedTokenData
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}
