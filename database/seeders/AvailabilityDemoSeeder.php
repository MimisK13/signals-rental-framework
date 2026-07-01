<?php

namespace Database\Seeders;

use App\Actions\Opportunities\AllocateAsset;
use App\Data\Opportunities\AllocateAssetData;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\RateTransactionType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

/**
 * Demo availability demand for the By Group grid (browser / local demo only).
 *
 * NOT registered in DatabaseSeeder — run manually:
 * php artisan db:seed --class=AvailabilityDemoSeeder
 */
class AvailabilityDemoSeeder extends Seeder
{
    private const string TAG = 'availability-demo';

    /** @var array<string, int> */
    private const PRODUCT_STOCK = [
        'Demo - Moving Head Beam 230W' => 12,
        'Robe Esprite' => 8,
        'Martin MAC Aura XB' => 6,
    ];

    /** Moving-head products that receive serialised asset allocations in demo data. */
    private const MOVING_HEAD_PRODUCTS = [
        'Demo - Moving Head Beam 230W',
        'Robe Esprite',
        'Martin MAC Aura XB',
    ];

    /** Daily hire rate (minor units) attached to each demo product so it prices > £0. */
    private const PRODUCT_DAILY_RATE = [
        'Demo - Moving Head Beam 230W' => 6500,
        'Robe Esprite' => 12500,
        'Martin MAC Aura XB' => 9500,
    ];

    public function run(): void
    {
        Queue::fake();

        $this->purgeAvailabilityDemoData();

        $store = Store::query()->where('name', 'Main Warehouse')->first()
            ?? Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        if ($store === null) {
            if ($this->command !== null) {
                $this->command->error('No store found — seed stores first.');
            }

            return;
        }

        $group = ProductGroup::query()->where('name', 'Lighting - Moving Heads')->first();

        if ($group === null) {
            if ($this->command !== null) {
                $this->command->error('Product group "Lighting - Moving Heads" not found — seed product groups first.');
            }

            return;
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('name', array_keys(self::PRODUCT_STOCK))
            ->get()
            ->keyBy('name');

        foreach (self::PRODUCT_STOCK as $name => $targetStock) {
            if (! $products->has($name)) {
                if ($this->command !== null) {
                    $this->command->error("Product \"{$name}\" not found — run ProductSeeder / DemoDataSeeder first.");
                }

                return;
            }
        }

        foreach (self::PRODUCT_STOCK as $name => $targetStock) {
            $this->ensureSerialisedStock($products->get($name), $store->id, $targetStock);
            $this->ensureProductRate($products->get($name));
        }

        $member = Member::factory()->organisation()->create([
            'name' => 'Availability Demo Customer Ltd',
            'tag_list' => [self::TAG],
        ]);

        $rangeStart = Carbon::parse('2026-06-28 09:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-09-28 17:00:00', 'UTC');

        /** @var list<array{product: string, quantity: int, start: string, end: string, status: OpportunityStatus}> $bookings */
        $bookings = [
            // Moving heads — overlapping peaks to force warnings and shortages.
            ['product' => 'Demo - Moving Head Beam 230W', 'quantity' => 4, 'start' => '2026-07-01', 'end' => '2026-07-14', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Demo - Moving Head Beam 230W', 'quantity' => 6, 'start' => '2026-07-08', 'end' => '2026-07-22', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Demo - Moving Head Beam 230W', 'quantity' => 5, 'start' => '2026-07-20', 'end' => '2026-08-05', 'status' => OpportunityStatus::OrderOnHire],
            ['product' => 'Demo - Moving Head Beam 230W', 'quantity' => 3, 'start' => '2026-08-15', 'end' => '2026-08-28', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Demo - Moving Head Beam 230W', 'quantity' => 2, 'start' => '2026-09-05', 'end' => '2026-09-18', 'status' => OpportunityStatus::QuotationReserved],

            // Robe Esprite — stock 8; stacked hires for amber + red cells.
            ['product' => 'Robe Esprite', 'quantity' => 3, 'start' => '2026-07-03', 'end' => '2026-07-12', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Robe Esprite', 'quantity' => 4, 'start' => '2026-07-10', 'end' => '2026-07-24', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Robe Esprite', 'quantity' => 2, 'start' => '2026-08-01', 'end' => '2026-08-12', 'status' => OpportunityStatus::OrderDispatched],
            ['product' => 'Robe Esprite', 'quantity' => 6, 'start' => '2026-08-20', 'end' => '2026-09-02', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Robe Esprite', 'quantity' => 1, 'start' => '2026-09-10', 'end' => '2026-09-22', 'status' => OpportunityStatus::OrderActive],

            // MAC Aura — stock 6; tighter pool for booked / warning / shortage mix.
            ['product' => 'Martin MAC Aura XB', 'quantity' => 2, 'start' => '2026-07-05', 'end' => '2026-07-18', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Martin MAC Aura XB', 'quantity' => 3, 'start' => '2026-07-15', 'end' => '2026-07-30', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Martin MAC Aura XB', 'quantity' => 4, 'start' => '2026-07-22', 'end' => '2026-08-08', 'status' => OpportunityStatus::OrderOnHire],
            ['product' => 'Martin MAC Aura XB', 'quantity' => 2, 'start' => '2026-08-25', 'end' => '2026-09-08', 'status' => OpportunityStatus::OrderActive],
            ['product' => 'Martin MAC Aura XB', 'quantity' => 5, 'start' => '2026-09-12', 'end' => '2026-09-25', 'status' => OpportunityStatus::OrderActive],
        ];

        $resolver = app(OpportunityItemDemandResolver::class);
        $opportunityCount = 0;
        $lineItemCount = 0;
        $allocationCount = 0;

        $owner = User::query()->first();
        if ($owner !== null) {
            Auth::setUser($owner);
        }

        /** @var array<int, true> $allocatedStockLevelIds */
        $allocatedStockLevelIds = [];

        foreach ($bookings as $index => $booking) {
            $product = $products->get($booking['product']);
            $start = Carbon::parse($booking['start'].' 09:00:00', 'UTC');
            $end = Carbon::parse($booking['end'].' 17:00:00', 'UTC');
            $showStart = $start->copy()->addDay()->setTime(9, 0);
            $showEnd = $end->copy()->subDay()->setTime(17, 0);
            if ($showEnd->lessThan($showStart)) {
                $showEnd = $showStart->copy();
            }

            $state = $booking['status']->state();
            $factory = $state->value === 2
                ? Opportunity::factory()->order()
                : Opportunity::factory()->quotation();

            $opportunity = $factory->create([
                'member_id' => $member->id,
                'store_id' => $store->id,
                'state' => $state->value,
                'status' => $booking['status']->statusValue(),
                'subject' => sprintf('%s hire — demo %d', $product->name, $index + 1),
                'starts_at' => $start,
                'ends_at' => $end,
                'deliver_starts_at' => $start->copy(),
                'deliver_ends_at' => $start->copy()->setTime(12, 0),
                'show_starts_at' => $showStart,
                'show_ends_at' => $showEnd,
                'collect_starts_at' => $end->copy()->setTime(9, 0),
                'collect_ends_at' => $end->copy(),
                'tag_list' => [self::TAG],
            ]);

            $item = OpportunityItem::factory()->for($opportunity)->create([
                'itemable_type' => Product::class,
                'itemable_id' => $product->id,
                'name' => $product->name,
                'quantity' => $booking['quantity'],
            ]);

            $resolver->syncDemands($item);

            if ($owner !== null
                && in_array($booking['product'], self::MOVING_HEAD_PRODUCTS, true)
                && $booking['status']->phase() !== DemandPhase::Void) {
                $allocationCount += $this->allocateDemoAssets(
                    $item,
                    $store->id,
                    (int) $product->id,
                    min(2, $booking['quantity']),
                    $allocatedStockLevelIds,
                );
            }

            $opportunityCount++;
            $lineItemCount++;
        }

        Auth::forgetUser();

        $pipeline = app(RecalculationPipeline::class);

        foreach ($products as $product) {
            $pipeline->recalculate(
                (int) $product->id,
                (int) $store->id,
                $rangeStart->copy()->startOfDay(),
                $rangeEnd->copy()->endOfDay(),
            );
        }

        if ($this->command !== null) {
            $this->command->info(sprintf(
                'Availability demo: %d opportunities, %d line items, %d asset allocations, %d products at %s (%s → %s).',
                $opportunityCount,
                $lineItemCount,
                $allocationCount,
                $products->count(),
                $store->name,
                $rangeStart->toDateString(),
                $rangeEnd->toDateString(),
            ));
        }
    }

    /**
     * Allocate up to {@see $limit} serialised assets to one demo line item.
     *
     * @param  array<int, true>  $allocatedStockLevelIds
     */
    private function allocateDemoAssets(
        OpportunityItem $item,
        int $storeId,
        int $productId,
        int $limit,
        array &$allocatedStockLevelIds,
    ): int {
        if ($limit <= 0) {
            return 0;
        }

        $assets = StockLevel::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->whereNotNull('asset_number')
            ->whereNotIn('id', array_keys($allocatedStockLevelIds))
            ->orderBy('asset_number')
            ->limit($limit)
            ->get();

        $created = 0;

        foreach ($assets as $asset) {
            try {
                (new AllocateAsset)($item->refresh(), AllocateAssetData::from([
                    'stock_level_id' => $asset->id,
                ]));
                $allocatedStockLevelIds[$asset->id] = true;
                $created++;
            } catch (\Throwable) {
                // Skip assets that fail availability guards (overlapping windows).
            }
        }

        return $created;
    }

    private function purgeAvailabilityDemoData(): void
    {
        Opportunity::query()
            ->whereJsonContains('tag_list', self::TAG)
            ->each(fn (Opportunity $opportunity) => $opportunity->forceDelete());

        Member::query()
            ->whereJsonContains('tag_list', self::TAG)
            ->each(fn (Member $member) => $member->forceDelete());
    }

    /**
     * Ensure the demo product carries a non-zero rental {@see ProductRate} so the
     * rate engine prices it above £0 (the resolver otherwise returns null and the
     * line falls back to a £0.00 ad-hoc price). Uses the "Daily Rate" preset, a
     * store-agnostic all-stores rate at default priority.
     */
    private function ensureProductRate(Product $product): void
    {
        $dailyRate = RateDefinition::query()->where('preset_slug', 'daily-rate')->first();

        if ($dailyRate === null) {
            if ($this->command !== null) {
                $this->command->warn('Rate definition preset "daily-rate" not found — run RateDefinitionPresetSeeder first; demo products will price at £0.');
            }

            return;
        }

        ProductRate::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'store_id' => null,
                'transaction_type' => RateTransactionType::Rental,
            ],
            [
                'rate_definition_id' => $dailyRate->id,
                'price' => self::PRODUCT_DAILY_RATE[$product->name] ?? 5000,
                'currency' => 'GBP',
                'priority' => 0,
            ],
        );
    }

    private function ensureSerialisedStock(Product $product, int $storeId, int $targetStock): void
    {
        $existing = StockLevel::query()
            ->where('product_id', $product->id)
            ->where('store_id', $storeId)
            ->count();

        $toCreate = max(0, $targetStock - $existing);

        for ($i = 0; $i < $toCreate; $i++) {
            StockLevel::factory()->serialised()->create([
                'product_id' => $product->id,
                'store_id' => $storeId,
            ]);
        }
    }
}
