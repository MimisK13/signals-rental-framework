<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Lists\Presentation\Http\Controllers\Api\V1\ListNameController;
use App\Domain\Lists\Presentation\Http\Controllers\Api\V1\ListValueController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('list_names', ListNameController::class)->names('api.v1.list_names');
        Route::apiResource('list_names.list_values', ListValueController::class)
            ->except(['show'])
            ->names('api.v1.list_names.list_values');

        Route::apiResource('list_of_values', ListNameController::class)
            ->parameters(['list_of_values' => 'list_name'])
            ->names('api.v1.list_of_values');
        Route::apiResource('list_of_values.list_values', ListValueController::class)
            ->parameters(['list_of_values' => 'list_name', 'list_values' => 'list_value'])
            ->except(['show'])
            ->names('api.v1.list_of_values.list_values');
    });
