<?php

declare(strict_types=1);

use App\Domain\CustomFields\Presentation\Http\Controllers\Api\V1\CustomFieldController;
use App\Domain\CustomFields\Presentation\Http\Controllers\Api\V1\CustomFieldGroupController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('custom_field_groups', CustomFieldGroupController::class)->names('api.v1.custom_field_groups');
        Route::apiResource('custom_fields', CustomFieldController::class)->names('api.v1.custom_fields');
    });
