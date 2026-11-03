<?php

declare(strict_types=1);

use App\Domain\ReferenceData\Presentation\Livewire\Admin\Countries;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/countries', Countries::class)->name('admin.settings.countries');
});
