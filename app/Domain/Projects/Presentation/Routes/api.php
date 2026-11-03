<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Projects\Presentation\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('projects', ProjectController::class)->names('api.v1.projects');
    });
