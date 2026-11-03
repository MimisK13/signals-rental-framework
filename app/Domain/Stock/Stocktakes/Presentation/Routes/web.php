<?php

declare(strict_types=1);

use App\Domain\Stock\Stocktakes\Presentation\Livewire\Form;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Index;
use App\Domain\Stock\Stocktakes\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('stocktakes', Index::class)->name('stocktakes.index');
    Route::livewire('stocktakes/create', Form::class)->name('stocktakes.create');
    Route::livewire('stocktakes/{stocktake}', Show::class)->name('stocktakes.show');
});
