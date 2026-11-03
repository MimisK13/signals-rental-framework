<?php

namespace Database\Seeders;

use App\Domain\Administration\Models\User;
use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Members\Models\Member;
use App\Domain\Products\Enums\ProductType;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityGroup;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityGroupData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Enums\LineItemTransactionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class HeavyQuotationSeeder extends Seeder
{
    private const string REFERENCE = 'EXP-HEAVY-QUOTATION';

    private const string TAG = 'experimental-heavy-quotation';

    private const int STOCK_HEADROOM = 10;

    private const string STOCK_MARKER = 'Experimental heavy quotation fixture stock';

    /** @var array<string, int> */
    private const array CONSTRAINED_STOCK = [
        'AUD-SM58' => 1,
        'LMH-ESPRITE' => 2,
        'STG-DECK-8X4' => 0,
    ];

    public function run(): void
    {
        $user = User::query()->whereNotNull('member_id')->first();
        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();
        $products = Product::query()
            ->where('is_active', true)
            ->whereIn('product_type', [
                ProductType::Rental->value,
                ProductType::Sale->value,
                ProductType::Service->value,
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'sku', 'product_type', 'stock_method']);
        $currency = app(CurrencyService::class)->baseCurrencyCode();

        if ($user === null || $store === null || $products->isEmpty()) {
            throw new RuntimeException('Heavy quotation seeding requires an existing user, store, and active product.');
        }

        $this->ensureFixtureStock($store, $products);

        if (Opportunity::query()->where('reference', self::REFERENCE)->exists()) {
            $this->command?->info('Heavy quotation and fixture stock already exist; nothing was seeded.');

            return;
        }

        $customer = Member::query()
            ->where('membership_type', 'Organisation')
            ->where('is_active', true)
            ->first();

        Auth::setUser($user);

        try {
            $created = (new CreateOpportunity)(CreateOpportunityData::from([
                'subject' => 'Experimental Heavy Production Quotation',
                'reference' => self::REFERENCE,
                'member_id' => $customer?->id,
                'store_id' => $store->id,
                'owned_by' => $user->member_id,
                'starts_at' => '2027-06-01T09:00:00Z',
                'ends_at' => '2027-06-05T17:00:00Z',
                'planning_starts_at' => '2027-05-31T09:00:00Z',
                'planning_ends_at' => '2027-06-06T17:00:00Z',
                'currency' => $currency,
                'tag_list' => [self::TAG],
            ]));

            $opportunity = Opportunity::query()->findOrFail($created->id);

            foreach (range(1, 12) as $groupNumber) {
                $groupName = sprintf('Production Package %02d', $groupNumber);

                (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
                    'name' => $groupName,
                ]));

                $opportunity->refresh();
                $group = $opportunity->items()
                    ->where('item_type', OpportunityItemType::Group->value)
                    ->where('name', $groupName)
                    ->latest('id')
                    ->firstOrFail();

                foreach (range(0, 17) as $lineNumber) {
                    $product = $products[($groupNumber * 18 + $lineNumber) % $products->count()];
                    $transactionType = match ($product->product_type) {
                        ProductType::Sale => LineItemTransactionType::Sale,
                        ProductType::Service => LineItemTransactionType::Service,
                        default => LineItemTransactionType::Rental,
                    };

                    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
                        'name' => $product->name,
                        'itemable_id' => $product->id,
                        'itemable_type' => Product::class,
                        'item_type' => OpportunityItemType::Product->value,
                        'parent_path' => $group->path,
                        'quantity' => (string) (($lineNumber % 4) + 1),
                        'transaction_type' => $transactionType->value,
                        'starts_at' => '2027-06-01T09:00:00Z',
                        'ends_at' => '2027-06-05T17:00:00Z',
                        'planning_starts_at' => '2027-05-31T09:00:00Z',
                        'planning_ends_at' => '2027-06-06T17:00:00Z',
                        'unit_price' => 2500 + ($lineNumber * 375),
                        'currency' => $currency,
                        'materialize_included_accessories' => false,
                    ]));
                }
            }

            (new ConvertToQuotation)($opportunity->refresh());

            $this->command?->info('Seeded one heavy quotation with 12 groups and 216 product lines.');
        } finally {
            Auth::forgetUser();
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function ensureFixtureStock(Store $store, Collection $products): void
    {
        $requiredByProduct = [];

        foreach (range(1, 12) as $groupNumber) {
            foreach (range(0, 17) as $lineNumber) {
                $product = $products[($groupNumber * 18 + $lineNumber) % $products->count()];
                $requiredByProduct[$product->id] = ($requiredByProduct[$product->id] ?? 0)
                    + (($lineNumber % 4) + 1);
            }
        }

        foreach ($products as $product) {
            $required = $requiredByProduct[$product->id] ?? 0;
            $target = isset(self::CONSTRAINED_STOCK[$product->sku])
                ? self::CONSTRAINED_STOCK[$product->sku]
                : $required + self::STOCK_HEADROOM;
            $stockQuery = StockLevel::query()
                ->where('product_id', $product->id)
                ->where('store_id', $store->id);
            $managedQuantity = (int) (clone $stockQuery)
                ->where('item_name', self::STOCK_MARKER)
                ->sum('quantity_held');
            $externalQuantity = (int) (clone $stockQuery)
                ->where(fn ($query) => $query
                    ->whereNull('item_name')
                    ->orWhere('item_name', '!=', self::STOCK_MARKER))
                ->sum('quantity_held');
            $desiredManagedQuantity = max(0, $target - $externalQuantity);

            if ($managedQuantity === $desiredManagedQuantity) {
                continue;
            }

            (clone $stockQuery)
                ->where('item_name', self::STOCK_MARKER)
                ->delete();

            if ($product->stock_method === StockMethod::Serialised) {
                StockLevel::factory()
                    ->serialised()
                    ->count($desiredManagedQuantity)
                    ->createQuietly([
                        'product_id' => $product->id,
                        'store_id' => $store->id,
                        'item_name' => self::STOCK_MARKER,
                    ]);

                continue;
            }

            if ($desiredManagedQuantity === 0) {
                continue;
            }

            StockLevel::factory()->bulk()->createQuietly([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity_held' => $desiredManagedQuantity,
                'item_name' => self::STOCK_MARKER,
            ]);
        }
    }
}
