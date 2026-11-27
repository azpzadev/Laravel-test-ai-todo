<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\IssuedTokenData;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Models\User;
use Illuminate\Validation\ValidationException;

interface AuthServiceInterface
{
    /**
     * Create a new user and issue them an API token.
     */
    public function register(RegisterData $data): IssuedTokenData;

    /**
     * Verify the credentials and issue a new API token.
     *
     * @throws ValidationException when the credentials are invalid.
     */
    public function login(LoginData $data): IssuedTokenData;

    /**
     * Revoke the API token used for the current request.
     */
    public function logout(User $user): void;
}
