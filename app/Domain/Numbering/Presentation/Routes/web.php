<?php

declare(strict_types=1);

use App\Domain\Numbering\Presentation\Livewire\Admin\Numbering;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/numbering', Numbering::class)->name('admin.settings.numbering');
});
