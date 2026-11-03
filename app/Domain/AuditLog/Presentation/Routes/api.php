<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\AuditLog\Presentation\Http\Controllers\Api\V1\ActionLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('actions', [ActionLogController::class, 'index'])->name('api.v1.actions.index');
        Route::post('actions/export', [ActionLogController::class, 'export'])->name('api.v1.actions.export');
    });
