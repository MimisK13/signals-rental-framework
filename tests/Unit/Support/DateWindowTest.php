<?php

use App\Support\DateWindow;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('resolves 24h to today start of day through now', function () {
    Carbon::setTestNow('2026-07-01 15:30:00');

    $window = DateWindow::fromRequest('24h');

    expect($window->preset)->toBe('24h')
        ->and($window->from->toDateTimeString())->toBe('2026-07-01 00:00:00')
        ->and($window->to->toDateTimeString())->toBe('2026-07-01 15:30:00')
        ->and($window->fromDate())->toBe('2026-07-01')
        ->and($window->toDate())->toBe('2026-07-01');
});

it('resolves 7d to six days ago start of day through now', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $window = DateWindow::fromRequest('7d');

    expect($window->preset)->toBe('7d')
        ->and($window->from->toDateTimeString())->toBe('2026-06-25 00:00:00')
        ->and($window->to->toDateTimeString())->toBe('2026-07-01 12:00:00')
        ->and($window->fromDate())->toBe('2026-06-25')
        ->and($window->toDate())->toBe('2026-07-01');
});

it('resolves 30d to twenty nine days ago start of day through now', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');

    $window = DateWindow::fromRequest('30d');

    expect($window->preset)->toBe('30d')
        ->and($window->from->toDateTimeString())->toBe('2026-06-02 00:00:00')
        ->and($window->to->toDateTimeString())->toBe('2026-07-01 08:00:00')
        ->and($window->fromDate())->toBe('2026-06-02')
        ->and($window->toDate())->toBe('2026-07-01');
});

it('resolves custom dates to day aligned start and end of day', function () {
    $window = DateWindow::fromRequest('custom', '2026-06-01', '2026-06-03');

    expect($window->preset)->toBe('custom')
        ->and($window->from->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($window->to->toDateTimeString())->toBe('2026-06-03 23:59:59')
        ->and($window->fromDate())->toBe('2026-06-01')
        ->and($window->toDate())->toBe('2026-06-03');
});

it('throws when custom window is missing dates', function () {
    DateWindow::fromRequest('custom');
})->throws(InvalidArgumentException::class);

it('throws for unknown presets', function () {
    DateWindow::fromRequest('90d');
})->throws(InvalidArgumentException::class);
