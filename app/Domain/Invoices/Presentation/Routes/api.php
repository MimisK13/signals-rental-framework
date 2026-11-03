<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Invoices\Presentation\Http\Controllers\Api\V1\CreditNoteController;
use App\Domain\Invoices\Presentation\Http\Controllers\Api\V1\InvoiceController;
use App\Domain\Invoices\Presentation\Http\Controllers\Api\V1\OpportunityInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::post('opportunities/{opportunity}/invoices', [OpportunityInvoiceController::class, 'store'])
            ->middleware('idempotency')
            ->name('api.v1.opportunities.invoices.store');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('api.v1.invoices.pdf');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('api.v1.invoices.issue');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('api.v1.invoices.void');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->middleware('idempotency')->name('api.v1.invoices.payments.store');
        Route::post('invoices/{invoice}/refunds', [InvoiceController::class, 'recordRefund'])->middleware('idempotency')->name('api.v1.invoices.refunds.store');
        Route::post('invoices/{invoice}/credit_notes', [InvoiceController::class, 'issueCreditNote'])->name('api.v1.invoices.credit_notes.store');
        Route::apiResource('credit_notes', CreditNoteController::class)->only(['index', 'show'])->names('api.v1.credit_notes');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('api.v1.invoices.email');
        Route::apiResource('invoices', InvoiceController::class)->names('api.v1.invoices');
    });
