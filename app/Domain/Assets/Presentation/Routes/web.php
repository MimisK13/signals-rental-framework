<?php

declare(strict_types=1);

use App\Domain\Assets\Presentation\Livewire\Investment;
use App\Domain\Assets\Presentation\Livewire\ProductFinancials;
use App\Domain\Assets\Presentation\Livewire\ProductInspections;
use App\Domain\Assets\Presentation\Livewire\ProductUtilization;
use App\Domain\Assets\Presentation\Livewire\Show;
use App\Domain\Assets\Presentation\Livewire\Utilization;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('assets/{asset}', Show::class)->name('assets.show');
    Route::livewire('assets/{asset}/utilization', Utilization::class)->name('assets.utilization');
    Route::livewire('assets/{asset}/investment', Investment::class)->name('assets.investment');
    Route::livewire('products/{product}/financials', ProductFinancials::class)->name('products.financials');
    Route::livewire('products/{product}/utilization', ProductUtilization::class)->name('products.utilization');
    Route::livewire('products/{product}/inspections', ProductInspections::class)->name('products.inspections');
});
