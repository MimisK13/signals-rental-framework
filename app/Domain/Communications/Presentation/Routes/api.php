<?php

declare(strict_types=1);

use App\Domain\Communications\Presentation\Http\Controllers\Api\V1\ChannelProviderController;
use App\Domain\Communications\Presentation\Http\Controllers\Api\V1\CommunicationLogController;
use App\Domain\Communications\Presentation\Http\Controllers\Api\V1\CommunicationTemplateController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])->group(function (): void {
    Route::apiResource('communication_log', CommunicationLogController::class)
        ->only(['index', 'show'])
        ->names('api.v1.communication_log');
    Route::get('communication_templates/{communicationTemplate}/versions', [CommunicationTemplateController::class, 'versions'])
        ->name('api.v1.communication_templates.versions');
    Route::post('communication_templates/{communicationTemplate}/versions/{version}/restore', [CommunicationTemplateController::class, 'restoreVersion'])
        ->name('api.v1.communication_templates.restore_version');
    Route::apiResource('communication_templates', CommunicationTemplateController::class)
        ->names('api.v1.communication_templates');
    Route::post('channel_providers/{channel_provider}/test', [ChannelProviderController::class, 'test'])
        ->name('api.v1.channel_providers.test');
    Route::apiResource('channel_providers', ChannelProviderController::class)
        ->names('api.v1.channel_providers');
});
