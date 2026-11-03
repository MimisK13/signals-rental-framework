<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Rates\Presentation\Http\Controllers\Api\V1\ProductRateController;
use App\Domain\Rates\Presentation\Http\Controllers\Api\V1\RateCalculationController;
use App\Domain\Rates\Presentation\Http\Controllers\Api\V1\RateDefinitionController;
use App\Domain\Rates\Presentation\Http\Controllers\Api\V1\RateEngineMetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('products.rates', ProductRateController::class)->names('api.v1.products.rates');
        Route::post('products/{product}/calculate_rate', [RateCalculationController::class, 'calculate'])
            ->name('api.v1.products.calculate_rate');

        Route::post('rate_definitions/{rate_definition}/duplicate', [RateDefinitionController::class, 'duplicate'])
            ->name('api.v1.rate_definitions.duplicate');
        Route::apiResource('rate_definitions', RateDefinitionController::class)->names('api.v1.rate_definitions');

        Route::get('rate_engine/strategies', [RateEngineMetaController::class, 'strategies'])
            ->name('api.v1.rate_engine.strategies');
        Route::get('rate_engine/modifiers', [RateEngineMetaController::class, 'modifiers'])
            ->name('api.v1.rate_engine.modifiers');
        Route::get('rate_engine/presets', [RateEngineMetaController::class, 'presets'])
            ->name('api.v1.rate_engine.presets');
        Route::get('rate_engine/schema', [RateEngineMetaController::class, 'schema'])
            ->name('api.v1.rate_engine.schema');
    });
