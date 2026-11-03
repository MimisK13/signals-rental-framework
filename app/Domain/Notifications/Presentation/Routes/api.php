<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Notifications\Presentation\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Domain\Notifications\Presentation\Http\Controllers\Api\V1\NotificationSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('notification_preferences', [NotificationPreferenceController::class, 'index'])->name('api.v1.notification_preferences.index');
        Route::put('notification_preferences', [NotificationPreferenceController::class, 'update'])->name('api.v1.notification_preferences.update');
        Route::get('notification_settings', [NotificationSettingController::class, 'index'])->name('api.v1.notification_settings.index');
        Route::put('notification_settings', [NotificationSettingController::class, 'update'])->name('api.v1.notification_settings.update');
    });
