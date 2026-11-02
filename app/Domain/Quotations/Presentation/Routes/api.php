<?php

declare(strict_types=1);

use App\Domain\Quotations\Presentation\Http\Controllers\Api\V1\QuotationController;
use App\Domain\Quotations\Presentation\Http\Controllers\Api\V1\QuotationItemController;
use App\Domain\Quotations\Presentation\Http\Controllers\Api\V1\QuotationVersionController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('quotations', [QuotationController::class, 'index'])->name('api.v1.quotations.index');
        Route::post('quotations', [QuotationController::class, 'store'])->name('api.v1.quotations.store');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('api.v1.quotations.show');
        Route::post('quotations/{quotation}/versions', [QuotationVersionController::class, 'store'])->name('api.v1.quotations.versions.store');
        Route::post('quotations/{quotation}/versions/{version}/activate', [QuotationVersionController::class, 'activate'])->name('api.v1.quotations.versions.activate');
        Route::post('quotations/{quotation}/versions/{version}/send', [QuotationVersionController::class, 'send'])->name('api.v1.quotations.versions.send');
        Route::post('quotations/{quotation}/versions/{version}/accept', [QuotationVersionController::class, 'accept'])->name('api.v1.quotations.versions.accept');
        Route::post('quotations/{quotation}/versions/{version}/decline', [QuotationVersionController::class, 'decline'])->name('api.v1.quotations.versions.decline');
        Route::post('quotations/{quotation}/versions/{version}/items', [QuotationItemController::class, 'store'])->name('api.v1.quotations.items.store');
        Route::patch('quotations/{quotation}/versions/{version}/items/tree', [QuotationItemController::class, 'restructure'])->name('api.v1.quotations.items.tree');
        Route::patch('quotations/{quotation}/versions/{version}/items/{item}', [QuotationItemController::class, 'update'])->name('api.v1.quotations.items.update');
        Route::delete('quotations/{quotation}/versions/{version}/items/{item}', [QuotationItemController::class, 'destroy'])->name('api.v1.quotations.items.destroy');
        Route::put('quotations/{quotation}/versions/{version}/deal-price', [QuotationController::class, 'setDealPrice'])->name('api.v1.quotations.deal-price.set');
        Route::delete('quotations/{quotation}/versions/{version}/deal-price', [QuotationController::class, 'clearDealPrice'])->name('api.v1.quotations.deal-price.clear');
    });
