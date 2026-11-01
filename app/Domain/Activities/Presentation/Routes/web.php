<?php

declare(strict_types=1);

use App\Domain\Activities\Presentation\Http\Controllers\CalendarFeedController;
use App\Domain\Activities\Presentation\Livewire\Calendar\Index as CalendarIndex;
use App\Domain\Activities\Presentation\Livewire\Embedded\MemberActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\OpportunityActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\ProductActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\StockLevelActivities;
use App\Domain\Activities\Presentation\Livewire\Form;
use App\Domain\Activities\Presentation\Livewire\Index;
use App\Domain\Activities\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('activities', Index::class)->name('activities.index');
    Route::livewire('activities/create', Form::class)->name('activities.create');
    Route::livewire('activities/{activity}', Show::class)->name('activities.show');
    Route::livewire('activities/{activity}/edit', Form::class)->name('activities.edit');
    Route::livewire('calendar', CalendarIndex::class)->name('calendar.index');
    Route::livewire('members/{member}/activities', MemberActivities::class)->name('members.activities');
    Route::livewire('products/{product}/activities', ProductActivities::class)->name('products.activities');
    Route::livewire('stock-levels/{stockLevel}/activities', StockLevelActivities::class)->name('stock-levels.activities');
    Route::livewire('opportunities/{opportunity}/activities', OpportunityActivities::class)
        ->name('opportunities.activities')->withTrashed();
});

Route::middleware(['web', 'signals.setup-complete'])->group(function (): void {
    Route::get('calendar/feed.ics', [CalendarFeedController::class, 'global'])
        ->name('calendar.feed.global')->middleware('signed');
    Route::get('calendar/feed/{user}.ics', [CalendarFeedController::class, 'user'])
        ->name('calendar.feed.user')->middleware('signed');
});
