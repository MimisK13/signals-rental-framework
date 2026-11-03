<?php

declare(strict_types=1);

use App\Domain\Projects\Opportunities\Presentation\Http\Controllers\Web\OpportunityLineItemController;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Assets;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Costs;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Form;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Index;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Participants;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Show;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Versions;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('opportunities', Index::class)->name('opportunities.index');
    Route::livewire('opportunities/create', Form::class)->name('opportunities.create');
    Route::livewire('opportunities/{opportunity}', Show::class)->withTrashed()->name('opportunities.show');
    Route::livewire('opportunities/{opportunity}/edit', Form::class)->withTrashed()->name('opportunities.edit');
    Route::livewire('opportunities/{opportunity}/assets', Assets::class)->withTrashed()->name('opportunities.assets');
    Route::livewire('opportunities/{opportunity}/costs', Costs::class)->withTrashed()->name('opportunities.costs');
    Route::livewire('opportunities/{opportunity}/participants', Participants::class)->withTrashed()->name('opportunities.participants');
    Route::livewire('opportunities/{opportunity}/versions', Versions::class)->withTrashed()->name('opportunities.versions');
    Route::delete('opportunities/{opportunity}/items/{item}', [OpportunityLineItemController::class, 'destroy'])
        ->withTrashed()
        ->name('opportunities.items.destroy');
});
