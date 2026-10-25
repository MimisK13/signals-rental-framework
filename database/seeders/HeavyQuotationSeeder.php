<?php

namespace Database\Seeders;

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\ProductType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class HeavyQuotationSeeder extends Seeder
{
    private const string REFERENCE = 'EXP-HEAVY-QUOTATION';

    private const string TAG = 'experimental-heavy-quotation';

    public function run(): void
    {
        if (Opportunity::query()->where('reference', self::REFERENCE)->exists()) {
            $this->command?->info('Heavy quotation already exists; nothing was seeded.');

            return;
        }

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
            ->get(['id', 'name', 'product_type']);
        $currency = app(CurrencyService::class)->baseCurrencyCode();

        if ($user === null || $store === null || $products->count() < 18) {
            throw new RuntimeException('Heavy quotation seeding requires an existing user, store, and at least 18 active products.');
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
}
