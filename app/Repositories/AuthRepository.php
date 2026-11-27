<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Services\AuthServiceInterface;
use App\DTOs\IssuedTokenData;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Models\User;

final readonly class AuthRepository
{
    public function __construct(
        private AuthServiceInterface $service,
    ) {}

    /**
     * Create a new user and issue them an API token.
     */
    public function register(RegisterData $data): IssuedTokenData
    {
        return $this->service->register(data: $data);
    }

    /**
     * Verify the credentials and issue a new API token.
     */
    public function login(LoginData $data): IssuedTokenData
    {
        return $this->service->login(data: $data);
    }

    /**
     * Revoke the API token used for the current request.
     */
    public function logout(User $user): void
    {
        $this->service->logout(user: $user);
    }
}
