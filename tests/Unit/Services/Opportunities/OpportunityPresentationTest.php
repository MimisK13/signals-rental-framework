<?php

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->presentation = app(OpportunityPresentation::class);
});

it('exposes planner phase colours verbatim', function () {
    expect($this->presentation->phaseColours())->toBe([
        'delivery' => '#3498db',
        'in-use' => '#8e95a6',
        'collection' => '#27ae60',
    ]);
});

it('exposes customer flag colours and icons', function () {
    expect($this->presentation->customerBadgeColours())->toBe([
        'collecting' => '#27ae60',
        'returning' => '#17a2b8',
    ])->and($this->presentation->customerFlagDefinitions()['collecting']['icon'])->toBe('truck')
        ->and($this->presentation->customerFlagDefinitions()['returning']['icon'])->toBe('truck');
});

it('delegates status colour and label to OpportunityStatus', function () {
    $status = OpportunityStatus::OrderOnHire;

    expect($this->presentation->statusColour($status))->toBe($status->colour())
        ->and($this->presentation->statusLabel($status))->toBe($status->label());
});

it('builds the planner legend with statuses, flags, phases, and chrome entries', function () {
    $legend = $this->presentation->legend();

    $provisional = collect($legend)->first(fn (array $entry): bool => $entry['label'] === 'Provisional');
    $inUse = collect($legend)->first(fn (array $entry): bool => $entry['label'] === 'In Use');
    $collecting = collect($legend)->first(fn (array $entry): bool => $entry['label'] === __('Collecting'));

    expect($provisional)->not->toBeNull()
        ->and($provisional['colour'])->toBe(OpportunityStatus::QuotationProvisional->colour())
        ->and($inUse)->not->toBeNull()
        ->and($inUse['colour'])->toBe('#8e95a6')
        ->and($collecting)->not->toBeNull()
        ->and($collecting['icon'])->toBe('truck')
        ->and(collect($legend)->first(fn (array $entry): bool => $entry['label'] === __('Today'))['border'] ?? false)->toBeTrue();
});

it('resolves delivery, in-use, and collection phases for a day', function () {
    $opportunity = Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
        'deliver_starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'deliver_ends_at' => Carbon::parse('2026-06-22 12:00:00', 'UTC'),
        'show_starts_at' => Carbon::parse('2026-06-23 09:00:00', 'UTC'),
        'show_ends_at' => Carbon::parse('2026-06-24 17:00:00', 'UTC'),
        'collect_starts_at' => Carbon::parse('2026-06-25 09:00:00', 'UTC'),
        'collect_ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $deliveryDay = Carbon::parse('2026-06-22', 'UTC');
    $inUseDay = Carbon::parse('2026-06-23', 'UTC');
    $collectionDay = Carbon::parse('2026-06-25', 'UTC');

    expect($this->presentation->phaseForDay($opportunity, $deliveryDay->startOfDay(), $deliveryDay->copy()->endOfDay()))->toBe('delivery')
        ->and($this->presentation->phaseForDay($opportunity, $inUseDay->startOfDay(), $inUseDay->copy()->endOfDay()))->toBe('in-use')
        ->and($this->presentation->phaseForDay($opportunity, $collectionDay->startOfDay(), $collectionDay->copy()->endOfDay()))->toBe('collection')
        ->and($this->presentation->phaseColour('delivery'))->toBe('#3498db');
});
