<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Currency\Presentation\Http\Controllers\Api\V1\CurrencyController;
use App\Domain\Currency\Presentation\Http\Controllers\Api\V1\ExchangeRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('currencies', CurrencyController::class)->only(['index', 'show'])->names('api.v1.currencies');
        Route::apiResource('exchange_rates', ExchangeRateController::class)->names('api.v1.exchange_rates');
    });
