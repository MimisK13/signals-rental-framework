<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Settings\Presentation\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
        Route::get('settings/{group}', [SettingsController::class, 'show'])->name('api.v1.settings.show');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->name('api.v1.settings.update');
    });
