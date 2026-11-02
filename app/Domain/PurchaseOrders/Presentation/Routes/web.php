<?php

declare(strict_types=1);

use App\Domain\PurchaseOrders\Presentation\Livewire\Form;
use App\Domain\PurchaseOrders\Presentation\Livewire\Index;
use App\Domain\PurchaseOrders\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('purchase-orders', Index::class)->name('purchase-orders.index');
    Route::livewire('purchase-orders/create', Form::class)->name('purchase-orders.create');
    Route::livewire('purchase-orders/{purchaseOrder}', Show::class)->name('purchase-orders.show');
    Route::livewire('purchase-orders/{purchaseOrder}/edit', Form::class)->name('purchase-orders.edit');
});
