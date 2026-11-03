<?php

declare(strict_types=1);

use App\Domain\VirtualStock\Presentation\Livewire\Form;
use App\Domain\VirtualStock\Presentation\Livewire\Index;
use App\Domain\VirtualStock\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('virtual-stock', Index::class)->name('virtual-stock.index');
    Route::livewire('virtual-stock/create', Form::class)->name('virtual-stock.create');
    Route::livewire('virtual-stock/{virtualStockIntake}', Show::class)->name('virtual-stock.show');
});
