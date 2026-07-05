<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Shortages\CostApportionmentContextBuilder;
use App\Services\Shortages\Strategies\PrimaryJobCostApportionmentStrategy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function opportunityItemWithDates(string $startsAt, string $endsAt): array
{
    $store = Store::factory()->create(['timezone' => 'UTC']);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Apportionment participant',
        'store_id' => $store->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Participant line',
        'quantity' => '1',
        'unit_price' => 1000,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]));

    return [$opportunity, $opportunity->items()->firstOrFail()];
}

/**
 * Mirrors the migration backfill for legacy rows missing primary_opportunity_id.
 */
function runPrimaryOpportunityBackfill(): void
{
    DB::table('shortage_resolutions')
        ->whereNotNull('virtual_stock_intake_id')
        ->orderBy('id')
        ->chunkById(100, function ($resolutions): void {
            foreach ($resolutions as $resolution) {
                /** @var array<string, mixed>|null $metadata */
                $metadata = json_decode($resolution->metadata ?? 'null', true);

                if (! is_array($metadata) || ! isset($metadata['opportunity_id'])) {
                    continue;
                }

                $opportunityId = (int) $metadata['opportunity_id'];

                if ($opportunityId <= 0) {
                    continue;
                }

                DB::table('virtual_stock_intakes')
                    ->where('id', $resolution->virtual_stock_intake_id)
                    ->whereNull('primary_opportunity_id')
                    ->update(['primary_opportunity_id' => $opportunityId]);
            }
        });
}

/**
 * @return array<string, mixed>
 */
function durationApportionFixture(): array
{
    $intake = createTestVirtualStockIntake([
        'quantity' => 1,
        'unit_cost' => 900,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
    ]);

    VirtualStockIntake::query()
        ->whereKey($intake->id)
        ->update(['cost_apportionment_strategy' => 'proportional_quantity_duration']);
    $intake->refresh();

    [$oppA, $itemA] = opportunityItemWithDates('2026-08-02T09:00:00Z', '2026-08-05T17:00:00Z');
    [$oppB, $itemB] = opportunityItemWithDates('2026-08-03T09:00:00Z', '2026-08-04T17:00:00Z');

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $oppA->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $itemA->id,
        'quantity_allocated' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $itemB->id,
        'quantity_allocated' => 1,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    return compact('intake', 'oppA', 'oppB', 'itemA', 'itemB');
}

it('backfills primary_opportunity_id from shortage resolution metadata', function () {
    $primary = Opportunity::factory()->create();
    $intake = VirtualStockIntake::factory()->confirmed()->create(['primary_opportunity_id' => null]);

    ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $primary->id],
    ]);

    runPrimaryOpportunityBackfill();

    expect($intake->refresh()->primary_opportunity_id)->toBe($primary->id);
});

it('leaves primary_opportunity_id null when resolution metadata has no opportunity_id', function () {
    $intake = VirtualStockIntake::factory()->confirmed()->create(['primary_opportunity_id' => null]);

    ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['supplier_id' => 42],
    ]);

    runPrimaryOpportunityBackfill();

    expect($intake->refresh()->primary_opportunity_id)->toBeNull();
});

it('prefers persisted primary_opportunity_id over resolution metadata and first participant', function () {
    $persistedPrimary = Opportunity::factory()->create();
    $metadataPrimary = Opportunity::factory()->create();
    $firstParticipant = Opportunity::factory()->create();

    $intake = VirtualStockIntake::factory()->confirmed()->create([
        'quantity' => 2,
        'unit_cost' => 500,
        'primary_opportunity_id' => $persistedPrimary->id,
    ]);

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $metadataPrimary->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($firstParticipant)->create()->id,
        'quantity_allocated' => 1,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($persistedPrimary)->create()->id,
        'quantity_allocated' => 1,
    ]);

    $context = app(CostApportionmentContextBuilder::class)->build($intake);
    $allocations = app(PrimaryJobCostApportionmentStrategy::class)->calculate($context);

    expect($context->primaryOpportunityId)->toBe($persistedPrimary->id)
        ->and($allocations[$persistedPrimary->id])->toBe(1000)
        ->and($allocations[$firstParticipant->id])->toBe(0)
        ->and($allocations)->not->toHaveKey($metadataPrimary->id);
});

it('falls back to resolution metadata when primary_opportunity_id column is null', function () {
    $metadataPrimary = Opportunity::factory()->create();
    $intake = VirtualStockIntake::factory()->confirmed()->create([
        'quantity' => 1,
        'unit_cost' => 400,
        'primary_opportunity_id' => null,
    ]);

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $metadataPrimary->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($metadataPrimary)->create()->id,
        'quantity_allocated' => 1,
    ]);

    $context = app(CostApportionmentContextBuilder::class)->build($intake);

    expect($context->primaryOpportunityId)->toBe($metadataPrimary->id);
});

it('recalculates duration apportionment when a participating item date changes', function () {
    ['intake' => $intake, 'oppA' => $oppA, 'oppB' => $oppB, 'itemA' => $itemA] = durationApportionFixture();

    $costA = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->where('opportunity_id', $oppA->id)
        ->firstOrFail();
    $costB = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->where('opportunity_id', $oppB->id)
        ->firstOrFail();

    expect($costA->amount)->toBe(720)
        ->and($costB->amount)->toBe(180);

    app(ChangeItemDates::class)(
        $itemA,
        ChangeItemDatesData::from([
            'starts_at' => '2026-08-02T09:00:00Z',
            'ends_at' => '2026-08-03T17:00:00Z',
        ]),
    );

    $costA->refresh();
    $costB->refresh();

    expect($costA->amount)->toBe(600)
        ->and($costB->amount)->toBe(300);
});

it('does not recalculate apportionment when a non-participating item date changes', function () {
    ['intake' => $intake, 'oppA' => $oppA, 'oppB' => $oppB] = durationApportionFixture();

    $before = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->orderBy('opportunity_id')
        ->get()
        ->map(fn (OpportunityCost $cost): array => [
            'opportunity_id' => $cost->opportunity_id,
            'amount' => $cost->amount,
        ])
        ->all();

    [$otherOpp, $otherItem] = opportunityItemWithDates('2026-08-10T00:00:00Z', '2026-08-12T00:00:00Z');

    app(ChangeItemDates::class)(
        $otherItem,
        ChangeItemDatesData::from([
            'starts_at' => '2026-08-11T00:00:00Z',
            'ends_at' => '2026-08-13T00:00:00Z',
        ]),
    );

    $after = OpportunityCost::query()
        ->where('virtual_stock_intake_id', $intake->id)
        ->orderBy('opportunity_id')
        ->get()
        ->map(fn (OpportunityCost $cost): array => [
            'opportunity_id' => $cost->opportunity_id,
            'amount' => $cost->amount,
        ])
        ->all();

    expect($after)->toBe($before);
});

it('does not double-apportion costs when verbs replays after a date-driven recalc', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-05T10:00:00Z'));

    ['intake' => $intake, 'itemA' => $itemA] = durationApportionFixture();

    app(ChangeItemDates::class)(
        $itemA,
        ChangeItemDatesData::from([
            'starts_at' => '2026-08-02T09:00:00Z',
            'ends_at' => '2026-08-03T17:00:00Z',
        ]),
    );

    $costsBefore = OpportunityCost::query()->orderBy('id')->get()->map(
        fn (OpportunityCost $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    OpportunityCost::query()->delete();

    Verbs::replay();

    $costsAfter = OpportunityCost::query()->orderBy('id')->get()->map(
        fn (OpportunityCost $row): array => $row->getAttributes(),
    )->all();

    expect($costsAfter)->toBe($costsBefore)
        ->and(verbEventCount())->toBe($eventCountBeforeReplay);

    Carbon::setTestNow();
});
