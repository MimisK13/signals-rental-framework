<?php

use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityPresentation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['stock.access', 'availability.view']);

    Carbon::setTestNow(Carbon::parse('2026-06-28 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the product bookings page for a user with availability.view', function () {
    $product = Product::factory()->create(['name' => 'MAC Aura XIP']);

    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'A-05476',
    ]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-28', 'UTC'), 4, 4)
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

    $this->actingAs($this->user);

    Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-06-28',
        'days' => 28,
    ])
        ->set('storeId', $this->store->id)
        ->assertOk()
        ->assertSee('Product Bookings')
        ->assertSee('Equipment Availability')
        ->assertSee('MAC Aura XIP')
        ->assertSee('Opportunities')
        ->assertSee('Assets')
        ->assertSee('A-05476')
        ->assertSee('Available')
        ->assertSeeHtml('s-avail-group-table-scroll')
        ->assertSeeHtml('s-avail-grid-sticky-chrome')
        ->assertSee('quantity on sub-rent');
});

it('shifts the bookings date window from the grid header navigation', function () {
    $product = Product::factory()->create(['name' => 'Nav Product']);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-28', 'UTC'), 5, 5)
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

    $this->actingAs($this->user);

    Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-06-28',
        'days' => 7,
    ])
        ->set('storeId', $this->store->id)
        ->call('nextPeriod')
        ->assertRedirect(route('availability.bookings', [
            'product' => $product->id,
            'from' => '2026-07-05',
            'days' => 7,
            'store' => $this->store->id,
        ]));
});

it('links back to the main availability page preserving filters', function () {
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'name' => 'MAC Aura XIP',
        'product_group_id' => $group->id,
    ]);

    $this->actingAs($this->user);

    $backUrl = route('availability.index', [
        'store' => $this->store->id,
        'from' => '2026-06-28',
        'days' => 28,
        'group' => $group->id,
    ]);

    Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-06-28',
        'days' => 28,
    ])
        ->set('storeId', $this->store->id)
        ->assertSeeHtml('href="'.e($backUrl).'"');
});

it('forbids the product bookings page without availability.view', function () {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create());

    Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-06-28',
        'days' => 28,
    ])->assertForbidden();
});

it('shows opportunity rows for bulk demands in the window', function () {
    $product = Product::factory()->create(['name' => 'Bulk Speaker']);
    $opportunity = Opportunity::factory()->create([
        'subject' => 'Ballet Boyz Tour',
        'number' => 'J-019371',
        'starts_at' => Carbon::parse('2026-04-27 10:00:00', 'UTC'),
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    Demand::factory()->window(
        Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        Carbon::parse('2026-07-02 17:00:00', 'UTC'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'source_type' => 'opportunity_item',
        'source_id' => $item->id,
        'quantity' => 3,
    ]);

    $this->actingAs($this->user);

    Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-06-28',
        'days' => 28,
    ])
        ->set('storeId', $this->store->id)
        ->assertSee('Ballet Boyz Tour')
        ->assertSee('J-019371');
});

it('colours bulk opportunity rows by delivery in-use and collection phases', function () {
    $presentation = app(OpportunityPresentation::class);
    $product = Product::factory()->create(['name' => 'Phase Bulk Product']);

    $start = Carbon::parse('2026-07-01 09:00:00', 'UTC');
    $end = Carbon::parse('2026-07-04 17:00:00', 'UTC');

    $opportunity = Opportunity::factory()->order()->create([
        'starts_at' => $start,
        'ends_at' => $end,
        'deliver_starts_at' => $start->copy(),
        'deliver_ends_at' => $start->copy()->setTime(12, 0),
        'show_starts_at' => $start->copy()->addDay()->setTime(9, 0),
        'show_ends_at' => $end->copy()->subDay()->setTime(17, 0),
        'collect_starts_at' => $end->copy()->setTime(9, 0),
        'collect_ends_at' => $end->copy(),
        'subject' => 'Untitled booking',
        'number' => null,
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    Demand::factory()->window(
        Carbon::parse('2026-07-01 09:00:00', 'UTC'),
        Carbon::parse('2026-07-04 17:00:00', 'UTC'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'source_type' => 'opportunity_item',
        'source_id' => $item->id,
        'quantity' => 2,
        'metadata' => [],
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-07-01',
        'days' => 7,
    ])
        ->set('storeId', $this->store->id)
        ->get('opportunityRows');

    $row = collect($rows)->first();

    expect($row)->not->toBeNull()
        ->and($row['label'])->toBe('01/07 - Untitled booking');

    $cells = collect($row['cells']);

    expect($cells->firstWhere('date', '2026-07-01')['colour'])->toBe($presentation->phaseColour('delivery'))
        ->and($cells->firstWhere('date', '2026-07-02')['colour'])->toBe($presentation->phaseColour('in-use'))
        ->and($cells->firstWhere('date', '2026-07-04')['colour'])->toBe($presentation->phaseColour('collection'))
        ->and($cells->firstWhere('date', '2026-07-01')['colour'])->not->toBe('#3B82F6');
});

it('colours registry-labelled booking rows and asset rows from the parent opportunity phase', function () {
    $presentation = app(OpportunityPresentation::class);
    $product = Product::factory()->create(['name' => 'Phase Asset Product']);

    $start = Carbon::parse('2026-07-01 09:00:00', 'UTC');
    $end = Carbon::parse('2026-07-04 17:00:00', 'UTC');

    $opportunity = Opportunity::factory()->order()->create([
        'starts_at' => $start,
        'ends_at' => $end,
        'deliver_starts_at' => $start->copy(),
        'deliver_ends_at' => $start->copy()->setTime(12, 0),
        'show_starts_at' => $start->copy()->addDay()->setTime(9, 0),
        'show_ends_at' => $end->copy()->subDay()->setTime(17, 0),
        'collect_starts_at' => $end->copy()->setTime(9, 0),
        'collect_ends_at' => $end->copy(),
        'subject' => 'Untitled booking',
        'number' => null,
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'A-PHASE-01',
    ]);

    Demand::factory()->serialised()->window(
        Carbon::parse('2026-07-01 09:00:00', 'UTC'),
        Carbon::parse('2026-07-04 17:00:00', 'UTC'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_id' => $asset->id,
        'source_type' => 'opportunity_item',
        'source_id' => $item->id,
        'quantity' => 1,
        'metadata' => [],
    ]);

    Demand::factory()->window(
        Carbon::parse('2026-07-01 09:00:00', 'UTC'),
        Carbon::parse('2026-07-04 17:00:00', 'UTC'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'source_type' => 'opportunity_item',
        'source_id' => $item->id,
        'quantity' => 1,
        'metadata' => [],
    ]);

    $this->actingAs($this->user);

    $component = Volt::test('availability.bookings', [
        'product' => $product,
        'from' => '2026-07-01',
        'days' => 7,
    ])->set('storeId', $this->store->id);

    $gantt = $component->get('gantt');
    $bookingRow = collect($component->get('opportunityRows'))->first();
    $assetRow = collect($component->get('assetRows'))->firstWhere('label', 'A-PHASE-01');
    $bulkBar = collect($gantt->demands)->first(fn ($bar): bool => $bar->asset_id === null);

    expect($bulkBar)->not->toBeNull()
        ->and($bulkBar->source_name)->toBe('Bookings #'.$item->id)
        ->and($bookingRow['label'])->toBe('01/07 - Untitled booking');

    $bookingDelivery = collect($bookingRow['cells'])->firstWhere('date', '2026-07-01');
    $assetDelivery = collect($assetRow['cells'])->firstWhere('date', '2026-07-01');
    $assetInUse = collect($assetRow['cells'])->firstWhere('date', '2026-07-02');

    expect($bookingDelivery['colour'])->toBe($presentation->phaseColour('delivery'))
        ->and($assetDelivery['colour'])->toBe($presentation->phaseColour('delivery'))
        ->and($assetInUse['colour'])->toBe($presentation->phaseColour('in-use'))
        ->and($assetDelivery['colour'])->not->toBe('#3B82F6');
});

it('links from the group grid day cells to the bookings page using the view start date', function () {
    $product = Product::factory()->create(['name' => 'Linked Product']);
    $group = ProductGroup::factory()->create();
    $product->update(['product_group_id' => $group->id]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-28', 'UTC'), 5, 5)
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

    $this->actingAs($this->user);

    $cellUrl = route('availability.bookings', [
        'product' => $product->id,
        'from' => '2026-06-28',
        'days' => 30,
        'store' => $this->store->id,
    ]);

    Volt::test('availability.index')
        ->set('groupId', $group->id)
        ->set('storeId', $this->store->id)
        ->set('from', '2026-06-28')
        ->assertSeeHtml('href="'.e($cellUrl).'"')
        ->assertSeeHtml('s-avail-group-cell-link');
});

it('links from the group grid product name to the bookings page', function () {
    $product = Product::factory()->create(['name' => 'Linked Product']);
    $group = ProductGroup::factory()->create();
    $product->update(['product_group_id' => $group->id]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-28', 'UTC'), 5, 5)
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

    $this->actingAs($this->user);

    $expectedUrl = route('availability.bookings', [
        'product' => $product->id,
        'from' => '2026-06-28',
        'days' => 30,
        'store' => $this->store->id,
    ]);

    Volt::test('availability.index')
        ->set('groupId', $group->id)
        ->set('storeId', $this->store->id)
        ->set('from', '2026-06-28')
        ->assertSeeHtml('href="'.e($expectedUrl).'"')
        ->assertSeeHtml('href="'.e(route('products.show', $product)).'"')
        ->assertSeeHtml('target="_blank"');
});
