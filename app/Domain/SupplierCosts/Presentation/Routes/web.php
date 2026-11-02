<?php

declare(strict_types=1);

use App\Domain\SupplierCosts\Presentation\Livewire\ProductSupplierCosts;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('products/{product}/supplier-costs', ProductSupplierCosts::class)
        ->name('products.supplier-costs');
});
