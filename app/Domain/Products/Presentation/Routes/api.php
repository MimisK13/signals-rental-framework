<?php

declare(strict_types=1);

use App\Domain\Products\Presentation\Http\Controllers\Api\V1\AccessoryController;
use App\Domain\Products\Presentation\Http\Controllers\Api\V1\ProductContainerTemplateController;
use App\Domain\Products\Presentation\Http\Controllers\Api\V1\ProductController;
use App\Domain\Products\Presentation\Http\Controllers\Api\V1\ProductGroupController;
use App\Domain\Products\Presentation\Http\Controllers\Api\V1\SerialisedComponentController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('products', ProductController::class)->names('api.v1.products');
        Route::post('products/{product}/merge', [ProductController::class, 'merge'])->name('api.v1.products.merge');
        Route::apiResource('products.accessories', AccessoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('api.v1.products.accessories');
        Route::apiResource('products.components', SerialisedComponentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['components' => 'component'])
            ->names('api.v1.products.components');
        Route::get('products/{product}/container_template', [ProductContainerTemplateController::class, 'show'])
            ->name('api.v1.products.container_template.show');
        Route::put('products/{product}/container_template', [ProductContainerTemplateController::class, 'update'])
            ->name('api.v1.products.container_template.update');

        Route::apiResource('product_groups', ProductGroupController::class)->names('api.v1.product_groups');
    });
