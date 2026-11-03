<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Controllers\V1\EndpointCatalogueController;
use App\Domain\API\Presentation\Http\Controllers\V1\RequestLogController;
use App\Domain\API\Presentation\Http\Controllers\V1\SchemaController;
use App\Domain\API\Presentation\Http\Controllers\V1\SystemController;
use App\Domain\API\Presentation\Http\Controllers\V1\UsageController;
use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('system/health', [SystemController::class, 'health'])->name('api.v1.system.health');
        Route::get('schema', [SchemaController::class, 'index'])->name('api.v1.schema.index');
        Route::get('schema/{model}', [SchemaController::class, 'show'])->name('api.v1.schema.show');
        Route::get('usage/export', [UsageController::class, 'export'])->name('api.v1.usage.export');
        Route::get('usage', [UsageController::class, 'index'])->name('api.v1.usage.index');
        Route::get('request_logs', [RequestLogController::class, 'index'])->name('api.v1.request_logs.index');
        Route::get('endpoints', [EndpointCatalogueController::class, 'index'])->name('api.v1.endpoints.index');
    });
