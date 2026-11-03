<?php

declare(strict_types=1);

use App\Domain\Administration\Presentation\Http\Controllers\Api\V1\RoleController;
use App\Domain\Administration\Presentation\Http\Controllers\Api\V1\UserController;
use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('users', UserController::class)->names('api.v1.users');
        Route::apiResource('roles', RoleController::class)->names('api.v1.roles');
    });
