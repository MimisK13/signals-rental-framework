<?php

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api automatically by Laravel.
| The v1 group adds the /v1 prefix and applies authentication +
| active-user middleware to all endpoints.
|
*/

Route::prefix('v1')->middleware([ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])->group(function (): void {

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
    Route::get('settings/{group}', [SettingsController::class, 'show'])->name('api.v1.settings.show');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->name('api.v1.settings.update');

    // Users
    Route::apiResource('users', UserController::class)->names('api.v1.users');

    // Roles
    Route::apiResource('roles', RoleController::class)->names('api.v1.roles');

    // Custom Views
    // Custom Fields

    // Stock Levels

});
