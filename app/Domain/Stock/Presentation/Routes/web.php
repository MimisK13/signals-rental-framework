<?php

declare(strict_types=1);

use App\Domain\Stock\Presentation\Livewire\Form;
use App\Domain\Stock\Presentation\Livewire\Index;
use App\Domain\Stock\Presentation\Livewire\ProductStock;
use App\Domain\Stock\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('stock-levels', Index::class)->name('stock-levels.index');
    Route::livewire('stock-levels/create', Form::class)->name('stock-levels.create');
    Route::livewire('stock-levels/{stockLevel}', Show::class)->name('stock-levels.show');
    Route::livewire('stock-levels/{stockLevel}/edit', Form::class)->name('stock-levels.edit');
    Route::livewire('products/{product}/stock', ProductStock::class)->name('products.stock');
});
