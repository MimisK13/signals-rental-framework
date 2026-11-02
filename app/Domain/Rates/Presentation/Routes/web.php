<?php

declare(strict_types=1);

use App\Domain\Rates\Presentation\Livewire\ProductRateForm;
use App\Domain\Rates\Presentation\Livewire\ProductRates;
use App\Domain\Rates\Presentation\Livewire\RateDefinitionForm;
use App\Domain\Rates\Presentation\Livewire\RateDefinitions;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('products/{product}/rates', ProductRates::class)->name('products.rates');
    Route::livewire('products/{product}/rates/create', ProductRateForm::class)->name('products.rates.create');
    Route::livewire('products/{product}/rates/{rate}/edit', ProductRateForm::class)->name('products.rates.edit');
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/rate-definitions', RateDefinitions::class)
        ->name('admin.settings.rate-definitions');
    Route::livewire('admin/settings/rate-definitions/create', RateDefinitionForm::class)
        ->name('admin.settings.rate-definitions.create');
    Route::livewire('admin/settings/rate-definitions/{rateDefinition}/edit', RateDefinitionForm::class)
        ->name('admin.settings.rate-definitions.edit');
});
