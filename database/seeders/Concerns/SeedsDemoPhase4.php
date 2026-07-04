<?php

namespace Database\Seeders\Concerns;

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Invoices\GenerateDepositInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\GenerateProformaInvoice;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\RecordRefund;
use App\Actions\Members\CreateMember;
use App\Actions\Members\UpdateMember;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\ToggleOpportunitySurchargeGroup;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Actions\Products\CreateStockLevel;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\ReceivePurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\SupplierCosts\CreateSupplierCost;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Data\Members\CreateMemberData;
use App\Data\Members\UpdateMemberData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\ToggleOpportunitySurchargeGroupData;
use App\Data\Products\CreateStockLevelData;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\ReceivePurchaseOrderData;
use App\Data\SupplierCosts\CreateSupplierCostData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\DiscountRuleScope;
use App\Enums\MembershipType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaxBuyerType;
use App\Models\Container;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\TaxCategory;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Carbon\CarbonImmutable;

trait SeedsDemoPhase4
{
    private function enrichDemoMembers(): void
    {
        $this->command->info('Enriching demo members...');

        $tax = $this->demoTaxInfrastructure();

        if ($tax === null) {
            $this->command->warn('Tax infrastructure missing — skipping member enrichment.');

            return;
        }

        $defaultStore = Store::query()->where('is_default', true)->first();

        if ($defaultStore !== null && $defaultStore->tax_zone_id === null) {
            $defaultStore->forceFill([
                'tax_zone_id' => $tax['uk']->id,
                'country_code' => $defaultStore->country_code ?: 'GB',
            ])->save();
        }

        Member::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->where('membership_type', MembershipType::Organisation)
            ->limit(25)
            ->update([
                'sale_tax_class_id' => $tax['orgClass']->id,
                'tax_zone_id' => $tax['uk']->id,
            ]);

        $this->actingAsDemoOwner(function (): void {
            $tradeDiscount = DiscountCategory::query()->where('name', 'Trade Discount')->first();
            $preferredDiscount = DiscountCategory::query()->where('name', 'Preferred Customer')->first();
            $tradePrice = PriceCategory::query()->where('name', 'Trade')->first();
            $retailPrice = PriceCategory::query()->where('name', 'Retail')->first();

            $enrichmentTargets = Member::query()
                ->whereJsonContains('tag_list', self::DEMO_TAG)
                ->where('membership_type', MembershipType::Organisation)
                ->orderBy('id')
                ->limit(3)
                ->get();

            foreach ($enrichmentTargets as $index => $member) {
                $priceCategory = $index === 0 ? $tradePrice : ($index === 1 ? $retailPrice : $tradePrice);
                $tierId = $priceCategory?->tiers()->orderBy('sort_order')->value('id');

                (new UpdateMember)($member, UpdateMemberData::from([
                    'discount_category_id' => $index === 0 ? $tradeDiscount?->id : ($index === 1 ? $preferredDiscount?->id : $tradeDiscount?->id),
                    'price_category_id' => $priceCategory?->id,
                    'price_category_tier_id' => $tierId,
                ]));
            }

            $this->seedDemoTaxVariantMembers();
        });
    }

    private function seedDemoTaxVariantMembers(): void
    {
        $tax = $this->demoTaxInfrastructure();

        if ($tax === null) {
            return;
        }

        $variants = [
            [
                'name' => 'Demo - Berlin Events GmbH',
                'tax_number' => 'DE123456789',
                'tax_zone_id' => $tax['eu']->id,
                'buyer_type' => TaxBuyerType::Business,
                'tax_exempt' => false,
            ],
            [
                'name' => 'Demo - Riverside Community Trust',
                'tax_number' => null,
                'tax_zone_id' => $tax['uk']->id,
                'buyer_type' => TaxBuyerType::Charity,
                'tax_exempt' => false,
            ],
            [
                'name' => 'Demo - Exempt Arts Foundation',
                'tax_number' => null,
                'tax_zone_id' => $tax['uk']->id,
                'buyer_type' => TaxBuyerType::Business,
                'tax_exempt' => true,
            ],
            [
                'name' => 'Demo - Pacific Events LLC',
                'tax_number' => null,
                'tax_zone_id' => $tax['world']->id,
                'buyer_type' => TaxBuyerType::Business,
                'tax_exempt' => false,
            ],
        ];

        foreach ($variants as $variant) {
            $existing = Member::query()
                ->whereJsonContains('tag_list', self::DEMO_TAG)
                ->where('name', $variant['name'])
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'tax_zone_id' => $variant['tax_zone_id'],
                    'buyer_type' => $variant['buyer_type'],
                    'tax_exempt' => $variant['tax_exempt'],
                    'tax_number' => $variant['tax_number'],
                ])->save();

                continue;
            }

            $memberData = (new CreateMember)(CreateMemberData::from([
                'name' => $variant['name'],
                'membership_type' => MembershipType::Organisation,
                'sale_tax_class_id' => $tax['orgClass']->id,
                'tax_number' => $variant['tax_number'],
                'tag_list' => [self::DEMO_TAG],
            ]));

            Member::query()->findOrFail($memberData->id)->forceFill([
                'tax_zone_id' => $variant['tax_zone_id'],
                'buyer_type' => $variant['buyer_type'],
                'tax_exempt' => $variant['tax_exempt'],
            ])->save();
        }
    }

    private function enrichDemoProducts(): void
    {
        $this->command->info('Enriching demo products...');

        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        if ($store === null) {
            return;
        }

        $reducedCategory = TaxCategory::query()->where('slug', 'reduced')->first();
        $gaffer = Product::query()->where('sku', 'DEMO-CON-GAF50')->first();

        if ($gaffer !== null && $reducedCategory !== null) {
            $gaffer->forceFill(['sale_tax_category_id' => $reducedCategory->id])->save();
        }

        $this->actingAsDemoOwner(function () use ($store): void {
            $supplierA = $this->firstOrCreateDemoSupplier('Demo - Supplier Audio Ltd');
            $supplierB = $this->firstOrCreateDemoSupplier('Demo - Supplier Lighting Co');

            $mic = Product::query()->where('sku', 'DEMO-AUD-EW100')->first();
            $movingHead = Product::query()->where('sku', 'DEMO-LMH-BEAM230')->first();
            $parCan = Product::query()->where('sku', 'DEMO-LGN-PARRGBW')->first();

            if ($mic !== null) {
                $this->firstOrCreateSupplierCost($mic, $supplierA, $store, 18000, preferred: true);
            }

            if ($movingHead !== null) {
                $this->firstOrCreateSupplierCost($movingHead, $supplierA, $store, 42000, preferred: false);
                $this->firstOrCreateSupplierCost($movingHead, $supplierB, $store, 39500, preferred: true);
            }

            if ($movingHead !== null && $parCan !== null) {
                $movingHead->forceFill([
                    'is_containerable' => true,
                    'container_template' => [
                        'slots' => [
                            ['product_id' => $parCan->id, 'quantity' => 2, 'binding' => 'fixed'],
                        ],
                        'max_items' => 2,
                    ],
                ])->save();

                $this->seedDemoSerialisedAssets($movingHead, $store, 'DEMO-MH', 2);
                $this->seedDemoSerialisedAssets($parCan, $store, 'DEMO-PAR', 4);
            }
        });
    }

    private function firstOrCreateDemoSupplier(string $name): Member
    {
        $existing = Member::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $data = (new CreateMember)(CreateMemberData::from([
            'name' => $name,
            'membership_type' => MembershipType::Organisation,
            'tag_list' => [self::DEMO_TAG],
        ]));

        return Member::query()->whereKey($data->id)->firstOrFail();
    }

    private function firstOrCreateSupplierCost(
        Product $product,
        Member $supplier,
        Store $store,
        int $costMinor,
        bool $preferred,
    ): void {
        $exists = ProductSupplierCost::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->where('store_id', $store->id)
            ->exists();

        if ($exists) {
            return;
        }

        (new CreateSupplierCost)(CreateSupplierCostData::from([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'cost_basis' => 'per_unit',
            'cost' => $costMinor,
            'currency_code' => 'GBP',
            'is_preferred' => $preferred,
        ]));
    }

    private function seedDemoSerialisedAssets(Product $product, Store $store, string $prefix, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $assetNumber = sprintf('%s-%03d', $prefix, $i);

            if (StockLevel::query()->where('asset_number', $assetNumber)->exists()) {
                continue;
            }

            (new CreateStockLevel)(CreateStockLevelData::from([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'item_name' => $product->name,
                'asset_number' => $assetNumber,
                'serial_number' => $assetNumber,
                'barcode' => $assetNumber,
                'quantity_held' => '1',
            ]));
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string|null}>
     */
    private function seedExtendedDemoOpportunityBlueprints(): array
    {
        return [
            ['DEMO-OPP-0001', 'Summer Festival Main Stage', 'order', null],
            ['DEMO-OPP-0002', 'Corporate AGM AV Package', 'order', null],
            ['DEMO-OPP-0003', 'Product Launch Lighting Rig', 'quotation', 'surcharge'],
            ['DEMO-OPP-0004', 'Awards Dinner Sound System', 'quotation', 'proforma'],
            ['DEMO-OPP-0005', 'Conference Breakout Rooms', 'quotation', 'deposit'],
            ['DEMO-OPP-0006', 'Wedding Marquee Hire', 'draft', null],
            ['DEMO-OPP-0008', 'Charity Gala Staging', 'order', 'partial_pay'],
            ['DEMO-OPP-0009', 'Exhibition Build & Strike', 'order', 'dispatched'],
            ['DEMO-OPP-0010', 'Outdoor Concert Rig', 'order', 'returned'],
            ['DEMO-OPP-0011', 'Fixed-Price Festival Package', 'order', 'deal_price'],
            ['DEMO-OPP-0012', 'Volume Discount Showcase', 'order', 'quantity_break'],
            ['DEMO-OPP-0013', 'Arena Tour Production Package', 'order', 'overdue'],
        ];
    }

    private function applyDemoOpportunitySpecial(
        Opportunity $opportunity,
        string $special,
        User $owner,
    ): void {
        match ($special) {
            'surcharge' => $this->applyDemoSurchargeGroup($opportunity),
            'deal_price' => $this->applyDemoDealPrice($opportunity),
            'quantity_break' => $this->applyDemoQuantityBreak($opportunity),
            'dispatched' => (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::OrderDispatched),
            'returned' => (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::OrderReturned),
            default => null,
        };
    }

    private function applyDemoSurchargeGroup(Opportunity $opportunity): void
    {
        $group = SurchargeGroup::query()->firstOrCreate(
            ['name' => 'Demo - Weekend Delivery'],
            ['description' => 'Demo surcharge group for UAT.', 'is_default' => false, 'is_active' => true, 'sort_order' => 99],
        );

        Surcharge::query()->firstOrCreate(
            ['surcharge_group_id' => $group->id, 'name' => 'Demo Weekend Loading'],
            [
                'percent_value' => '7.50',
                'fixed_value_minor' => null,
                'criteria' => [],
                'is_default' => true,
                'sort_order' => 1,
            ],
        );

        (new ToggleOpportunitySurchargeGroup)($opportunity, ToggleOpportunitySurchargeGroupData::from([
            'surcharge_group_id' => $group->id,
            'is_active' => true,
        ]));
    }

    private function applyDemoDealPrice(Opportunity $opportunity): void
    {
        $opportunity->refresh();

        if ($opportunity->hasLocks()) {
            (new UnlockOpportunity)($opportunity, 'Demo data seeding');
            $opportunity->refresh();
        }

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        $target = max(1, (int) floor(((int) $opportunity->charge_total) * 0.85));

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'deal_total' => $target,
        ]));
    }

    private function applyDemoQuantityBreak(Opportunity $opportunity): void
    {
        $category = DiscountCategory::query()->where('name', 'Trade Discount')->first();

        if ($category === null) {
            return;
        }

        if (! DiscountRule::query()
            ->where('discount_category_id', $category->id)
            ->where('rule_scope', DiscountRuleScope::PerLine)
            ->where('discount_percent', '10.00')
            ->exists()) {
            DiscountRule::query()->create([
                'discount_category_id' => $category->id,
                'rule_scope' => DiscountRuleScope::PerLine,
                'discount_percent' => '10.00',
                'criteria' => ['quantity_min' => 10],
            ]);
        }

        $member = $opportunity->member;

        if ($member !== null) {
            $member->forceFill(['discount_category_id' => $category->id])->save();
        }

        $item = $opportunity->items()
            ->where('item_type', OpportunityItemType::Product)
            ->first();

        if ($item !== null) {
            (new ChangeItemQuantity)($item, ChangeItemQuantityData::from(['quantity' => '12']));
        }
    }

    private function createDemoInvoices(): void
    {
        $this->command->info('Seeding demo invoices...');

        $this->ensureDemoFinanceSettings();

        $this->actingAsDemoOwner(function (): void {
            $demoTag = [self::DEMO_TAG];

            $this->seedDemoInvoiceIfMissing('DEMO-INV-DRAFT', 'DEMO-OPP-0001', function (Opportunity $opportunity) use ($demoTag): void {
                (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
                    'opportunity_id' => $opportunity->id,
                    'reference' => 'DEMO-INV-DRAFT',
                    'tag_list' => $demoTag,
                ]));
            });

            $this->seedDemoInvoiceIfMissing('DEMO-INV-PAID', 'DEMO-OPP-0002', function (Opportunity $opportunity) use ($demoTag): void {
                $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
                    'opportunity_id' => $opportunity->id,
                    'reference' => 'DEMO-INV-PAID',
                    'tag_list' => $demoTag,
                ]));

                $invoice = Invoice::query()->findOrFail($draft->id);
                (new IssueInvoice)($invoice);
                (new RecordPayment)($invoice->refresh(), RecordPaymentData::from([
                    'amount' => $invoice->total,
                    'payment_method' => PaymentMethod::BankTransfer,
                    'reference' => 'DEMO-PAY-FULL',
                ]));
            });

            $this->seedDemoInvoiceIfMissing('DEMO-INV-PARTIAL', 'DEMO-OPP-0008', function (Opportunity $opportunity) use ($demoTag): void {
                $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
                    'opportunity_id' => $opportunity->id,
                    'reference' => 'DEMO-INV-PARTIAL',
                    'tag_list' => $demoTag,
                ]));

                $invoice = Invoice::query()->findOrFail($draft->id);
                (new IssueInvoice)($invoice);
                $partial = max(1, intdiv($invoice->total, 3));
                (new RecordPayment)($invoice->refresh(), RecordPaymentData::from([
                    'amount' => $partial,
                    'payment_method' => PaymentMethod::Card,
                    'reference' => 'DEMO-PAY-PARTIAL',
                ]));
                (new RecordRefund)($invoice->refresh(), RecordPaymentData::from([
                    'amount' => max(1, intdiv($partial, 2)),
                    'payment_method' => PaymentMethod::Card,
                    'reference' => 'DEMO-REFUND',
                ]));

                $item = $invoice->refresh()->items->first();
                if ($item !== null) {
                    (new IssueCreditNote)($invoice, IssueCreditNoteData::from([
                        'lines' => [
                            ['invoice_item_id' => $item->id, 'quantity' => bcdiv((string) $item->quantity, '2', 2)],
                        ],
                        'notes' => 'Demo partial credit note.',
                    ]));
                }
            });

            $this->seedDemoInvoiceIfMissing('DEMO-INV-OVERDUE', 'DEMO-OPP-0013', function (Opportunity $opportunity) use ($demoTag): void {
                CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->subDays(120));

                try {
                    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
                        'opportunity_id' => $opportunity->id,
                        'reference' => 'DEMO-INV-OVERDUE',
                        'tag_list' => $demoTag,
                    ]));

                    $invoice = Invoice::query()->findOrFail($draft->id);
                    (new IssueInvoice)($invoice);

                    (new IssueCreditNote)($invoice->refresh(), IssueCreditNoteData::from([
                        'lines' => null,
                        'notes' => 'Demo full credit note on overdue invoice.',
                    ]));
                } finally {
                    CarbonImmutable::setTestNow();
                }
            });

            $this->seedDemoInvoiceIfMissing('DEMO-INV-DEPOSIT', 'DEMO-OPP-0005', function (Opportunity $opportunity) use ($demoTag): void {
                (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
                    'opportunity_id' => $opportunity->id,
                    'amount_percentage' => '25',
                    'reference' => 'DEMO-INV-DEPOSIT',
                    'tag_list' => $demoTag,
                ]));
            }, requiresOrder: false);

            $this->seedDemoInvoiceIfMissing('DEMO-INV-PROFORMA', 'DEMO-OPP-0004', function (Opportunity $opportunity) use ($demoTag): void {
                (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
                    'opportunity_id' => $opportunity->id,
                    'reference' => 'DEMO-INV-PROFORMA',
                    'tag_list' => $demoTag,
                ]));
            }, requiresOrder: false);
        });
    }

    private function seedDemoInvoiceIfMissing(
        string $invoiceReference,
        string $opportunityReference,
        callable $seed,
        bool $requiresOrder = true,
    ): void {
        if (Invoice::query()->where('reference', $invoiceReference)->exists()) {
            return;
        }

        $opportunity = Opportunity::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->where('reference', $opportunityReference)
            ->first();

        if ($opportunity === null) {
            return;
        }

        if ($requiresOrder && $opportunity->state !== OpportunityState::Order) {
            return;
        }

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        $seed($opportunity->fresh());
    }

    private function createDemoPurchaseOrders(): void
    {
        $this->command->info('Seeding demo purchase orders...');

        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        if ($store === null) {
            return;
        }

        $this->actingAsDemoOwner(function () use ($store): void {
            $supplier = $this->firstOrCreateDemoSupplier('Demo - Sub-Hire Partner Ltd');
            $product = Product::query()->where('sku', 'DEMO-STG-DECK2X1')->first();

            if ($product === null) {
                return;
            }

            if (! PurchaseOrder::query()->where('reference', 'DEMO-PO-0001')->exists()) {
                (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
                    'supplier_id' => $supplier->id,
                    'store_id' => $store->id,
                    'po_type' => 'purchase',
                    'reference' => 'DEMO-PO-0001',
                    'supplier_reference' => 'SUP-DRAFT-001',
                    'notes' => 'Demo draft purchase order.',
                    'lines' => [[
                        'product_id' => $product->id,
                        'description' => 'Stage deck hire-in (draft)',
                        'quantity' => '4.00',
                        'unit_cost' => 1200,
                    ]],
                ]));
            }

            if (PurchaseOrder::query()->where('reference', 'DEMO-PO-0002')->exists()) {
                return;
            }

            $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'po_type' => 'sub_hire',
                'reference' => 'DEMO-PO-0002',
                'supplier_reference' => 'SUP-SH-002',
                'starts_at' => now()->addWeek()->toIso8601String(),
                'ends_at' => now()->addWeeks(2)->toIso8601String(),
                'notes' => 'Demo sub-hire PO with virtual stock intake.',
                'lines' => [[
                    'product_id' => $product->id,
                    'description' => 'Sub-hired staging decks',
                    'quantity' => '6.00',
                    'unit_cost' => 1500,
                ]],
            ]));

            $po = PurchaseOrder::query()->with('items')->findOrFail($created->id);
            $line = $po->items->firstOrFail();

            (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
                'product_id' => (int) $line->product_id,
                'store_id' => $store->id,
                'quantity' => 6,
                'available_from' => now()->addWeek()->toIso8601String(),
                'available_until' => now()->addWeeks(2)->toIso8601String(),
                'unit_cost' => (int) $line->unit_cost,
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $line->id,
            ]));

            (new SendPurchaseOrder)($po);
            app(ReceivePurchaseOrder::class)($po->refresh(), ReceivePurchaseOrderData::from([
                'lines' => [[
                    'purchase_order_item_id' => $line->id,
                    'quantity' => (string) $line->quantity,
                ]],
            ]));
        });
    }

    private function createDemoContainers(): void
    {
        $this->command->info('Seeding demo containers...');

        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        if ($store === null) {
            return;
        }

        $this->actingAsDemoOwner(function (): void {
            $housingProduct = Product::query()->where('sku', 'DEMO-LMH-BEAM230')->first();
            $component = Product::query()->where('sku', 'DEMO-LGN-PARRGBW')->first();

            if ($housingProduct === null || ! $housingProduct->is_containerable) {
                return;
            }

            $openHousing = StockLevel::query()->where('asset_number', 'DEMO-MH-001')->first();
            if ($openHousing !== null && ! Container::query()->where('serialised_item_id', $openHousing->id)->exists()) {
                (new CreateContainer)(CreateContainerData::from([
                    'serialised_item_id' => $openHousing->id,
                    'name' => 'Demo Open Flight Case',
                ]));
            }

            $sealedHousing = StockLevel::query()->where('asset_number', 'DEMO-MH-002')->first();
            if ($sealedHousing === null || Container::query()->where('serialised_item_id', $sealedHousing->id)->exists()) {
                return;
            }

            $container = (new CreateContainer)(CreateContainerData::from([
                'serialised_item_id' => $sealedHousing->id,
                'name' => 'Demo Sealed Flight Case',
            ]));

            $containerModel = Container::query()->findOrFail($container->id);
            $componentAssets = StockLevel::query()
                ->where('product_id', $component?->id)
                ->orderBy('asset_number')
                ->limit(2)
                ->get();

            foreach ($componentAssets as $asset) {
                (new PackContainerItem)($containerModel, PackContainerItemData::from([
                    'serialised_item_id' => $asset->id,
                ]));
                $containerModel->refresh();
            }

            if ($containerModel->activeItems()->count() > 0) {
                (new SealContainer)($containerModel);
            }
        });
    }
}
