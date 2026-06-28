<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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

        foreach ($bookings as $index => $booking) {
            $product = $products->get($booking['product']);
            $start = Carbon::parse($booking['start'].' 09:00:00', 'UTC');
            $end = Carbon::parse($booking['end'].' 17:00:00', 'UTC');

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
                'show_starts_at' => $start->copy()->addDay(),
                'show_ends_at' => $end->copy()->subDay(),
                'tag_list' => [self::TAG],
            ]);

            $item = OpportunityItem::factory()->for($opportunity)->create([
                'itemable_type' => Product::class,
                'itemable_id' => $product->id,
                'name' => $product->name,
                'quantity' => $booking['quantity'],
            ]);

            $resolver->syncDemands($item);

            $opportunityCount++;
            $lineItemCount++;
        }

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
                'Availability demo: %d opportunities, %d line items, %d products at %s (%s → %s).',
                $opportunityCount,
                $lineItemCount,
                $products->count(),
                $store->name,
                $rangeStart->toDateString(),
                $rangeEnd->toDateString(),
            ));
        }
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
