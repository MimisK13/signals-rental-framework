<?php

declare(strict_types=1);

use App\Domain\Invoices\Presentation\Livewire\Communications;
use App\Domain\Invoices\Presentation\Livewire\CreditNotesIndex;
use App\Domain\Invoices\Presentation\Livewire\Form;
use App\Domain\Invoices\Presentation\Livewire\Index;
use App\Domain\Invoices\Presentation\Livewire\PaymentsIndex;
use App\Domain\Invoices\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('invoices', Index::class)->name('invoices.index');
    Route::livewire('invoices/create', Form::class)->name('invoices.create');
    Route::livewire('invoices/{invoice}', Show::class)->name('invoices.show');
    Route::livewire('invoices/{invoice}/communications', Communications::class)->name('invoices.communications');
    Route::livewire('invoices/{invoice}/edit', Form::class)->name('invoices.edit');
    Route::redirect('finance/credit-notes', '/credit-notes', 301);
    Route::redirect('finance/payments', '/payments', 301);
    Route::livewire('credit-notes', CreditNotesIndex::class)->name('credit-notes.index');
    Route::livewire('payments', PaymentsIndex::class)->name('payments.index');
});
