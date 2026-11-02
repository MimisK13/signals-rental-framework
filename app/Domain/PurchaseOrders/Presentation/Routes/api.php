<?php

declare(strict_types=1);

use App\Domain\PurchaseOrders\Presentation\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::post('purchase_orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('api.v1.purchase_orders.send');
        Route::post('purchase_orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('api.v1.purchase_orders.receive');
        Route::post('purchase_orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('api.v1.purchase_orders.cancel');
        Route::apiResource('purchase_orders', PurchaseOrderController::class)->names('api.v1.purchase_orders');
    });
