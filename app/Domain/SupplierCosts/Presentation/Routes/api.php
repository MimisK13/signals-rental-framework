<?php

declare(strict_types=1);

use App\Domain\SupplierCosts\Presentation\Http\Controllers\Api\V1\ProductSupplierCostController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::post('products/{product}/supplier_costs/{supplierCost}/set_preferred', [ProductSupplierCostController::class, 'setPreferred'])
            ->name('api.v1.products.supplier_costs.set_preferred');
        Route::apiResource('products.supplier_costs', ProductSupplierCostController::class)
            ->parameters(['supplier_costs' => 'supplierCost'])
            ->names('api.v1.products.supplier_costs');
    });
