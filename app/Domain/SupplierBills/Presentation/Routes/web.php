<?php

declare(strict_types=1);

use App\Domain\SupplierBills\Presentation\Livewire\Form;
use App\Domain\SupplierBills\Presentation\Livewire\Index;
use App\Domain\SupplierBills\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('supplier-bills', Index::class)->name('supplier-bills.index');
    Route::livewire('supplier-bills/create', Form::class)->name('supplier-bills.create');
    Route::livewire('supplier-bills/{supplierBill}', Show::class)->name('supplier-bills.show');
    Route::livewire('supplier-bills/{supplierBill}/edit', Form::class)->name('supplier-bills.edit');
});
