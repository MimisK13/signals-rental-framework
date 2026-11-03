<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\VirtualStock\Presentation\Http\Controllers\Api\V1\VirtualStockIntakeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::post('virtual_stock_intakes/{virtual_stock_intake}/confirm', [VirtualStockIntakeController::class, 'confirm'])->name('api.v1.virtual_stock_intakes.confirm');
        Route::post('virtual_stock_intakes/{virtual_stock_intake}/receive', [VirtualStockIntakeController::class, 'receive'])->name('api.v1.virtual_stock_intakes.receive');
        Route::post('virtual_stock_intakes/{virtual_stock_intake}/return', [VirtualStockIntakeController::class, 'return'])->name('api.v1.virtual_stock_intakes.return');
        Route::post('virtual_stock_intakes/{virtual_stock_intake}/cancel', [VirtualStockIntakeController::class, 'cancel'])->name('api.v1.virtual_stock_intakes.cancel');
        Route::post('virtual_stock_intakes/{virtual_stock_intake}/apportion', [VirtualStockIntakeController::class, 'apportion'])->name('api.v1.virtual_stock_intakes.apportion');
        Route::put('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides', [VirtualStockIntakeController::class, 'setCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.set');
        Route::delete('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides/{opportunity}', [VirtualStockIntakeController::class, 'clearCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.clear');
        Route::apiResource('virtual_stock_intakes', VirtualStockIntakeController::class)->names('api.v1.virtual_stock_intakes');
    });
