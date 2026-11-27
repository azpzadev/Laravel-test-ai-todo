<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\TodoServiceInterface;
use App\Services\AuthService;
use App\Services\TodoService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TodoServiceInterface::class, TodoService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
