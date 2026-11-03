<?php

declare(strict_types=1);

use App\Domain\Notifications\Presentation\Livewire\Admin\NotificationSettings;
use App\Domain\Notifications\Presentation\Livewire\Settings\Preferences;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('settings/notifications', Preferences::class)->name('settings.notifications');
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/notifications', NotificationSettings::class)->name('admin.settings.notifications');
});
