<?php

use Illuminate\Support\Facades\Blade;

/**
 * D6 — when a date field has no value of its own, its calendar should open on the
 * `anchor` month (e.g. the chosen "starts at" month for an empty "ends at"),
 * rather than today.
 *
 * The calendar emits the anchor into its Alpine x-data attribute, so the literal
 * apostrophes are HTML-escaped (`'` → `&#039;`) in the rendered markup.
 */
it('anchors the visible month to the anchor date when nothing is selected', function () {
    $html = Blade::render('<x-signals.calendar :anchor="$anchor" />', ['anchor' => '2026-07-15']);

    expect($html)->toContain('new Date(&#039;2026-07-15&#039;).getMonth()')
        ->toContain('new Date(&#039;2026-07-15&#039;).getFullYear()');
});

it('prefers an explicit selected value over the anchor', function () {
    $html = Blade::render('<x-signals.calendar :selected="$selected" :anchor="$anchor" />', [
        'selected' => '2026-03-02',
        'anchor' => '2026-07-15',
    ]);

    expect($html)->toContain('new Date(&#039;2026-03-02&#039;).getMonth()');
    expect($html)->not->toContain('2026-07-15');
});

it('falls back to today when neither a value nor an anchor is given', function () {
    $html = Blade::render('<x-signals.calendar />');

    expect($html)->toContain('new Date().getMonth()');
});

it('flows the anchor through the datetime-input wrapper to its calendar', function () {
    $html = Blade::render('<x-signals.datetime-input :anchor="$anchor" />', ['anchor' => '2026-07-15']);

    expect($html)->toContain('new Date(&#039;2026-07-15&#039;).getMonth()');
});
