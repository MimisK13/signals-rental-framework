<?php

declare(strict_types=1);

use App\Domain\System\Presentation\Livewire\Admin\Infrastructure;
use App\Domain\System\Presentation\Livewire\Admin\Seeders;
use App\Domain\System\Presentation\Livewire\Admin\SystemHealth;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/system-health', SystemHealth::class)->name('admin.settings.system-health');
    Route::livewire('admin/settings/infrastructure', Infrastructure::class)->name('admin.settings.infrastructure');
    Route::livewire('admin/settings/seeders', Seeders::class)->name('admin.settings.seeders');
});
