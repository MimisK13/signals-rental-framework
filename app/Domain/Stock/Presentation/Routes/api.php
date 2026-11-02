<?php

declare(strict_types=1);

use App\Domain\Stock\Presentation\Http\Controllers\Api\V1\StockLevelController;
use App\Domain\Stock\Presentation\Http\Controllers\Api\V1\StockTransactionController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('stock_levels', StockLevelController::class)->names('api.v1.stock_levels');
        Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'index'])->name('api.v1.stock_transactions.index');
        Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'show'])->name('api.v1.stock_transactions.show');
        Route::post('products/{product}/stock_levels/{stock_level}/adjust', [StockTransactionController::class, 'adjust'])->name('api.v1.stock_levels.adjust');
        Route::post('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'store'])->name('api.v1.stock_transactions.store');
        Route::delete('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'destroy'])->name('api.v1.stock_transactions.destroy');
        Route::post('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}/reverse', [StockTransactionController::class, 'reverse'])->name('api.v1.stock_transactions.reverse');
    });
