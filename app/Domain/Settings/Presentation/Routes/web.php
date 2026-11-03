<?php

declare(strict_types=1);

use App\Domain\Settings\Presentation\Livewire\Admin\Branding;
use App\Domain\Settings\Presentation\Livewire\Admin\Company;
use App\Domain\Settings\Presentation\Livewire\Admin\Email;
use App\Domain\Settings\Presentation\Livewire\Admin\Finance;
use App\Domain\Settings\Presentation\Livewire\Admin\GeneralPreferences;
use App\Domain\Settings\Presentation\Livewire\Admin\Integrations;
use App\Domain\Settings\Presentation\Livewire\Admin\Scheduling;
use App\Domain\Settings\Presentation\Livewire\Admin\Security;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/company', Company::class)->name('admin.settings.company');
    Route::livewire('admin/settings/branding', Branding::class)->name('admin.settings.branding');
    Route::livewire('admin/settings/security', Security::class)->name('admin.settings.security');
    Route::livewire('admin/settings/preferences', GeneralPreferences::class)->name('admin.settings.preferences');
    Route::livewire('admin/settings/email', Email::class)->name('admin.settings.email');
    Route::livewire('admin/settings/scheduling', Scheduling::class)->name('admin.settings.scheduling');
    Route::livewire('admin/settings/integrations', Integrations::class)->name('admin.settings.integrations');
    Route::livewire('admin/settings/finance', Finance::class)->name('admin.settings.finance');
});
