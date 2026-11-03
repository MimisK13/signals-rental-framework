<?php

declare(strict_types=1);

use App\Domain\Availability\Presentation\Livewire\Bookings;
use App\Domain\Availability\Presentation\Livewire\Index;
use App\Domain\Availability\Presentation\Livewire\ProductAvailability;
use App\Domain\Availability\Shortages\Presentation\Livewire\OpportunityShortages;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('availability/bookings/{product}/{from}/{days}', Bookings::class)
        ->name('availability.bookings')
        ->where(['from' => '[0-9]{4}-[0-9]{2}-[0-9]{2}', 'days' => '[0-9]+']);
    Route::livewire('availability', Index::class)->name('availability.index');
    Route::livewire('products/{product}/availability', ProductAvailability::class)->name('products.availability');
    Route::livewire('opportunities/{opportunity}/shortages', OpportunityShortages::class)
        ->withTrashed()
        ->name('opportunities.shortages');
});
