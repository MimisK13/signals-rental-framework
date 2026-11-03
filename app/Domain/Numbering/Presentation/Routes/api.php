<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Numbering\Presentation\Http\Controllers\Api\V1\DocumentSequenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('document_sequences', [DocumentSequenceController::class, 'index'])->name('api.v1.document_sequences.index');
        Route::put('document_sequences/{documentSequence}', [DocumentSequenceController::class, 'update'])->name('api.v1.document_sequences.update');
    });
