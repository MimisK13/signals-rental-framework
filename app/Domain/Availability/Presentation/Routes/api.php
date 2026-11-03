<?php

declare(strict_types=1);

use App\Domain\Availability\Presentation\Http\Controllers\Api\V1\AvailabilityController;
use App\Domain\Availability\Shortages\Presentation\Http\Controllers\Api\V1\ShortageController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])->name('api.v1.availability.calendar');
        Route::get('availability/shortages', [AvailabilityController::class, 'shortages'])->name('api.v1.availability.shortages');
        Route::get('availability/{product}/gantt', [AvailabilityController::class, 'gantt'])->name('api.v1.availability.gantt');
        Route::get('availability', [AvailabilityController::class, 'index'])->name('api.v1.availability.index');
        Route::get('products/{product}/availability', [AvailabilityController::class, 'showForProduct'])->name('api.v1.products.availability');
        Route::get('products/{product}/available-assets', [AvailabilityController::class, 'availableAssets'])->name('api.v1.products.available_assets');

        Route::get('opportunities/{opportunity}/shortages', [ShortageController::class, 'index'])->name('api.v1.opportunities.shortages.index');
        Route::get('opportunities/{opportunity}/shortage_resolutions', [ShortageController::class, 'resolutions'])->name('api.v1.opportunities.shortage_resolutions.index');
        Route::get('opportunities/{opportunity}/shortage_gate', [ShortageController::class, 'gate'])->name('api.v1.opportunities.shortage_gate');
        Route::get('opportunities/{opportunity}/items/{item}/shortage_resolvers', [ShortageController::class, 'resolvers'])->name('api.v1.opportunities.shortage_resolvers');
        Route::post('opportunities/{opportunity}/shortages/acknowledge', [ShortageController::class, 'acknowledge'])->name('api.v1.opportunities.shortages.acknowledge');
        Route::post('shortage_resolutions', [ShortageController::class, 'resolve'])->name('api.v1.shortage_resolutions.store');
        Route::get('shortage_resolutions/batch', [ShortageController::class, 'batchResolvers'])->name('api.v1.shortage_resolutions.batch.index');
        Route::post('shortage_resolutions/batch', [ShortageController::class, 'batchResolve'])->name('api.v1.shortage_resolutions.batch.store');
        Route::post('shortage_resolutions/{resolution}/transition', [ShortageController::class, 'transitionResolution'])->name('api.v1.shortage_resolutions.transition');
        Route::patch('shortage_resolutions/{resolution}/confirm', [ShortageController::class, 'confirmResolution'])->name('api.v1.shortage_resolutions.confirm');
        Route::patch('shortage_resolutions/{resolution}/start', [ShortageController::class, 'startResolution'])->name('api.v1.shortage_resolutions.start');
        Route::patch('shortage_resolutions/{resolution}/fulfill', [ShortageController::class, 'fulfillResolution'])->name('api.v1.shortage_resolutions.fulfill');
        Route::patch('shortage_resolutions/{resolution}/cancel', [ShortageController::class, 'cancelResolution'])->name('api.v1.shortage_resolutions.cancel');
        Route::patch('shortage_resolutions/{resolution}/fail', [ShortageController::class, 'failResolution'])->name('api.v1.shortage_resolutions.fail');
    });
