<?php

declare(strict_types=1);

use App\Domain\Stores\Presentation\Livewire\Admin\Stores;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/stores', Stores::class)->name('admin.settings.stores');
});
