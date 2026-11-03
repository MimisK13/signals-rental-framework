<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\CustomViews\Presentation\Http\Controllers\Api\V1\CustomViewController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('custom_views', CustomViewController::class)->names('api.v1.custom_views');
        Route::post('custom_views/{custom_view}/clone', [CustomViewController::class, 'clone'])
            ->name('api.v1.custom_views.clone');
    });
