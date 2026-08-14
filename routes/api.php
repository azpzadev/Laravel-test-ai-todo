<?php

declare(strict_types=1);

use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function (): void {
    Route::apiResource('todos', TodoController::class);
});
