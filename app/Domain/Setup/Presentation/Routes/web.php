<?php

declare(strict_types=1);

use App\Domain\Setup\Presentation\Livewire\Wizard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-required'])->group(function (): void {
    Route::livewire('setup', Wizard::class)->name('setup.wizard');
});
