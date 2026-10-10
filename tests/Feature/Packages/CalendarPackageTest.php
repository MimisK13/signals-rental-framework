<?php

use Calendar\Livewire\Calendar;
use Calendar\Livewire\Planner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

it('renders the calendar Blade component', function (): void {
    expect(config('calendar.day_start_hour'))->toBe('00:00')
        ->and(config('calendar.day_end_hour'))->toBe('24:00')
        ->and(config('calendar.time_format'))->toBe('24h');

    $html = Blade::render(
        '<x-calendar::timeline :days="$days" :events="$events" />',
        [
            'days' => [['date' => '2026-05-15', 'label' => 'Monday']],
            'events' => [['id' => 1, 'date' => '2026-05-15', 'label' => 'Delivery']],
        ],
    );

    expect($html)
        ->toContain('Monday')
        ->toContain('Delivery')
        ->toContain('grid-cols-7')
        ->toContain('min-h-32')
        ->toContain('selectedView')
        ->toContain('Calendar views')
        ->toContain('00:00')
        ->toContain('23:00');
});

it('renders the optional Livewire calendar component', function (): void {
    Livewire::test(Calendar::class, [
        'days' => [['label' => 'Monday']],
        'events' => [['id' => 1, 'date' => '2026-05-15', 'label' => 'Delivery']],
        'date' => '2026-05-15',
    ])
        ->assertSee('Monday')
        ->assertSee('Delivery');
});

it('supports day, week, month and year calendar views', function (): void {
    $calendar = Livewire::test(Calendar::class, [
        'date' => '2026-05-15',
    ]);

    /** @var Testable<Calendar> $calendar */
    $calendar = $calendar;

    $calendar->assertSee('May 2026')
        ->assertSeeInOrder(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);

    $calendar->call('setView', 'day')
        ->assertSet('view', 'day')
        ->assertSee('Friday')
        ->assertSee('15');

    $calendar
        ->call('setView', 'week')
        ->assertSet('view', 'week');

    $calendar
        ->call('setView', 'month')
        ->assertSet('view', 'month');

    $calendar
        ->call('setView', 'year')
        ->assertSet('view', 'year')
        ->assertSee('January');
});

it('finishes the month grid when dates are immutable', function (): void {
    $calendar = Livewire::test(Calendar::class, [
        'date' => '2026-05-15',
    ]);

    /** @var Testable<Calendar> $calendar */
    $calendar = $calendar;

    /** @var Calendar $calendarComponent */
    $calendarComponent = $calendar->instance();

    expect($calendarComponent->calendarDays())
        ->toHaveCount(35)
        ->and($calendarComponent->calendarDays()[0]['date'])->toBe('2026-04-27')
        ->and($calendarComponent->calendarDays()[34]['date'])->toBe('2026-05-31');
});

it('uses the configured first day of the week', function (): void {
    config(['calendar.first_day_of_week' => 'Sunday']);

    $calendar = Livewire::test(Calendar::class, [
        'date' => '2026-05-15',
    ]);

    /** @var Testable<Calendar> $calendar */
    $calendar = $calendar;

    /** @var Calendar $calendarComponent */
    $calendarComponent = $calendar->instance();

    expect($calendarComponent->calendarDays()[0]['date'])->toBe('2026-04-26');
});

it('highlights today when enabled', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-15'));

    try {
        $html = Blade::render(
            '<x-calendar::timeline :days="$days" />',
            ['days' => [['date' => '2026-05-15', 'label' => '15']]],
        );

        expect($html)->toContain('ring-blue-500');
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the reusable planner Livewire component', function (): void {
    Livewire::test(Planner::class, [
        'events' => [[
            'id' => 1,
            'title' => 'JOB-001',
            'subtitle' => 'Equipment delivery',
            'start' => '2026-05-15',
            'end' => '2026-05-15',
            'colour' => '#2563eb',
        ]],
        'date' => '2026-05-15',
    ])
        ->assertSee('JOB-001')
        ->assertSee('Equipment delivery');
});

it('renders the planner empty state when there are no jobs', function (): void {
    Livewire::test(Planner::class, [
        'events' => [],
    ])
        ->assertSee('No jobs in this window')
        ->assertSee('No quotes or orders fall within the selected dates and filters.')
        ->assertSee('s-empty-icon flex justify-center');
});

it('starts every planner zoom at today and renders the production key entries', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-15'));

    try {
        $planner = Livewire::test(Planner::class, [
            'events' => [],
        ]);

        /** @var Testable<Planner> $planner */
        $planner = $planner;

        /** @var Planner $plannerComponent */
        $plannerComponent = $planner->instance();

        $planner->call('setZoom', '1w');

        expect($plannerComponent->visibleDays()[0]->toDateString())->toBe('2026-05-15');

        $planner->call('nextPeriod')
            ->assertSet('date', '2026-05-22')
            ->assertSet('preset', '');
        $planner->call('previousPeriod')->assertSet('date', '2026-05-15');
        $planner->call('applyPreset', 'next_30_days')
            ->assertSet('date', '2026-05-15')
            ->assertSet('preset', 'next_30_days');
        $planner->call('applyPreset', 'this_month')
            ->assertSet('date', '2026-05-01')
            ->assertSet('preset', 'this_month');
        $planner->call('applyPreset', 'next_month')
            ->assertSet('date', '2026-06-01')
            ->assertSet('preset', 'next_month');
        $planner->set('date', '')->assertSet('date', '2026-05-15');
        $planner->call('today')->assertSet('date', '2026-05-15');

        $planner->call('applyPreset', 'next_30_days');
        $planner->call('setZoom', '1m');

        expect($plannerComponent->visibleDays())->toHaveCount(30)
            ->and($plannerComponent->visibleDays()[0]->toDateString())->toBe('2026-05-15');

        $planner->assertDontSee('Key');
        expect($planner->html())->not->toContain('truck');
        $planner->assertSee('s-datepicker')
            ->assertSee('Pick a date')
            ->assertSee('s-datepicker-dropdown-floating')
            ->assertSee('todayString()');
        expect($planner->html())->toContain('$wire.set(\'date\', $event.detail || \'2026-05-15\')');
        $planner->assertSee('wire:loading.remove');
        $planner->assertSee('animate-pulse');
        $planner->assertSeeInOrder([
            'Provisional', 'Reserved', 'Lost', 'Dead', 'Postponed', 'Active',
            'Dispatched', 'On Hire', 'Returned', 'Checked', 'Complete', 'Cancelled',
            'Collecting', 'Returning', 'Delivery', 'In Use', 'Collection', 'Today',
            'Extends beyond view',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

it('loads the planner legend from package configuration', function (): void {
    $legend = config('calendar.planner.legend');

    expect($legend)->toHaveCount(19)
        ->and($legend[0]['label'])->toBe('Provisional')
        ->and($legend[18]['label'])->toBe('Extends beyond view');
});
