<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\OrganisationTaxClassController;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\ProductTaxClassController;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\TaxCategoryController;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\TaxRateController;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\TaxRuleController;
use App\Domain\Tax\Presentation\Http\Controllers\Api\V1\TaxZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::apiResource('organisation_tax_classes', OrganisationTaxClassController::class)->names('api.v1.organisation_tax_classes');
        Route::apiResource('product_tax_classes', ProductTaxClassController::class)->names('api.v1.product_tax_classes');
        Route::apiResource('tax_zones', TaxZoneController::class)->names('api.v1.tax_zones');
        Route::apiResource('tax_categories', TaxCategoryController::class)->names('api.v1.tax_categories');
        Route::apiResource('tax_rates', TaxRateController::class)->names('api.v1.tax_rates');
        Route::apiResource('tax_rules', TaxRuleController::class)->names('api.v1.tax_rules');
    });
