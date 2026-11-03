<?php

declare(strict_types=1);

use App\Domain\Assets\Repairs\Presentation\Livewire\Form;
use App\Domain\Assets\Repairs\Presentation\Livewire\Index;
use App\Domain\Assets\Repairs\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('repairs', Index::class)->name('repairs.index');
    Route::livewire('repairs/create', Form::class)->name('repairs.create');
    Route::livewire('repairs/{repair}', Show::class)->name('repairs.show');
    Route::livewire('repairs/{repair}/edit', Form::class)->name('repairs.edit');
});
