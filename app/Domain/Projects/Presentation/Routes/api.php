<?php

declare(strict_types=1);

use App\Domain\Projects\Presentation\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('projects', ProjectController::class)->names('api.v1.projects');
        Route::post('projects/{project}/opportunities/{opportunity}/attach', [ProjectController::class, 'attach'])
            ->name('api.v1.projects.opportunities.attach');
        Route::delete('projects/{project}/opportunities/{opportunity}', [ProjectController::class, 'detach'])
            ->name('api.v1.projects.opportunities.detach');
        Route::post('projects/{project}/opportunities/{opportunity}/move', [ProjectController::class, 'move'])
            ->name('api.v1.projects.opportunities.move');
    });
