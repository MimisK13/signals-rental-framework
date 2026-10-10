<?php

namespace App\Console\Commands\Assets;

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\SubstituteAsset;
use App\Actions\Products\CreateStockLevel;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Data\Products\CreateStockLevelData;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\StockCategory;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetUsage;
use App\Models\Demand;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'assets:seed-financial-demo')]
class SeedFinancialDemoCommand extends Command
{
    protected $signature = 'assets:seed-financial-demo {--reset : Remove this command\'s demo records before reseeding}';

    protected $description = 'Seed deterministic local Asset Financials demo data';

    /** @var array<string, Asset> */
    private array $assets = [];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $user = User::query()->first();
        $store = Store::query()->where('is_default', true)->first() ?? Store::query()->first();
        $member = Member::query()->where('membership_type', 'organisation')->first()
            ?? Member::factory()->organisation()->create([
                'name' => 'Asset Financials Demo Customer',
            ]);

        if ($user === null || $store === null) {
            $this->components->error('A user, store, and organisation member are required before seeding the demo.');

            return self::FAILURE;
        }

        Auth::setUser($user);

        try {
            DB::transaction(function () use ($store, $member): void {
                if ($this->option('reset')) {
                    $this->resetDemoRecords();
                }

                $product = $this->resolveProduct();

                foreach (['001', '002', '003', '004'] as $number) {
                    $this->assets[$number] = $this->resolveAsset($product, $store, $number);
                }

                $this->seedExpenses();
                $this->seedBelowBreakEvenRentals($store, $member);
                $this->seedAboveBreakEvenRentals($store, $member);
                $this->seedAmbiguousRental($store, $member);
            });
        } finally {
            Auth::forgetUser();
        }

        $this->reportResults();

        return self::SUCCESS;
    }

    private function resetDemoRecords(): void
    {
        $opportunityIds = Opportunity::query()
            ->where('reference', 'like', 'ASSET-FIN-DEMO-%')
            ->pluck('id');
        $itemIds = OpportunityItem::query()->whereIn('opportunity_id', $opportunityIds)->pluck('id');

        Demand::query()
            ->where('source_type', OpportunityItem::class)
            ->whereIn('source_id', $itemIds)
            ->delete();
        AssetUsage::query()->whereIn('opportunity_item_id', $itemIds)->delete();
        OpportunityItemAsset::query()->whereIn('opportunity_item_id', $itemIds)->delete();
        Invoice::query()->where('reference', 'like', 'ASSET-FIN-DEMO-%')->get()->each->forceDelete();
        Opportunity::query()->whereIn('id', $opportunityIds)->get()->each->forceDelete();

        $swapStock = StockLevel::query()->where('asset_number', 'M32-005')->first();
        if ($swapStock !== null) {
            $swapAsset = $swapStock->asset()->first();
            $swapStock->delete();
            $swapAsset?->delete();
        }
    }

    private function resolveProduct(): Product
    {
        return Product::query()->firstOrCreate(
            ['name' => 'Midas M32'],
            Product::factory()->rental()->serialised()->make([
                'name' => 'Midas M32',
                'sku' => 'M32',
            ])->getAttributes(),
        );
    }

    private function resolveAsset(Product $product, Store $store, string $number): Asset
    {
        $stockLevel = StockLevel::query()->where('asset_number', 'M32-'.$number)->first();

        if ($stockLevel === null) {
            (new CreateStockLevel)(CreateStockLevelData::from([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'item_name' => $product->name,
                'asset_number' => 'M32-'.$number,
                'serial_number' => 'M32-SN-'.$number,
                'barcode' => 'M32-BAR-'.$number,
                'quantity_held' => '1',
            ]));

            $stockLevel = StockLevel::query()->where('asset_number', 'M32-'.$number)->firstOrFail();
        }

        if ((int) $stockLevel->getRawOriginal('stock_category') !== StockCategory::SerialisedStock->value) {
            throw new \RuntimeException('Existing demo stock level M32-'.$number.' is not serialized.');
        }

        if ($stockLevel->asset_id === null) {
            $asset = Asset::create([
                'product_id' => $product->id,
                'ownership_type' => 'company_owned',
            ]);
            $stockLevel->forceFill(['asset_id' => $asset->id])->saveQuietly();
        } else {
            $asset = $stockLevel->asset()->firstOrFail();
        }

        $baseCurrency = app(CurrencyService::class)->baseCurrencyCode();
        $asset->forceFill([
            'product_id' => $product->id,
            'ownership_type' => 'company_owned',
            'acquired_at' => '2026-01-15',
            'acquisition_amount' => 320000,
            'acquisition_currency_code' => $baseCurrency,
            'acquisition_exchange_rate' => '1',
            'base_currency_code' => $baseCurrency,
            'base_acquisition_amount' => 320000,
            'estimated_resale_value' => 210000,
            'estimated_resale_currency_code' => $baseCurrency,
        ])->save();

        return $asset->refresh();
    }

    private function seedExpenses(): void
    {
        $baseCurrency = app(CurrencyService::class)->baseCurrencyCode();
        $expenses = [
            '001' => [['repair', 12000, '2026-05-12'], ['service', 8000, '2026-08-03']],
            '002' => [['service', 18000, '2026-04-18'], ['parts', 12000, '2026-07-22']],
        ];

        foreach ($expenses as $number => $rows) {
            foreach ($rows as [$type, $amount, $date]) {
                AssetExpense::query()->firstOrCreate([
                    'asset_id' => $this->assets[$number]->id,
                    'reference' => 'ASSET-FIN-DEMO-'.$number.'-'.$date,
                ], [
                    'expense_type' => $type,
                    'amount' => $amount,
                    'currency_code' => $baseCurrency,
                    'exchange_rate' => '1',
                    'base_currency_code' => $baseCurrency,
                    'base_amount' => $amount,
                    'occurred_at' => $date,
                    'description' => 'Asset Financials demo operating expense',
                ]);
            }
        }
    }

    private function seedBelowBreakEvenRentals(Store $store, Member $member): void
    {
        foreach ([
            ['A', 60000, '2026-04-10', '2026-04-12'],
            ['B', 70000, '2026-06-05', '2026-06-08'],
            ['C', 70000, '2026-08-14', '2026-08-16'],
        ] as [$suffix, $amount, $start, $end]) {
            $this->seedRental('001', 'BE-'.$suffix, $amount, $start, $end, $store, $member);
        }
    }

    private function seedAboveBreakEvenRentals(Store $store, Member $member): void
    {
        foreach ([
            ['A', 150000, '2026-04-22', '2026-04-25'],
            ['B', 150000, '2026-06-19', '2026-06-22'],
            ['C', 150000, '2026-09-03', '2026-09-06'],
        ] as [$suffix, $amount, $start, $end]) {
            $this->seedRental('002', 'AE-'.$suffix, $amount, $start, $end, $store, $member);
        }
    }

    private function seedAmbiguousRental(Store $store, Member $member): void
    {
        $reference = 'ASSET-FIN-DEMO-AMBIGUOUS';

        if (Opportunity::query()->where('reference', $reference)->exists()) {
            return;
        }

        $item = $this->createRentalOpportunity('003', $reference, 80000, '2026-07-10', '2026-07-12', $store, $member);
        $first = $this->allocate($item, $this->assets['003']);
        $second = $this->resolveSwapAsset($store);

        (new DispatchAsset)($first, DispatchAssetData::from([
            'dispatched_at' => '2026-07-10T09:00:00+00:00',
        ]));

        // This is a legitimate sequential substitution. The attributor marks the
        // commercial unit ambiguous instead of inventing a revenue split.
        Carbon::setTestNow(Carbon::parse('2026-07-10T14:00:00+00:00'));
        try {
            (new SubstituteAsset)($first->fresh(), SubstituteAssetData::from([
                'new_stock_level_id' => $second->currentStockLevel()->value('id'),
                'reason' => 'Demo sequential substitution',
            ]));
        } finally {
            Carbon::setTestNow();
        }

        $replacement = $item->assets()->where('stock_level_id', $second->currentStockLevel()->value('id'))->firstOrFail();
        (new ReturnAsset)($replacement->fresh(), ReturnAssetData::from([
            'returned_at' => '2026-07-12T17:00:00+00:00',
        ]));

        $this->invoice($item, $reference);
    }

    private function seedRental(string $number, string $suffix, int $amount, string $start, string $end, Store $store, Member $member): void
    {
        $reference = 'ASSET-FIN-DEMO-'.$number.'-'.$suffix;

        if (Opportunity::query()->where('reference', $reference)->exists()) {
            return;
        }

        $item = $this->createRentalOpportunity($number, $reference, $amount, $start, $end, $store, $member);
        $assignment = $this->allocate($item, $this->assets[$number]);

        (new DispatchAsset)($assignment, DispatchAssetData::from([
            'dispatched_at' => $start.'T09:00:00+00:00',
        ]));
        (new ReturnAsset)($assignment->fresh(), ReturnAssetData::from([
            'returned_at' => $end.'T17:00:00+00:00',
        ]));

        $this->invoice($item, $reference);
    }

    private function createRentalOpportunity(string $number, string $reference, int $amount, string $start, string $end, Store $store, Member $member): OpportunityItem
    {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Asset Financials Demo '.$reference,
            'reference' => $reference,
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => $start.'T09:00:00+00:00',
            'ends_at' => $end.'T17:00:00+00:00',
            'currency' => app(CurrencyService::class)->baseCurrencyCode(),
            'use_chargeable_days' => true,
            'chargeable_days' => '1',
        ]));
        $opportunity = Opportunity::query()->findOrFail($created->id);
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $this->assets[$number]->product->name,
            'itemable_id' => $this->assets[$number]->product_id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'quantity' => '1',
            'charge_period' => ChargePeriod::Fixed->value,
            'unit_price' => $amount,
            'transaction_type' => LineItemTransactionType::Rental->value,
            'starts_at' => $start.'T09:00:00+00:00',
            'ends_at' => $end.'T17:00:00+00:00',
        ]));
        (new ConvertToOrder)($opportunity->refresh());

        return $opportunity->refresh()->items()->firstOrFail();
    }

    private function allocate(OpportunityItem $item, Asset $asset): OpportunityItemAsset
    {
        $stockLevelId = $asset->currentStockLevel()->value('id');
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stockLevelId]));

        return $item->assets()->where('stock_level_id', $stockLevelId)->latest('id')->firstOrFail();
    }

    private function invoice(OpportunityItem $item, string $reference): void
    {
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $item->opportunity_id,
            'reference' => $reference,
            'tag_list' => ['asset-financials-demo'],
        ]));
        (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));
    }

    private function resolveSwapAsset(Store $store): Asset
    {
        $product = $this->assets['003']->product;
        $stock = StockLevel::query()->where('asset_number', 'M32-005')->first();

        if ($stock === null) {
            (new CreateStockLevel)(CreateStockLevelData::from([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'item_name' => $product->name,
                'asset_number' => 'M32-005',
                'serial_number' => 'M32-SN-005',
                'barcode' => 'M32-BAR-005',
                'quantity_held' => '1',
            ]));
            $stock = StockLevel::query()->where('asset_number', 'M32-005')->firstOrFail();
        }

        return $stock->asset()->firstOrFail();
    }

    private function reportResults(): void
    {
        $calculator = app(AssetFinancialsCalculator::class);

        foreach ($this->assets as $number => $asset) {
            $result = $calculator->calculate($asset->refresh());
            $this->line(json_encode([
                'asset' => 'M32-'.$number,
                'asset_id' => $asset->id,
                'acquisition' => $asset->base_acquisition_amount,
                'revenue' => $result->confirmedAttributedRentalRevenue,
                'revenue_status' => $result->revenueStatus->value,
                'revenue_complete' => $result->revenueComplete,
                'expenses' => $result->operatingExpenses,
                'net_rental_income' => $result->netRentalIncome,
                'cost_recovery_ratio' => $result->costRecoveryRatio,
                'remaining_to_break_even' => $result->remainingToBreakEven,
                'investment_profit' => $result->investmentProfit,
                'investment_roi' => $result->investmentRoi,
                'rental_count' => $result->rentalCount,
                'completed_rental_count' => $result->completedRentalCount,
                'rental_days' => $result->actualRentalDays,
            ], JSON_THROW_ON_ERROR));
        }
    }
}
