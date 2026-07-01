<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * D7 — the opportunity hire period must propagate to the line items that still
 * inherit it (new lines inherit at creation; a hire-period change follows through
 * to non-overridden lines, recomputing charge days/totals and re-snapshotting
 * availability demand), while manually date-overridden lines are left untouched.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    Auth::login($this->owner);
});

function hpOpportunity(int $storeId, string $start, string $end): Opportunity
{
    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Hire period propagation',
        'store_id' => $storeId,
        'starts_at' => $start,
        'ends_at' => $end,
    ]));

    return Opportunity::query()->whereKey($result->id)->firstOrFail();
}

it('(a) inherits the opportunity hire window on a newly added line', function () {
    $opportunity = hpOpportunity($this->store->id, '2026-07-01T09:00:00Z', '2026-07-06T17:00:00Z');

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Ad-hoc line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->items()->firstOrFail();

    expect($item->starts_at->equalTo($opportunity->starts_at))->toBeTrue()
        ->and($item->ends_at->equalTo($opportunity->ends_at))->toBeTrue();
});

it('(b) propagates a hire-period change to an inheriting line and recomputes its days/total', function () {
    $opportunity = hpOpportunity($this->store->id, '2026-07-01T09:00:00Z', '2026-07-06T17:00:00Z');

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Ad-hoc line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $itemBefore = $opportunity->items()->firstOrFail();
    $totalBefore = $itemBefore->total;

    // Move the hire window out to a longer span.
    (new UpdateOpportunity)($opportunity->fresh(), UpdateOpportunityData::from([
        'starts_at' => '2026-07-10T09:00:00Z',
        'ends_at' => '2026-07-20T17:00:00Z',
    ]));

    $itemAfter = OpportunityItem::query()->whereKey($itemBefore->id)->firstOrFail();
    $opportunity->refresh();

    expect($itemAfter->starts_at->toIso8601String())->toBe($opportunity->starts_at->toIso8601String())
        ->and($itemAfter->ends_at->toIso8601String())->toBe($opportunity->ends_at->toIso8601String())
        // Longer window ⇒ more chargeable days ⇒ larger hire total.
        ->and($itemAfter->total)->toBeGreaterThan($totalBefore);
});

it('(b) re-snapshots availability demand for an inheriting product line', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $opportunity = hpOpportunity($this->store->id, '2026-07-01T09:00:00Z', '2026-07-06T17:00:00Z');

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
    ]));

    $item = $opportunity->items()->firstOrFail();

    $demandBefore = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->firstOrFail();
    expect($demandBefore->starts_at->toDateString())->toBe('2026-07-01');

    (new UpdateOpportunity)($opportunity->fresh(), UpdateOpportunityData::from([
        'starts_at' => '2026-07-10T09:00:00Z',
        'ends_at' => '2026-07-20T17:00:00Z',
    ]));

    $demandAfter = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->firstOrFail();

    expect($demandAfter->starts_at->toDateString())->toBe('2026-07-10')
        ->and($demandAfter->ends_at->toDateString())->toBe('2026-07-20');
});

it('(b) leaves a manually date-overridden line untouched when the hire period changes', function () {
    $opportunity = hpOpportunity($this->store->id, '2026-07-01T09:00:00Z', '2026-07-06T17:00:00Z');

    // Inheriting line — should follow the opportunity window.
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Inheriting line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // Overridden line — given its own per-item window.
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Overridden line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $items = $opportunity->fresh()->items()->orderBy('path')->get();
    $inheriting = $items->firstWhere('name', 'Inheriting line');
    $overridden = $items->firstWhere('name', 'Overridden line');

    (new ChangeItemDates)($overridden, ChangeItemDatesData::from([
        'starts_at' => '2026-08-01T09:00:00Z',
        'ends_at' => '2026-08-03T17:00:00Z',
    ]));

    (new UpdateOpportunity)($opportunity->fresh(), UpdateOpportunityData::from([
        'starts_at' => '2026-07-10T09:00:00Z',
        'ends_at' => '2026-07-20T17:00:00Z',
    ]));

    $inheritingAfter = OpportunityItem::query()->whereKey($inheriting->id)->firstOrFail();
    $overriddenAfter = OpportunityItem::query()->whereKey($overridden->id)->firstOrFail();

    // The inheriting line follows the new window…
    expect($inheritingAfter->starts_at->toDateString())->toBe('2026-07-10')
        ->and($inheritingAfter->ends_at->toDateString())->toBe('2026-07-20')
        // …while the manual override is preserved.
        ->and($overriddenAfter->starts_at->toDateString())->toBe('2026-08-01')
        ->and($overriddenAfter->ends_at->toDateString())->toBe('2026-08-03');
});

it('(b) does not re-price inheriting lines when pricing is frozen by a deal price', function () {
    $opportunity = hpOpportunity($this->store->id, '2026-07-01T09:00:00Z', '2026-07-06T17:00:00Z');

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Ad-hoc line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->items()->firstOrFail();
    $datesBefore = [$item->starts_at->toDateString(), $item->ends_at->toDateString()];

    // Freeze pricing with a deal price.
    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from(['deal_total' => 5000]));
    expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

    (new UpdateOpportunity)($opportunity->fresh(), UpdateOpportunityData::from([
        'starts_at' => '2026-07-10T09:00:00Z',
        'ends_at' => '2026-07-20T17:00:00Z',
    ]));

    $itemAfter = OpportunityItem::query()->whereKey($item->id)->firstOrFail();

    // The frozen line keeps its snapshotted window (no propagation while frozen).
    expect([$itemAfter->starts_at->toDateString(), $itemAfter->ends_at->toDateString()])
        ->toBe($datesBefore);
});
