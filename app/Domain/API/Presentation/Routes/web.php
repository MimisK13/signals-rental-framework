<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Livewire\Explorer;
use App\Domain\API\Presentation\Livewire\Index;
use App\Domain\API\Presentation\Livewire\Logs;
use App\Domain\API\Presentation\Livewire\TokenManagement;
use App\Domain\API\Presentation\Livewire\Usage;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('api', Index::class)->name('api.index');
    Route::livewire('api/explorer', Explorer::class)->name('api.explorer');
    Route::livewire('api/logs', Logs::class)->name('api.logs');
    Route::livewire('api/usage', Usage::class)->name('api.usage');
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/api', TokenManagement::class)->name('admin.settings.api');
});
