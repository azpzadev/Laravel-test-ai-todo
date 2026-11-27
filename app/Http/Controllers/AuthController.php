<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\IssuedTokenData;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Repositories\AuthRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthRepository $repository,
    ) {}

    /**
     * Register a new user and return an API token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $issued = $this->repository->register(data: RegisterData::fromRequest($request));

        return $this->tokenResponse(issued: $issued)->setStatusCode(201);
    }

    /**
     * Exchange valid credentials for an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $issued = $this->repository->login(data: LoginData::fromRequest($request));

        return $this->tokenResponse(issued: $issued);
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        $this->repository->logout(user: $request->user());

        return response()->noContent();
    }

    /**
     * Return the authenticated user.
     */
    public function user(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * Shape the register/login response: the user plus a bearer token.
     */
    private function tokenResponse(IssuedTokenData $issued): JsonResponse
    {
        return UserResource::make($issued->user)
            ->additional([
                'token' => $issued->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response();
    }
}
