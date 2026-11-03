<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\BundleRuleController;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\DiscountCategoryController;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\DiscountRuleController;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\PriceCategoryController;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\SurchargeController;
use App\Domain\Pricing\Presentation\Http\Controllers\Api\V1\SurchargeGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('discount_categories', DiscountCategoryController::class)->names('api.v1.discount_categories');
        Route::apiResource('discount_rules', DiscountRuleController::class)->names('api.v1.discount_rules');
        Route::apiResource('bundle_rules', BundleRuleController::class)->names('api.v1.bundle_rules');
        Route::apiResource('surcharge_groups', SurchargeGroupController::class)->names('api.v1.surcharge_groups');
        Route::apiResource('surcharges', SurchargeController::class)->names('api.v1.surcharges');
        Route::apiResource('price_categories', PriceCategoryController::class)->names('api.v1.price_categories');
    });
