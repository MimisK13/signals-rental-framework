<?php

declare(strict_types=1);

use App\Domain\Activities\Presentation\Http\Controllers\Api\V1\ActivityController;
use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('activities', ActivityController::class)->names('api.v1.activities');
        Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])
            ->name('api.v1.activities.complete');
    });
