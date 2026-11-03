<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Documents\Presentation\Http\Controllers\Api\V1\DocumentController;
use App\Domain\Documents\Presentation\Http\Controllers\Api\V1\DocumentSequenceController;
use App\Domain\Documents\Presentation\Http\Controllers\Api\V1\DocumentTemplateController;
use App\Domain\Documents\Presentation\Http\Controllers\Api\V1\DocumentTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('document_types', [DocumentTypeController::class, 'index'])->name('api.v1.document_types.index');
        Route::get('document_types/{type}/fields', [DocumentTypeController::class, 'fields'])->name('api.v1.document_types.fields');
        Route::get('document_sequences', [DocumentSequenceController::class, 'index'])->name('api.v1.document_sequences.index');
        Route::put('document_sequences/{documentSequence}', [DocumentSequenceController::class, 'update'])->name('api.v1.document_sequences.update');
        Route::post('document_templates/import', [DocumentTemplateController::class, 'import'])->name('api.v1.document_templates.import');
        Route::get('document_templates/{documentTemplate}/export', [DocumentTemplateController::class, 'export'])->name('api.v1.document_templates.export');
        Route::get('document_templates', [DocumentTemplateController::class, 'index'])->name('api.v1.document_templates.index');
        Route::post('document_templates', [DocumentTemplateController::class, 'store'])->name('api.v1.document_templates.store');
        Route::get('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'show'])->name('api.v1.document_templates.show');
        Route::put('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('api.v1.document_templates.update');
        Route::delete('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('api.v1.document_templates.destroy');
        Route::post('document_templates/{documentTemplate}/set_default', [DocumentTemplateController::class, 'setDefault'])->name('api.v1.document_templates.set_default');
        Route::post('document_templates/{documentTemplate}/duplicate', [DocumentTemplateController::class, 'duplicate'])->name('api.v1.document_templates.duplicate');
        Route::post('document_templates/{documentTemplate}/restore_default', [DocumentTemplateController::class, 'restoreDefault'])->name('api.v1.document_templates.restore_default');
        Route::post('document_templates/{documentTemplate}/versions/{version}/restore', [DocumentTemplateController::class, 'restoreVersion'])->name('api.v1.document_templates.restore_version');

        Route::get('documents', [DocumentController::class, 'index'])->name('api.v1.documents.index');
        Route::post('documents/generate', [DocumentController::class, 'generate'])->name('api.v1.documents.generate');
        Route::post('documents/batch_generate', [DocumentController::class, 'batchGenerate'])->name('api.v1.documents.batch_generate');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('api.v1.documents.show');
        Route::post('documents/{document}/finalise', [DocumentController::class, 'finalise'])->name('api.v1.documents.finalise');
        Route::post('documents/{document}/void', [DocumentController::class, 'void'])->name('api.v1.documents.void');
        Route::post('documents/{document}/public_link', [DocumentController::class, 'createPublicLink'])->name('api.v1.documents.public_link.create');
        Route::put('documents/{document}/public_link', [DocumentController::class, 'configurePublicLink'])->name('api.v1.documents.public_link.configure');
        Route::delete('documents/{document}/public_link', [DocumentController::class, 'revokePublicLink'])->name('api.v1.documents.public_link.revoke');
    });
