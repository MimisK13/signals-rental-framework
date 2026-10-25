<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])
    ->prefix('experimental')
    ->name('experimental.')
    ->group(function (): void {
        Volt::route('/', 'experimental.index')->name('index');
        Volt::route('quotations', 'experimental.quotations.index')->name('quotations.index');
        Volt::route('quotations/create', 'experimental.quotations.create')->name('quotations.create');
        Volt::route('quotations/{opportunity}/edit', 'experimental.quotations.edit')->name('quotations.edit');
    });
