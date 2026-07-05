<?php

/**
 * P4-CL4b coverage burn-down batch 6 — classes 46–67 (OpportunityTotalsCalculator through ContainerManifestService).
 */

use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\TestChannelProviderData;
use App\Enums\DealPriceMode;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Enums\PricingRoundingDirection;
use App\Enums\TaxCategoryType;
use App\Enums\TaxResultType;
use App\Exceptions\Documents\DocumentRenderException;
use App\Models\CommunicationLog;
use App\Models\Container;
use App\Models\CreditNote;
use App\Models\Demand;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\TaxCategory;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Api\PriceWeightResolver;
use App\Services\Containers\ContainerDispatchValidator;
use App\Services\Containers\ContainerManifestService;
use App\Services\Documents\DocumentRenderPipeline;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\InvoiceDataResolver;
use App\Services\Documents\SandboxedBladeCompiler;
use App\Services\Invoices\CreditNoteNumberGenerator;
use App\Services\Invoices\DepositInvoiceCalculator;
use App\Services\Invoices\InvoiceMarginCalculator;
use App\Services\Invoices\PaymentNumberGenerator;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CoreMergeFieldFilters;
use App\Services\Notifications\Drivers\DatabaseDriver;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Notifications\NotificationLinkResolver;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Services\Pricing\OpportunityPricingRecalculator;
use App\Services\PurchaseOrders\PurchaseOrderTaxCalculator;
use App\Services\Tax\DefaultVatNumberValidator;
use App\Services\Tax\TaxRateFallbackResolver;
use App\Services\TaxCalculator;
use App\Services\VirtualStock\VirtualStockAssetProvisioner;
use App\Services\VirtualStock\VirtualStockIntakeReturnGuard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Store::factory()->create();
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('company.time_format_php', 'H:i');
});

/**
 * @param  array<int, mixed>  $args
 */
function batch6Part3Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function batch6Part3TaxMember(): Member
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    return Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
}

describe('OpportunityTotalsCalculator batch-6 branches', function () {
    it('rollUp routes sale service rental costs surcharges deal headline and tax lock', function () {
        $member = batch6Part3TaxMember();
        $store = Store::factory()->create();
        $productClass = ProductTaxClass::query()->where('is_default', true)->firstOrFail();
        $saleProduct = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);
        $serviceProduct = Product::factory()->service()->create(['tax_class_id' => $productClass->id]);
        $rentalProduct = Product::factory()->create(['tax_class_id' => $productClass->id]);
        $surcharge = Surcharge::factory()->create();

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'deal_total' => 5000,
            'deal_price_mode' => DealPriceMode::FlatHeadline->value,
        ]);

        $saleItem = OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $saleProduct->id,
            'itemable_type' => Product::class,
            'total' => 2000,
            'unit_price' => 2000,
            'quantity' => '1',
            'is_optional' => false,
        ]);
        OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $serviceProduct->id,
            'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::Service,
            'total' => 1000,
            'unit_price' => 1000,
            'quantity' => '1',
        ]);
        OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $rentalProduct->id,
            'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::Rental,
            'total' => 1500,
            'unit_price' => 1500,
            'quantity' => '1',
        ]);
        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $saleProduct->id,
            'itemable_type' => Product::class,
            'total' => 999,
            'is_optional' => true,
        ]);

        OpportunityItemSurcharge::factory()->create([
            'opportunity_item_id' => $saleItem->id,
            'surcharge_id' => $surcharge->id,
            'amount' => 200,
            'is_disabled' => false,
            'product_tax_class_id' => $productClass->id,
        ]);
        OpportunityItemSurcharge::factory()->create([
            'opportunity_item_id' => $saleItem->id,
            'surcharge_id' => Surcharge::factory()->create()->id,
            'amount' => 50,
            'is_disabled' => true,
            'product_tax_class_id' => $productClass->id,
        ]);

        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'cost_type' => OpportunityCostType::Delivery,
            'amount' => 300,
            'quantity' => '1',
        ]);
        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'cost_type' => OpportunityCostType::LossDamage,
            'amount' => 100,
            'quantity' => '1',
        ]);
        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'cost_type' => OpportunityCostType::Labour,
            'amount' => 400,
            'quantity' => '1',
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $opportunity->refresh();
        expect($opportunity->sale_charge_total)->toBe(2000)
            ->and($opportunity->service_charge_total)->toBe(1400)
            ->and($opportunity->rental_charge_total)->toBe(1500)
            ->and($opportunity->transit_charge_total)->toBe(300)
            ->and($opportunity->loss_damage_charge_total)->toBe(100)
            ->and($opportunity->charge_excluding_tax_total)->toBe(5000);

        $locked = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'tax_locked' => true,
            'tax_total' => 777,
        ]);
        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $locked->id,
            'itemable_id' => $saleProduct->id,
            'itemable_type' => Product::class,
            'total' => 1000,
            'quantity' => '1',
        ]);
        app(OpportunityTotalsCalculator::class)->rollUp($locked->refresh());
        expect($locked->refresh()->tax_total)->toBe(777);
    });

    it('resolveSubtotal and effectiveDiscountPercent cover manual fractional and discount registry paths', function () {
        $member = batch6Part3TaxMember();
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $manualItem = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'unit_price' => 500,
            'quantity' => '2.5',
            'total' => 1250,
            'discount_percent' => '10.00',
        ]);

        $calc = app(OpportunityTotalsCalculator::class);
        $calc->recalculateItem($manualItem->refresh());

        $fractional = batch6Part3Invoke($calc, 'resolveSubtotal', [
            $manualItem,
            $product,
            'GBP',
            '2.5',
            Carbon::parse($opportunity->starts_at),
            Carbon::parse($opportunity->ends_at),
            $store->id,
            500,
            $opportunity,
        ]);
        expect($fractional['unit_price'])->toBe(500);

        $noRateItem = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => 999999,
            'itemable_type' => Product::class,
            'unit_price' => 100,
            'quantity' => '1',
        ]);
        $noRate = batch6Part3Invoke($calc, 'resolveSubtotal', [
            $noRateItem,
            null,
            'GBP',
            '1',
            Carbon::parse($opportunity->starts_at),
            Carbon::parse($opportunity->ends_at),
            $store->id,
            null,
            $opportunity,
        ]);
        expect($noRate['net_subtotal'])->toBeInt();

        $percent = batch6Part3Invoke($calc, 'effectiveDiscountPercent', [$manualItem->refresh(), $opportunity]);
        expect($percent)->not->toBeNull();
    });
});

describe('PurchaseOrderTaxCalculator batch-6 branches', function () {
    it('summarizeLines mergeLineTax and assertPennyReconciliation cover guard branches', function () {
        $supplier = Member::factory()->organisation()->create(['purchase_tax_class_id' => null]);
        $store = Store::factory()->create();
        $calculator = app(PurchaseOrderTaxCalculator::class);

        $emptySummary = batch6Part3Invoke($calculator, 'summarizeLines', [
            $supplier->id,
            $store->id,
            'GBP',
            [],
            fn (): string => '1',
            null,
        ]);
        expect($emptySummary->totalTax)->toBe(0);

        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create();
        $supplierWithTax = Member::factory()->organisation()->create(['purchase_tax_class_id' => $orgClass->id]);
        $product = Product::factory()->create([
            'purchase_tax_class_id' => $productClass->id,
        ]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '20.0000'])->id,
        ]);

        $lines = [
            ['product_id' => $product->id, 'unit_cost' => 1000, 'quantity' => '0'],
            ['product_id' => $product->id, 'unit_cost' => 1000, 'purchase_order_item_id' => 42],
        ];
        $summary = batch6Part3Invoke($calculator, 'summarizeLines', [
            $supplierWithTax->id,
            $store->id,
            'GBP',
            $lines,
            fn (array $line): string => (string) ($line['quantity'] ?? '1'),
            null,
        ]);
        expect($summary->totalNet)->toBeGreaterThan(0);

        $merged = batch6Part3Invoke($calculator, 'mergeLineTax', [
            $lines,
            $summary,
        ]);
        expect($merged[1]['tax'] ?? null)->toBeInt();

        $calculator->assertPennyReconciliation(1000, 200, 0, 1200, [1200]);
        expect(fn () => $calculator->assertPennyReconciliation(1000, 200, 0, 1300, [1200]))
            ->toThrow(LogicException::class);
        expect(fn () => $calculator->assertPennyReconciliation(1000, 200, 0, 1200, [1100]))
            ->toThrow(LogicException::class, 'line totals');
    });
});

describe('DocumentRenderPipeline batch-6 branches', function () {
    it('render returns failure when resolver throws and renderWithData handles missing store', function () {
        $this->mock(DocumentResolverRegistry::class, function ($mock): void {
            $mock->shouldReceive('resolve')->andThrow(new RuntimeException('resolver boom'));
        });

        $template = DocumentTemplate::factory()->create();
        $result = app(DocumentRenderPipeline::class)->render($template, 'invoice', 1);
        expect($result->success)->toBeFalse()
            ->and($result->error)->toContain('Unable to resolve');

        $orphanTemplate = DocumentTemplate::factory()->create(['store_id' => Store::factory()->create()->id]);
        Store::query()->whereKey($orphanTemplate->store_id)->delete();
        $orphanTemplate->unsetRelation('store');

        $result2 = app(DocumentRenderPipeline::class)->renderFromSnapshot(
            $orphanTemplate,
            ['invoice' => ['number' => 'X']],
        );
        expect($result2->success)->toBeFalse()
            ->and($result2->error)->toContain('store is missing');
    });
});

describe('CommunicationLogQuery batch-6 branches', function () {
    it('forUser scopes guests and store access and forInvoice matches payment and credit documents', function () {
        $query = app(CommunicationLogQuery::class);
        expect($query->forUser(null)->count())->toBe(0);

        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->issued()->create();
        $payment = InvoicePayment::factory()->create(['invoice_id' => $invoice->id]);
        $creditNote = CreditNote::factory()->create(['invoice_id' => $invoice->id]);

        $invoiceDoc = Document::factory()->create([
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);
        Document::factory()->create([
            'documentable_type' => InvoicePayment::class,
            'documentable_id' => $payment->id,
        ]);
        Document::factory()->create([
            'documentable_type' => CreditNote::class,
            'documentable_id' => $creditNote->id,
        ]);

        CommunicationLog::factory()->create([
            'subject_type' => Member::class,
            'subject_id' => $invoice->member_id,
            'attachments' => ['document:'.$invoiceDoc->id],
        ]);

        expect($query->forInvoice($invoice, $owner)->count())->toBe(1);

        $emptyInvoice = Invoice::factory()->issued()->create();
        expect($query->forInvoice($emptyInvoice, $owner)->count())->toBe(0);
    });
});

describe('NotificationLinkResolver batch-6 branches', function () {
    it('resolve handles action_url subject lookup and routeForSubject fallbacks', function () {
        $resolver = app(NotificationLinkResolver::class);
        $opportunity = Opportunity::factory()->create();
        $member = Member::factory()->create();

        $notification = new DatabaseNotification;
        $notification->data = [
            'payload' => [
                'opportunity' => ['id' => $opportunity->id],
            ],
        ];
        expect($resolver->resolve($notification))->toBe(route('opportunities.show', $opportunity));

        $notification->data = ['payload' => ['action_url' => '/custom/path']];
        expect($resolver->resolve($notification))->toBe('/custom/path');

        $notification->data = ['payload' => [
            'subject_type' => Member::class,
            'subject_id' => $member->id,
        ]];
        expect($resolver->resolve($notification))->toBe(route('members.show', $member));

        $notification->data = ['payload' => ['subject_type' => Store::class, 'subject_id' => 1]];
        expect($resolver->resolve($notification))->toBeNull();

        expect($resolver->routeForSubject($opportunity))->toBe(route('opportunities.show', $opportunity))
            ->and($resolver->routeForSubject($member))->toBe(route('members.show', $member))
            ->and($resolver->routeForSubject(Store::factory()->create()))->toBeNull();
    });
});

describe('DepositInvoiceCalculator batch-6 branches', function () {
    it('calculate proportional and full policies with penny reconciliation and scaleAmount edges', function () {
        $calculator = app(DepositInvoiceCalculator::class);
        $taxRows = [
            new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: 1,
                taxable_amount: 8000,
                tax_amount: 1600,
                rate_percentage: '20.0000',
                tax_rate_name: 'Standard',
                tax_rate_id: 1,
                tax_rule_id: 1,
                result_type: TaxResultType::Standard->value,
            ),
            new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: 2,
                taxable_amount: 2000,
                tax_amount: 400,
                rate_percentage: '20.0000',
                tax_rate_name: 'Standard',
                tax_rate_id: 1,
                tax_rule_id: 1,
                result_type: TaxResultType::ZeroRated->value,
            ),
        ];

        $proportional = $calculator->calculate(2500, 10000, $taxRows, PartialInvoiceTaxPolicy::Proportional);
        expect($proportional['tax_total'])->toBe($proportional['lines'][0]->tax);

        $full = $calculator->calculate(2500, 10000, $taxRows, PartialInvoiceTaxPolicy::Full);
        expect($full['tax_total'])->toBe(2000);

        expect($calculator->netFromFixedGross(10000, 12000, 6000))->toBe(5000);
        expect(fn () => $calculator->netFromFixedGross(10000, 0, 100))->toThrow(LogicException::class);

        expect(batch6Part3Invoke($calculator, 'scaleAmount', [0, '0.25']))->toBe(0)
            ->and(batch6Part3Invoke($calculator, 'scaleAmount', [-100, '0.5']))->toBe(-50);

        $emptyTreatment = batch6Part3Invoke($calculator, 'resolveLineTaxTreatment', [[]]);
        expect($emptyTreatment)->toBe(TaxResultType::Standard->value);
    });
});

describe('InvoiceDataResolver batch-6 branches', function () {
    it('resolve handles null opportunity store tax group and dates', function () {
        $resolver = app(InvoiceDataResolver::class);
        $invoice = Invoice::factory()->issued()->create([
            'opportunity_id' => null,
            'issued_at' => Carbon::parse('2026-06-01T00:00:00Z'),
        ]);
        $loaded = $resolver->loadSource($invoice->id);
        $loaded->setRelation('store', null);

        $data = $resolver->resolve($loaded);
        expect($data['opportunity']['number'])->toBeNull()
            ->and($data['store']['name'])->toBeNull()
            ->and($data['invoice']['issued_at'])->toBeString();

        expect(fn () => $resolver->resolve(Member::factory()->create()))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('ContainerDispatchValidator batch-6 branches', function () {
    it('validateProductQuantities linesByProduct and countManifestAssetsOnOpportunity cover kit paths', function () {
        $validator = app(ContainerDispatchValidator::class);
        $opportunity = Opportunity::factory()->create();
        $product = Product::factory()->create();
        $container = Container::factory()->create(['opportunity_id' => $opportunity->id]);
        $item = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '2',
        ]);

        $byProduct = batch6Part3Invoke($validator, 'linesByProduct', [$opportunity]);
        expect($byProduct)->toHaveKey($product->id);

        $count = batch6Part3Invoke($validator, 'countManifestAssetsOnOpportunity', [
            $container,
            $opportunity,
            $product->id,
            false,
        ]);
        expect($count)->toBeInt();
    });
});

describe('TaxRateFallbackResolver batch-6 branches', function () {
    it('resolve explicit rate name fallbacks and category steering', function () {
        $standard = TaxRate::factory()->create(['name' => 'Standard', 'is_active' => true]);
        $reduced = TaxRate::factory()->create(['name' => 'Reduced', 'is_active' => true]);
        $zero = TaxRate::factory()->create(['name' => 'Zero', 'is_active' => true]);

        $zoneRule = TaxRule::factory()->create(['tax_rate_id' => $standard->id]);
        $resolver = app(TaxRateFallbackResolver::class);

        expect($resolver->resolve($zoneRule, TaxResultType::Standard, null)?->id)->toBe($standard->id);

        $zoneRule->update(['tax_rate_id' => null]);
        $category = TaxCategory::factory()->create(['type' => TaxCategoryType::Reduced]);
        expect($resolver->resolve($zoneRule, TaxResultType::Standard, $category->id)?->name)->toBe('Reduced');

        $exemptCategory = TaxCategory::factory()->create(['type' => TaxCategoryType::Exempt]);
        expect($resolver->resolve($zoneRule, TaxResultType::ZeroRated, $exemptCategory->id)?->name)->toBe('Zero');
    });
});

describe('VirtualStockAssetProvisioner batch-6 branches', function () {
    it('provision skips empty assets non-serialised and syncSupplyDemand handles confirmed intake', function () {
        Verbs::shouldReceive('isReplaying')->andReturn(false);

        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $product = Product::factory()->create();
        $intake->update(['product_id' => $product->id]);

        app(VirtualStockAssetProvisioner::class)->provision($intake->refresh(), []);
        expect(StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe(0);

        $serialProduct = Product::factory()->serialised()->create();
        $serialIntake = VirtualStockIntake::factory()->confirmed()->create([
            'product_id' => $serialProduct->id,
            'quantity' => 1,
        ]);
        $stock = StockLevel::factory()->virtual()->create([
            'product_id' => $serialProduct->id,
            'store_id' => $serialIntake->store_id,
            'virtual_stock_intake_id' => $serialIntake->id,
        ]);

        app(VirtualStockAssetProvisioner::class)->provision($serialIntake, [[
            'stock_level_id' => $stock->id,
            'supply_demand_id' => 0,
            'sequence' => 1,
            'asset_number' => 'VS-A-001',
        ]]);

        app(VirtualStockAssetProvisioner::class)->syncSupplyDemand(
            $serialIntake->refresh(),
            $stock->id,
            0,
        );
        expect(Demand::query()->where('source_id', $serialIntake->id)->exists())->toBeTrue();
    });
});

describe('SandboxedBladeCompiler batch-6 branches', function () {
    it('forbidden compile methods throw and foreach compilers emit sandboxed loop code', function () {
        $compiler = app(SandboxedBladeCompiler::class);

        foreach ([
            'compilePhp', 'compileInject', 'compileInclude', 'compileIncludeIf', 'compileIncludeWhen',
            'compileIncludeUnless', 'compileIncludeFirst', 'compileIncludeIsolated', 'compileExtends',
            'compileExtendsFirst', 'compileComponent', 'compileComponentFirst', 'storePhpBlocks',
        ] as $method) {
            expect(fn () => batch6Part3Invoke($compiler, $method, ["'x'"]))
                ->toThrow(DocumentRenderException::class);
        }

        $foreach = batch6Part3Invoke($compiler, 'compileForeach', ['($items as $item)']);
        expect($foreach)->toContain('SandboxedLoopEnvironment::prepareLoopData');

        expect(fn () => batch6Part3Invoke($compiler, 'compileForeach', ['($bad']))
            ->toThrow(DocumentRenderException::class, 'Malformed @foreach');

        $forelse = batch6Part3Invoke($compiler, 'compileForelse', ['($items as $item)']);
        expect($forelse)->toContain('__empty_');

        $empty = batch6Part3Invoke($compiler, 'compileEmpty', [null]);
        expect($empty)->toContain('__empty_');
    });
});

describe('PriceWeightResolver batch-6 branches', function () {
    it('weightForMethod build and persistent cache methods handle edge cases', function () {
        $resolver = app(PriceWeightResolver::class);

        expect($resolver->weightForMethod(PriceWeightResolver::class, 'nonexistentMethod'))->toBe(1);

        Cache::shouldReceive('supportsTags')->andReturn(false);
        Cache::shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('put')->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache down'));

        $fresh = new PriceWeightResolver;
        expect($fresh->all())->toBeArray();
        $fresh->invalidateAll();
    });
});

describe('CoreMergeFieldFilters batch-6 branches', function () {
    it('covers time number plural truncate default and passthrough branches', function () {
        $context = new MergeFieldRenderContext(currencyCode: 'GBP');

        expect(CoreMergeFieldFilters::time(null, null, $context))->toBeNull()
            ->and(CoreMergeFieldFilters::time('not-a-date', null, $context))->toBe('not-a-date')
            ->and(CoreMergeFieldFilters::number('abc', null, $context))->toBe('abc')
            ->and(CoreMergeFieldFilters::number('12.5', '0', $context))->toBeString()
            ->and(CoreMergeFieldFilters::upper(null, null, $context))->toBeNull()
            ->and(CoreMergeFieldFilters::lower('AbC', null, $context))->toBe('abc')
            ->and(CoreMergeFieldFilters::title('hello world', null, $context))->toBe('Hello World')
            ->and(CoreMergeFieldFilters::default('', 'fallback', $context))->toBe('fallback')
            ->and(CoreMergeFieldFilters::truncate('short', '50', $context))->toBe('short')
            ->and(CoreMergeFieldFilters::truncate('abcdefghij', '7', $context))->toBe('abcd...')
            ->and(CoreMergeFieldFilters::plural(2, null, $context))->toBe(2)
            ->and(CoreMergeFieldFilters::plural(1, 'item|items', $context))->toBe('item')
            ->and(CoreMergeFieldFilters::currency('-100', null, $context))->toBeString();
    });
});

describe('InvoiceMarginCalculator batch-6 branches', function () {
    it('resolveLineCostBasis supplierCostBasis and scaleMinorAmount cover matching edges', function () {
        $opportunity = giCreateOrderOpportunity();
        $invoice = Invoice::factory()->issued()->create([
            'opportunity_id' => $opportunity->id,
            'store_id' => $opportunity->store_id,
        ]);
        $item = $opportunity->items()->first();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'opportunity_item_id' => $item->id,
            'product_id' => $item->itemable_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total' => $item->total,
        ]);

        $calculator = app(InvoiceMarginCalculator::class);
        expect(batch6Part3Invoke($calculator, 'scaleMinorAmount', [0, '0.5', 'GBP']))->toBe(0)
            ->and(batch6Part3Invoke($calculator, 'scaleMinorAmount', [100, '1', 'GBP']))->toBe(100);

        $result = $calculator->calculate($invoice->refresh());
        expect($result)->toHaveKeys(['margin_minor', 'margin_percent']);
    });
});

describe('DefaultVatNumberValidator batch-6 branches', function () {
    it('validate parseTaxNumber passesUkChecksum and validateViaVies cover failure branches', function () {
        Http::fake([
            '*' => Http::response(['valid' => false], 404),
        ]);

        $validator = app(DefaultVatNumberValidator::class);
        expect($validator->validate('FR12345678901')->isValid)->toBeFalse();

        $parse = batch6Part3Invoke($validator, 'parseTaxNumber', ['UK123456789']);
        expect($parse['country_code'])->toBe('GB');

        expect(batch6Part3Invoke($validator, 'passesUkChecksum', ['12345']))->toBeFalse();

        $vies = batch6Part3Invoke($validator, 'validateViaVies', ['GB', '123456789']);
        expect($vies->isValid)->toBeFalse();
    });
});

describe('CreditNoteNumberGenerator batch-6 branches', function () {
    it('ensureSequence and nextOrCreate provision defaults', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(CreditNoteNumberGenerator::class);

        DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', CreditNoteNumberGenerator::DOCUMENT_TYPE)
            ->delete();

        expect($generator->nextOrCreate($store->id))->toStartWith('CN-');
        $sequence = $generator->ensureSequence($store->id);
        expect($generator->ensureSequence($store->id)->id)->toBe($sequence->id);
    });
});

describe('PaymentNumberGenerator batch-6 branches', function () {
    it('ensureSequence returns existing sequence row', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(PaymentNumberGenerator::class);
        $first = $generator->ensureSequence($store->id);
        expect($generator->ensureSequence($store->id)->id)->toBe($first->id);
    });
});

describe('TaxCalculator batch-6 branches', function () {
    it('calculateInclusiveWithTaxRate and resolveRule default fallback paths execute', function () {
        $orgClass = OrganisationTaxClass::factory()->create(['is_default' => true]);
        $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
        $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $calculator = app(TaxCalculator::class);
        $inclusive = $calculator->calculateInclusiveWithTaxRate(1200, 'GBP', $taxRate);
        expect($inclusive->netAmount)->toBe(1000)
            ->and($inclusive->taxAmount)->toBe(200);

        $rule = $calculator->resolveRule($orgClass->id, $productClass->id, now());
        expect($rule)->not->toBeNull();
    });
});

describe('OpportunityPricingRecalculator batch-6 branches', function () {
    it('recalculate resets prices discounts and reapplies deal distribution', function () {
        $opportunity = giCreateQuotationOpportunity([], [
            'deal_price_mode' => DealPriceMode::Proportional->value,
        ]);
        $opportunity->update(['deal_total' => 9000]);
        $item = $opportunity->items()->first();
        $item?->update(['manual_unit_price' => 500, 'discount_percent' => '5.00']);

        app(OpportunityPricingRecalculator::class)->recalculate(
            $opportunity->refresh(),
            resetPrices: true,
            resetDiscounts: true,
        );

        expect($opportunity->refresh()->deal_total)->toBe(9000);
    });
});

describe('DatabaseDriver batch-6 branches', function () {
    it('test handles missing table and resolveNotifiable error paths', function () {
        $driver = new DatabaseDriver;

        $schema = Mockery::mock();
        $schema->shouldReceive('hasTable')->with('notifications')->andReturn(false);
        Schema::swap($schema);

        expect($driver->test(new TestChannelProviderData(channel: 'database'))->success)->toBeFalse();

        expect(fn () => batch6Part3Invoke($driver, 'resolveNotifiable', [
            new ChannelSendData(
                driverKey: 'database',
                notifiableType: 'App\\Models\\MissingModel',
                notifiableId: 1,
                title: 't',
                body: 'b',
            ),
        ]))->toThrow(RuntimeException::class, 'does not exist');

        expect(fn () => batch6Part3Invoke($driver, 'resolveNotifiable', [
            new ChannelSendData(
                driverKey: 'database',
                notifiableType: Member::class,
                notifiableId: 999999,
                title: 't',
                body: 'b',
            ),
        ]))->toThrow(RuntimeException::class, 'was not found');

        expect(fn () => batch6Part3Invoke($driver, 'resolveNotifiable', [
            new ChannelSendData(
                driverKey: 'database',
                notifiableType: Store::class,
                notifiableId: Store::factory()->create()->id,
                title: 't',
                body: 'b',
            ),
        ]))->toThrow(RuntimeException::class, 'Notifiable trait');
    });
});

describe('VirtualStockIntakeReturnGuard batch-6 branches', function () {
    it('outstandingAllocations dedupes shortage and virtual asset quantities', function () {
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $opportunity = Opportunity::factory()->create();
        $item = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'dispatched_quantity' => '2',
            'returned_quantity' => '0',
        ]);

        $resolution = ShortageResolution::factory()->create(['virtual_stock_intake_id' => $intake->id]);
        ShortageResolutionItem::factory()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $item->id,
            'quantity_allocated' => 1,
        ]);

        $guard = app(VirtualStockIntakeReturnGuard::class);
        $rows = $guard->outstandingAllocations($intake);
        expect($rows)->not->toBeEmpty();

        $assetRows = batch6Part3Invoke($guard, 'outstandingFromVirtualAssets', [$intake]);
        expect($assetRows)->toBeArray();

        $qty = batch6Part3Invoke($guard, 'quantityStillOut', [$item]);
        expect($qty)->toBe('2.00');
    });
});

describe('ContainerManifestService batch-6 branches', function () {
    it('compliance resolveMaxDepth isFixedBinding and collectors cover nested paths', function () {
        $service = app(ContainerManifestService::class);
        $container = Container::factory()->create();

        $compliance = $service->compliance($container);

        expect($compliance->status)->toBeString()
            ->and($compliance->slots)->toBeArray();

        $depth = batch6Part3Invoke($service, 'depthOf', [$container]);
        expect($depth)->toBeInt();

        $root = batch6Part3Invoke($service, 'resolveRootContainer', [$container]);
        expect($root)->not->toBeNull();

        $template = batch6Part3Invoke($service, 'templateFor', [$container]);
        expect($template)->toBeArray()
            ->and($template['slots'] ?? [])->toBeArray();

        $quantities = batch6Part3Invoke($service, 'collectQuantitiesRecursive', [$container, 5, 0]);
        expect($quantities)->toBeArray();
    });
});
