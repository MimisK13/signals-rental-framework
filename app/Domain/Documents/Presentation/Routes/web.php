<?php

declare(strict_types=1);

use App\Domain\Documents\Presentation\Http\Controllers\Web\PublicDocumentController;
use App\Domain\Documents\Presentation\Http\Controllers\Web\SignedDocumentDownloadController;
use App\Domain\Documents\Presentation\Livewire\Editor;
use App\Domain\Documents\Presentation\Livewire\Index;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::prefix(config('documents.public_route_prefix', 'd'))->group(function (): void {
        Route::get('{token}', [PublicDocumentController::class, 'show'])->name('documents.public.show');
        Route::post('{token}/pin', [PublicDocumentController::class, 'verifyPin'])->name('documents.public.verify-pin');
    });

    Route::get('documents/{documentId}/file', SignedDocumentDownloadController::class)
        ->middleware('signed')
        ->whereNumber('documentId')
        ->name('documents.signed-download');

    Route::middleware(['signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
        Route::livewire('admin/document-templates', Index::class)->name('admin.document-templates.index');
        Route::livewire('admin/document-templates/{documentTemplate}/edit', Editor::class)->name('admin.document-templates.edit');
    });
});
