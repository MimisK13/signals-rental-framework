<?php

declare(strict_types=1);

use App\Domain\AuditLog\Presentation\Livewire\Admin\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/action-log', Index::class)->name('admin.settings.action-log');
});
