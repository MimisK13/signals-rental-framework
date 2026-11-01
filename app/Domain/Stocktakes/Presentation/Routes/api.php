<?php

declare(strict_types=1);

use App\Domain\Stocktakes\Presentation\Http\Controllers\Api\V1\StocktakeController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('stocktakes', [StocktakeController::class, 'index'])->name('api.v1.stocktakes.index');
        Route::post('stocktakes', [StocktakeController::class, 'store'])->name('api.v1.stocktakes.store');
        Route::get('stocktakes/{stocktake}', [StocktakeController::class, 'show'])->name('api.v1.stocktakes.show');
        Route::post('stocktakes/{stocktake}/start', [StocktakeController::class, 'start'])->name('api.v1.stocktakes.start');
        Route::post('stocktakes/{stocktake}/lines/{line}/count', [StocktakeController::class, 'count'])->name('api.v1.stocktakes.lines.count');
        Route::post('stocktakes/{stocktake}/scan', [StocktakeController::class, 'scan'])->name('api.v1.stocktakes.scan');
        Route::post('stocktakes/{stocktake}/complete', [StocktakeController::class, 'complete'])->name('api.v1.stocktakes.complete');
        Route::post('stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel'])->name('api.v1.stocktakes.cancel');
    });
