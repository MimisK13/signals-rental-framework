<?php

declare(strict_types=1);

use App\Domain\Quotations\Presentation\Http\Controllers\QuotationItemController;
use App\Domain\Quotations\Presentation\Livewire\Editor;
use App\Domain\Quotations\Presentation\Livewire\Form;
use App\Domain\Quotations\Presentation\Livewire\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('quotations', Index::class)->name('quotations.index');
    Route::livewire('quotations/create', Form::class)->name('quotations.create');
    Route::livewire('projects/{project}/quotations/create', Form::class)->name('projects.quotations.create');
    Route::livewire('quotations/{quotation}/edit', Editor::class)->name('quotations.edit');
    Route::delete('quotations/{quotation}/items/{item}', [QuotationItemController::class, 'destroy'])
        ->name('quotations.items.destroy');
});
