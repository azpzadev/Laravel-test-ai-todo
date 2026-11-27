<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\AuthServiceInterface;
use App\DTOs\IssuedTokenData;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthService implements AuthServiceInterface
{
    private const string TOKEN_NAME = 'api';

    /**
     * Create a new user and issue them an API token. Both writes succeed or
     * neither does.
     */
    public function register(RegisterData $data): IssuedTokenData
    {
        $issued = DB::transaction(function () use ($data): IssuedTokenData {
            $user = User::query()->create($data->toArray());

            return new IssuedTokenData(
                user: $user,
                plainTextToken: $user->createToken(self::TOKEN_NAME)->plainTextToken,
            );
        });

        Log::info('auth.registered', ['user_id' => $issued->user->id]);

        return $issued;
    }

    /**
     * Verify the credentials and issue a new API token.
     *
     * @throws ValidationException when the credentials are invalid.
     */
    public function login(LoginData $data): IssuedTokenData
    {
        $user = User::query()->where('email', $data->email)->first();

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Log::info('auth.logged_in', ['user_id' => $user->id]);

        return new IssuedTokenData(
            user: $user,
            plainTextToken: $user->createToken(self::TOKEN_NAME)->plainTextToken,
        );
    }

    /**
     * Revoke the API token used for the current request. Other tokens of the
     * same user (other devices) stay valid.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        Log::info('auth.logged_out', ['user_id' => $user->id]);
    }
}
