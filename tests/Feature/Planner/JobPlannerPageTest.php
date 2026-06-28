<?php

use App\Enums\OpportunityStatus;
use App\Models\Address;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Job Planner page (C1)
|--------------------------------------------------------------------------
|
| Overlap timeline of OPPORTUNITIES (jobs) across dates. Lane-packed bars with
| day-snapped Delivery / In-Use / Collection segments + Coll/Ret badges.
| Presets, zoom, filters. Gated on opportunities.access.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['name' => 'Main', 'is_default' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['opportunities.access']);

    Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Helper: an opportunity that overlaps the default 2-week window (today + 13d).
 *
 * @param  array<string, mixed>  $attributes
 */
function plannerJob(array $attributes = []): Opportunity
{
    return Opportunity::factory()->create(array_merge([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ], $attributes));
}

it('renders the planner page for a user with opportunities.access', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertSet('viewPeriod', '1m')
        ->assertSet('storeIds', [])
        ->assertSet('date', '2026-06-20')
        ->assertSet('includeOrders', true)
        ->assertSet('includeQuotations', true)
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(30))
        ->assertOk();
});

it('does not expose KPI summary cards or a gantt layout toggle', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertDontSee(__('Total Jobs'))
        ->assertDontSee(__('Total Value'))
        ->assertDontSee(__('Peak Day'))
        ->assertDontSee(__('Lanes Used'))
        ->assertDontSee(__('Gantt'))
        ->assertDontSee(__('Overlap'));
});

it('forbids the page for a user without opportunities.access', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('planner.index')->assertForbidden();
});

it('shows a job bar for a seeded opportunity in the window', function () {
    $job = Opportunity::factory()->quotation()->create([
        'subject' => 'Summer Festival Main Stage',
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('planner.index')->get('rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($job->id)
        ->and($rows[0]['subject'])->toBe('Summer Festival Main Stage')
        ->and($rows[0]['width'])->toBeGreaterThan(0)
        ->and($rows[0])->toHaveKey('lane')
        ->and($rows[0])->toHaveKey('datetime_range');
});

it('excludes opportunities outside the window', function () {
    Opportunity::factory()->quotation()->create([
        'starts_at' => Carbon::parse('2026-09-01 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-09-05 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows'))->toHaveCount(0);
});

it('excludes Draft-state opportunities (only quotations and orders are jobs)', function () {
    plannerJob();

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows'))->toHaveCount(0);
});

it('narrows results with the include-quotations / include-orders toggles', function () {
    Opportunity::factory()->quotation()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->set('includeQuotations', false)
        ->set('includeOrders', true)
        ->assertCount('rows', 1);

    Volt::test('planner.index')
        ->set('includeQuotations', false)
        ->set('includeOrders', false)
        ->assertCount('rows', 0);
});

it('filters by store', function () {
    $other = Store::factory()->create(['name' => 'Depot']);

    Opportunity::factory()->order()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'store_id' => $other->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->set('storeIds', [$this->store->id])
        ->assertCount('rows', 1);
});

it('filters by multiple stores via storeIds array', function () {
    $other = Store::factory()->create(['name' => 'Depot']);

    Opportunity::factory()->order()->create([
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'store_id' => $other->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->set('storeIds', [$this->store->id, $other->id])
        ->assertCount('rows', 2);
});

it('renders store tag-input, datepicker, toggles, and grid skeleton markup', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertSeeHtml('s-tag-input')
        ->assertSeeHtml('s-avail-filter-datepicker')
        ->assertSeeHtml('s-toggle')
        ->assertSeeHtml('wire:loading.remove')
        ->assertSeeHtml('planner-skeleton-lane');
});

it('searches across subject, number and member name case-insensitively', function () {
    $member = Member::factory()->create(['name' => 'Festival Hire Co']);

    Opportunity::factory()->order()->create([
        'subject' => 'Wedding Marquee',
        'member_id' => $member->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'subject' => 'Corporate Gala',
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    Volt::test('planner.index')->set('search', 'Marquee')->assertCount('rows', 1);
    Volt::test('planner.index')->set('search', 'festival hire')->assertCount('rows', 1);
    Volt::test('planner.index')->set('search', 'Nonexistent')->assertCount('rows', 0);
});

it('lane-packs overlapping jobs into distinct lanes', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-23 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-27 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('planner.index')->get('rows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toHaveKey('lane')
        ->and($rows[0]['lanes'])->toBe(2);

    $lanes = array_column($rows, 'lane');
    expect($lanes)->toEqualCanonicalizing([0, 1]);
});

it('marks jobs that extend beyond the visible window', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-10 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-08-01 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('planner.index')->get('rows');

    expect($rows[0]['extends_before'])->toBeTrue()
        ->and($rows[1]['extends_after'])->toBeTrue();
});

it('includes opportunity tags on planner rows', function () {
    Opportunity::factory()->order()->create([
        'tag_list' => ['VIP', 'Festival', 'Outdoor'],
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows')[0]['tags'])->toBe(['VIP', 'Festival', 'Outdoor']);
});

it('builds day-snapped Delivery / In-Use / Collection segments and customer badges', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
        'deliver_starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'deliver_ends_at' => Carbon::parse('2026-06-22 12:00:00', 'UTC'),
        'show_starts_at' => Carbon::parse('2026-06-23 09:00:00', 'UTC'),
        'show_ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
        'collect_starts_at' => Carbon::parse('2026-06-26 09:00:00', 'UTC'),
        'collect_ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
        'customer_collecting' => true,
        'customer_returning' => true,
    ]);

    $this->actingAs($this->user);

    $row = Volt::test('planner.index')->get('rows')[0];

    $phaseKeys = array_column($row['day_segments'], 'phase');

    expect($phaseKeys)->toContain('delivery')
        ->and($phaseKeys)->toContain('in-use')
        ->and($phaseKeys)->toContain('collection')
        ->and($row['customer_collecting'])->toBeTrue()
        ->and($row['customer_returning'])->toBeTrue();
});

it('snaps bar position to whole day columns in the window', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 09:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-24 17:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $row = Volt::test('planner.index')->get('rows')[0];

    expect($row['left'])->toBe((2 / 30) * 100)
        ->and($row['width'])->toBe((3 / 30) * 100);
});

it('adds a symmetric visual gap between consecutive touching jobs in the same lane', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-23 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-24 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-26 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $component = Volt::test('planner.index');
    $rows = $component->get('rows');
    $halfGap = 2;

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['lane'])->toBe(0)
        ->and($rows[1]['lane'])->toBe(0)
        ->and($rows[0]['gap_right_px'])->toBe($halfGap)
        ->and($rows[1]['gap_left_px'])->toBe($halfGap);
});

it('colours job cards by opportunity status', function () {
    Opportunity::factory()->quotation()->create([
        'status' => OpportunityStatus::QuotationProvisional->statusValue(),
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'status' => OpportunityStatus::OrderOnHire->statusValue(),
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $rows = Volt::test('planner.index')->get('rows');

    expect($rows[0]['colour'])->toBe(OpportunityStatus::QuotationProvisional->colour())
        ->and($rows[1]['colour'])->toBe(OpportunityStatus::OrderOnHire->colour())
        ->and($rows[0]['status_label'])->toBe('Provisional')
        ->and($rows[1]['status_label'])->toBe('On Hire');
});

it('builds legend swatches from opportunity statuses', function () {
    $this->actingAs($this->user);

    $legend = Volt::test('planner.index')->get('legend');
    $provisional = collect($legend)->first(fn (array $entry): bool => $entry['label'] === 'Provisional');
    $onHire = collect($legend)->first(fn (array $entry): bool => $entry['label'] === 'On Hire');

    expect($provisional)->not->toBeNull()
        ->and($onHire)->not->toBeNull()
        ->and($provisional['colour'])->toBe(OpportunityStatus::QuotationProvisional->colour())
        ->and($onHire['colour'])->toBe(OpportunityStatus::OrderOnHire->colour());
});

it('exposes customer badge colours matching the legend key', function () {
    $this->actingAs($this->user);

    $legend = Volt::test('planner.index')->get('legend');

    $collecting = collect($legend)->first(fn (array $entry): bool => $entry['label'] === __('Collecting'));
    $returning = collect($legend)->first(fn (array $entry): bool => $entry['label'] === __('Returning'));

    expect($collecting)->not->toBeNull()
        ->and($returning)->not->toBeNull()
        ->and($collecting['colour'])->toBe('#27ae60')
        ->and($returning['colour'])->toBe('#17a2b8')
        ->and($collecting['icon'])->toBe('truck')
        ->and($returning['icon'])->toBe('truck');
});

it('snaps day segment positions to track day columns', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-24 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $segment = Volt::test('planner.index')->get('rows')[0]['day_segments'][0];

    expect($segment['left'])->toBe((2 / 30) * 100)
        ->and($segment['width'])->toBe((1 / 30) * 100);
});

it('surfaces venue address on each row with member name fallback', function () {
    $venue = Member::factory()->organisation()->create(['name' => 'Town Hall']);
    Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $venue->id,
        'street' => '1 High Street',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'is_primary' => true,
    ]);

    Opportunity::factory()->order()->create([
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    expect(Volt::test('planner.index')->get('rows')[0]['address'])
        ->toBe('1 High Street, Bristol, BS1 1AA');
});

it('uses a distinct in-use phase colour separate from quotation status', function () {
    $this->actingAs($this->user);

    $legend = Volt::test('planner.index')->get('legend');
    $inUse = collect($legend)->first(fn (array $entry): bool => $entry['label'] === 'In Use');

    expect($inUse)->not->toBeNull()
        ->and($inUse['colour'])->toBe('#8e95a6')
        ->and($inUse['colour'])->not->toBe(OpportunityStatus::QuotationProvisional->colour());
});

it('steps the window forward, back and home', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->assertSet('date', '2026-06-20')
        ->call('nextPeriod')
        ->assertSet('date', '2026-07-20')
        ->call('previousPeriod')
        ->assertSet('date', '2026-06-20')
        ->call('nextPeriod')
        ->call('today')
        ->assertSet('date', '2026-06-20');
});

it('changes zoom window length', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->call('setZoom', '1w')
        ->assertSet('viewPeriod', '1w')
        ->assertSet('preset', '')
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(7));

    Volt::test('planner.index')
        ->call('setZoom', '1m')
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(30));
});

it('applies date-range presets', function () {
    $this->actingAs($this->user);

    Volt::test('planner.index')
        ->call('applyPreset', 'next_30_days')
        ->assertSet('date', '2026-06-20')
        ->assertSet('preset', 'next_30_days')
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(30));

    Volt::test('planner.index')
        ->call('applyPreset', 'this_month')
        ->assertSet('date', '2026-06-01')
        ->assertSet('preset', 'this_month')
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(30));

    Volt::test('planner.index')
        ->call('applyPreset', 'next_month')
        ->assertSet('date', '2026-07-01')
        ->assertSet('preset', 'next_month')
        ->tap(fn ($v) => expect($v->instance()->windowDays())->toBe(31));
});

it('counts jobs per calendar day for the heatmap row', function () {
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-24 18:00:00', 'UTC'),
    ]);
    Opportunity::factory()->order()->create([
        'starts_at' => Carbon::parse('2026-06-22 08:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-06-25 18:00:00', 'UTC'),
    ]);

    $this->actingAs($this->user);

    $component = Volt::test('planner.index');

    expect($component->get('jobCountsPerDay')['2026-06-22'])->toBe(2)
        ->and($component->get('jobCountsPerDay')['2026-06-25'])->toBe(1)
        ->and($component->get('jobCountHeatmapMax'))->toBe(2);
});
