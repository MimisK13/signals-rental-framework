<?php

/**
 * P4-CL4b coverage burn-down batch 6 — classes 28–45 (DocsService through InvoiceStatusResolver).
 */

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\PricingRoundingDirection;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Exceptions\Documents\DocumentRenderException;
use App\Models\BundleRule;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\VirtualStockIntake;
use App\Services\AvailabilityService;
use App\Services\DocsService;
use App\Services\Documents\CreditNoteDataResolver;
use App\Services\Documents\ReceiptDataResolver;
use App\Services\Documents\SandboxedExpressionValidator;
use App\Services\Documents\SandboxedLoopEnvironment;
use App\Services\Documents\StatementDataResolver;
use App\Services\FileService;
use App\Services\Invoices\InvoiceStatusResolver;
use App\Services\Pricing\BundlePricingApplicator;
use App\Services\Pricing\DealPriceProportionalDistributor;
use App\Services\Pricing\SurchargeEvaluator;
use App\Services\Shortages\CostApportionmentContextBuilder;
use App\Services\Shortages\Resolvers\SubHireResolver;
use App\Services\Shortages\SubHireCostCalculator;
use App\Services\Tax\MemberVatValidationService;
use App\Services\VirtualStock\VirtualAssetStatusRegistry;
use App\Services\VirtualStock\VirtualStockIntakeProfitAndLossBuilder;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageGroup;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create();
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

function batch6DocsDir(): string
{
    $dir = sys_get_temp_dir().'/docs-batch6-'.uniqid();
    mkdir($dir, 0755, true);
    mkdir($dir.'/guide', 0755, true);
    mkdir($dir.'/changelog', 0755, true);

    file_put_contents($dir.'/documentation.json', json_encode([
        'sections' => [[
            'slug' => 'guide',
            'title' => 'Guide',
            'pages' => [[
                'slug' => 'intro',
                'title' => 'Intro',
                'description' => 'Intro page',
            ]],
        ]],
    ]));
    file_put_contents($dir.'/guide/intro.md', "# Intro\n\n## First {#first}\n\nBody.");
    file_put_contents($dir.'/changelog/1.0.0.md', "---\nversion: 1.0.0\ndate: 1700000000\n---\n# v1");

    return $dir;
}

function batch6RemoveDocsDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/**/*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir.'/guide');
    @rmdir($dir.'/changelog');
    @unlink($dir.'/documentation.json');
    @rmdir($dir);
}

describe('DocsService batch-6 branches', function () {
    it('extractTableOfContents skips headings without ids and resolveDisplayContext finds manifest pages', function () {
        App::shouldReceive('environment')->with('local')->andReturn(true);

        $dir = batch6DocsDir();

        try {
            $service = new DocsService($dir);
            $html = '<h2 id="one">One</h2><h3>No Id</h3><h3 id="two">Two</h3>';

            expect($service->extractTableOfContents($html))->toHaveCount(2)
                ->and($service->resolveDisplayContext('guide', 'intro'))->toMatchArray([
                    'sectionSlug' => 'guide',
                    'sectionTitle' => 'Guide',
                ])
                ->and($service->getLatestChangelogVersion())->toBe('1.0.0');

            $indexMethod = new ReflectionMethod(DocsService::class, 'buildSearchIndex');
            $indexMethod->setAccessible(true);
            expect($indexMethod->invoke($service))->not->toBeEmpty();
        } finally {
            batch6RemoveDocsDir($dir);
        }
    });
});

describe('SubHireResolver batch-6 branches', function () {
    it('canResolveBatch rejects zero shortfall groups and getBatchOptions returns empty', function () {
        $store = Store::factory()->create();
        $product = Product::factory()->rental()->bulk()->create();
        $resolver = app(SubHireResolver::class);
        $emptyGroup = new ShortageGroup(
            productId: $product->id,
            productName: $product->name,
            storeId: $store->id,
            dateRangeStart: now(),
            dateRangeEnd: now()->addDay(),
            totalShortfall: 0,
            shortages: [],
        );

        expect($resolver->canResolveBatch($emptyGroup))->toBeFalse()
            ->and($resolver->getBatchOptions($emptyGroup))->toBe([]);

        $execute = new ReflectionMethod(SubHireResolver::class, 'executeSubHire');
        $execute->setAccessible(true);
        $shortage = Shortage::make(
            opportunityItemId: 1,
            opportunityId: 1,
            productId: $product->id,
            productName: $product->name,
            storeId: $store->id,
            requestedQuantity: 2,
            availableQuantity: 0,
            trackingType: StockMethod::Bulk,
            startsAt: now(),
            endsAt: now()->addDay(),
            isCritical: false,
        );
        $option = new ResolutionOption(
            resolverKey: 'subhire',
            type: ShortageResolutionType::SubHire,
            label: 'Sub-hire',
            description: 'Missing supplier metadata',
            quantityResolved: 1,
            isPartial: false,
            autoExecutable: false,
            metadata: [],
        );

        expect(fn () => $execute->invoke(
            $resolver,
            [1 => 1],
            $product->id,
            $store->id,
            1,
            now(),
            now()->addDay(),
            $option,
            $shortage,
        ))->toThrow(ValidationException::class, 'missing supplier provenance');
    });
});

describe('SandboxedLoopEnvironment batch-6 branches', function () {
    it('caps oversized arrays, throws for oversized countable collections, and tracks nested loops', function () {
        config(['documents.max_loop_iterations' => 2]);

        expect(SandboxedLoopEnvironment::prepareLoopData([1, 2, 3, 4]))->toBe([1, 2])
            ->and(fn () => SandboxedLoopEnvironment::prepareLoopData(LazyCollection::make([1, 2, 3])))
            ->toThrow(DocumentRenderException::class, 'maximum of 2 iterations');

        $env = new SandboxedLoopEnvironment;
        $env->addLoop([1, 2]);
        expect($env->getLastLoop()?->depth)->toBe(1);
        $env->incrementLoopIndices();
        expect($env->getLastLoop()?->iteration)->toBe(1);
    });
});

describe('SurchargeEvaluator batch-6 branches', function () {
    it('covers default tax class lookup, non-surchargeable lines, temporal guards, and filtered amounts', function () {
        ProductTaxClass::factory()->create(['is_default' => true]);

        $group = SurchargeGroup::factory()->create();
        $opportunity = Opportunity::factory()->create(['surcharge_group_ids' => [$group->id]]);
        $product = Product::factory()->rental()->create();
        $item = OpportunityItem::factory()->for($opportunity)->create([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::SubRental,
        ]);

        $evaluator = app(SurchargeEvaluator::class);
        expect($evaluator->defaultProductTaxClassId())->not->toBeNull()
            ->and($evaluator->evaluateForItem(
                $item->load('item'),
                $opportunity,
                collect(),
                10_000,
                'GBP',
                now(),
                null,
            ))->toBe([]);

        $future = Surcharge::factory()->create([
            'surcharge_group_id' => $group->id,
            'valid_from' => now()->addYear(),
            'criteria' => [],
            'is_default' => true,
        ]);

        expect($evaluator->surchargesForGroups([$group->id]))->not->toBeEmpty()
            ->and($evaluator->evaluateForItem(
                $item->load('item'),
                $opportunity,
                collect([$future]),
                10_000,
                'GBP',
                now(),
                $product->tax_class_id,
            ))->toBe([]);
    });
});

describe('BundlePricingApplicator batch-6 branches', function () {
    it('applies fixed bundle pricing via largest-remainder distribution', function () {
        $opportunity = Opportunity::factory()->create();
        $productA = Product::factory()->rental()->create();
        $productB = Product::factory()->rental()->create();

        $itemA = OpportunityItem::factory()->for($opportunity)->create([
            'itemable_id' => $productA->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 5000,
        ]);
        $itemB = OpportunityItem::factory()->for($opportunity)->create([
            'itemable_id' => $productB->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 5000,
        ]);

        BundleRule::factory()
            ->forProducts([$productA->id, $productB->id])
            ->fixed(8000)
            ->create();

        $items = $opportunity->fresh('items')->items;
        $baseSubtotals = [
            $itemA->id => 5000,
            $itemB->id => 5000,
        ];

        $adjusted = app(BundlePricingApplicator::class)->apply(
            $opportunity,
            $items,
            $baseSubtotals,
            'GBP',
        );

        expect(array_sum($adjusted))->toBe(8000);
    });
});

describe('AvailabilityService batch-6 branches', function () {
    beforeEach(function () {
        $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
        {
            public function resolve(): AvailabilityResolution
            {
                return AvailabilityResolution::Daily;
            }
        });
        $this->store = Store::factory()->create(['timezone' => 'UTC']);
        $this->service = app(AvailabilityService::class);
    });

    it('checkAssetAvailable rejects missing and retired virtual assets', function () {
        $missing = $this->service->checkAssetAvailable(999999, now(), now()->addDay());
        expect($missing)->toBeFalse();

        $virtual = StockLevel::factory()->serialised()->create([
            'store_id' => $this->store->id,
            'is_virtual' => true,
            'virtual_asset_status' => 'retired',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        expect($this->service->checkAssetAvailable(
            $virtual->id,
            now(),
            now()->addDay(),
        ))->toBeFalse();
    });

    it('getCalendarForStores and getGanttForStores tolerate empty store lists', function () {
        expect($this->service->getCalendarForStores([], now(), now()->addDay()))->toBeEmpty()
            ->and($this->service->getGanttForStores(1, [], now(), now()->addDay())->demands)->toBe([]);
    });
});

describe('FileService batch-6 branches', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->service = app(FileService::class);
    });

    it('storeBinary without entity, getContents failures, documentFilename fallbacks, and signedDocumentUrlOrNull guards', function () {
        $path = $this->service->storeBinary('hello', 'exports', 'txt');
        expect($path)->toStartWith('exports/')
            ->and($this->service->getContents($path))->toBe('hello');

        expect(fn () => $this->service->getContents('missing.txt'))
            ->toThrow(RuntimeException::class, 'File not found');

        $document = Document::factory()->create([
            'document_number' => '',
            'pdf_path' => 'docs/test.pdf',
        ]);
        Storage::disk('public')->put('docs/test.pdf', '%PDF');

        expect($this->service->documentFilename($document, 'docs/test.pdf'))->toContain('.pdf')
            ->and($this->service->signedDocumentUrlOrNull(null))->toBeNull()
            ->and($this->service->signedDocumentUrlOrNull($document))->not->toBeNull();
    });
});

describe('SubHireCostCalculator batch-6 branches', function () {
    it('computes rental weeks and per-week PO economics', function () {
        $calculator = app(SubHireCostCalculator::class);
        $starts = Carbon::parse('2026-01-01T00:00:00Z');
        $ends = Carbon::parse('2026-01-15T00:00:00Z');

        expect($calculator->rentalWeeks($starts, $ends))->toBe(3);

        $weekly = ProductSupplierCost::factory()->make([
            'cost_basis' => 'per_week',
            'cost' => 1000,
        ]);

        expect($calculator->estimatedTotal($weekly, 2, $starts, $ends))->toBe(6000)
            ->and($calculator->purchaseOrderUnitCost($weekly, $starts, $ends))->toBe(3000)
            ->and($calculator->purchaseOrderQuantity($weekly, 5))->toBe('5.00')
            ->and($calculator->purchaseOrderQuantity(
                ProductSupplierCost::factory()->make(['cost_basis' => 'flat']),
                5,
            ))->toBe('1.00');
    });
});

describe('CostApportionmentContextBuilder batch-6 branches', function () {
    it('accumulates rental windows and falls back to purchase order opportunity', function () {
        $intake = VirtualStockIntake::factory()->confirmed()->create(['quantity' => 2]);
        $opportunity = Opportunity::factory()->create();
        $item = OpportunityItem::factory()->for($opportunity)->create([
            'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
            'ends_at' => Carbon::parse('2026-08-03T17:00:00Z'),
        ]);
        $itemTwo = OpportunityItem::factory()->for($opportunity)->create([
            'starts_at' => Carbon::parse('2026-07-28T09:00:00Z'),
            'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
        ]);

        $resolution = ShortageResolution::factory()->create([
            'virtual_stock_intake_id' => $intake->id,
        ]);
        ShortageResolutionItem::factory()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $item->id,
            'quantity_allocated' => 1,
        ]);
        ShortageResolutionItem::factory()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $itemTwo->id,
            'quantity_allocated' => 1,
        ]);

        $context = app(CostApportionmentContextBuilder::class)->build($intake);
        expect($context->quantitiesByOpportunity[$opportunity->id])->toBe(2)
            ->and($context->opportunityWindow($opportunity->id))->not->toBeNull();

        $poIntake = VirtualStockIntake::factory()->confirmed()->create([
            'quantity' => 1,
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'opportunity_id' => $opportunity->id,
            ])->id,
        ]);
        ShortageResolution::query()->where('virtual_stock_intake_id', $poIntake->id)->delete();

        $poContext = app(CostApportionmentContextBuilder::class)->build($poIntake);
        expect($poContext->quantitiesByOpportunity[$opportunity->id])->toBe(1);
    });
});

describe('ReceiptDataResolver batch-6 branches', function () {
    it('resolve includes null-store fallbacks and formatted dates', function () {
        $invoice = Invoice::factory()->issued()->create(['store_id' => null]);
        $payment = InvoicePayment::factory()->create([
            'invoice_id' => $invoice->id,
            'paid_at' => Carbon::parse('2026-06-15T12:00:00Z'),
        ]);

        $data = app(ReceiptDataResolver::class)->resolve(
            app(ReceiptDataResolver::class)->loadSource($payment->id),
        );

        expect($data['store']['name'])->toBeNull()
            ->and($data['payment']['received_at'])->toBeString();
    });
});

describe('SandboxedExpressionValidator batch-6 branches', function () {
    it('covers helper token messages and significant index scanners', function () {
        $validator = new SandboxedExpressionValidator;

        $messages = new ReflectionMethod(SandboxedExpressionValidator::class, 'forbiddenTokenMessages');
        $messages->setAccessible(true);
        expect($messages->invoke($validator))->not->toBeEmpty();

        expect(fn () => $validator->validate('$x ?? $y'))
            ->toThrow(DocumentRenderException::class);

        $next = new ReflectionMethod(SandboxedExpressionValidator::class, 'nextSignificantIndex');
        $next->setAccessible(true);
        $prev = new ReflectionMethod(SandboxedExpressionValidator::class, 'previousSignificantIndex');
        $prev->setAccessible(true);

        expect($next->invoke($validator, '  $a  + 1', 0))->toBeInt()
            ->and($prev->invoke($validator, '  $a  + 1', 8))->toBeInt();
    });
});

describe('VirtualAssetStatusRegistry batch-6 branches', function () {
    it('reports availability supply flags and enforces transition guards', function () {
        $registry = app(VirtualAssetStatusRegistry::class);

        expect($registry->suppliesAvailability('available'))->toBeTrue()
            ->and($registry->canTransitionTo('available', 'missing'))->toBeFalse()
            ->and(fn () => $registry->assertCanTransitionTo('retired', 'available'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => $registry->assertKnown('not-real'))
            ->toThrow(InvalidArgumentException::class, 'Unknown virtual asset status');
    });
});

describe('StatementDataResolver batch-6 branches', function () {
    it('resolve handles missing store context and formats dates', function () {
        $member = Member::factory()->create();
        Invoice::factory()->issued()->create([
            'member_id' => $member->id,
            'store_id' => null,
            'issued_at' => Carbon::parse('2026-05-01T00:00:00Z'),
            'amount_outstanding' => 1000,
        ]);

        $data = app(StatementDataResolver::class)->resolve(
            app(StatementDataResolver::class)->loadSource($member->id),
        );

        expect($data['store']['name'])->toBeNull()
            ->and($data['statement_date'])->toBeString()
            ->and($data['outstanding_invoices'])->not->toBeEmpty();
    });
});

describe('CreditNoteDataResolver batch-6 branches', function () {
    it('resolve includes tax group and null store fallbacks', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
        $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => $taxRate->id,
            'is_active' => true,
        ]);

        $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
        $invoice = Invoice::factory()->issued()->create([
            'member_id' => $member->id,
            'store_id' => null,
        ]);
        $creditNote = CreditNote::factory()->create([
            'invoice_id' => $invoice->id,
            'issued_at' => Carbon::parse('2026-04-01T00:00:00Z'),
        ]);
        $creditNote->update(['store_id' => null]);

        $data = app(CreditNoteDataResolver::class)->resolve(
            app(CreditNoteDataResolver::class)->loadSource($creditNote->id),
        );

        expect($data['store']['name'])->toBeNull()
            ->and($data['tax_summary'])->toBeArray();
    });
});

describe('DealPriceProportionalDistributor batch-6 branches', function () {
    it('distributes deal price across scoped group lines', function () {
        $opportunity = Opportunity::factory()->create();
        $group = OpportunityItem::factory()->for($opportunity)->create([
            'item_type' => OpportunityItemType::Group,
            'path' => '1',
        ]);
        $product = Product::factory()->rental()->create(['discountable' => true]);
        OpportunityItem::factory()->for($opportunity)->create([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'revenue_group_id' => $group->id,
            'path' => '1.1',
            'quantity' => '1',
            'unit_price' => 10_000,
            'item_type' => OpportunityItemType::Product,
        ]);

        $result = app(DealPriceProportionalDistributor::class)->distribute(
            $opportunity->fresh('items'),
            9000,
            $group,
        );

        expect($result['applications'])->not->toBeEmpty()
            ->and($result['original_total'])->toBeGreaterThan(0);
    });
});

describe('VirtualStockIntakeProfitAndLossBuilder batch-6 branches', function () {
    it('builds revenue rows from resolution pivots and PO-only intakes', function () {
        $intake = VirtualStockIntake::factory()->confirmed()->create(['quantity' => 1, 'unit_cost' => 500]);
        $opportunity = Opportunity::factory()->create(['subject' => 'P&L Job']);
        $item = OpportunityItem::factory()->for($opportunity)->create(['total' => 2000, 'quantity' => '2']);
        $resolution = ShortageResolution::factory()->create(['virtual_stock_intake_id' => $intake->id]);
        ShortageResolutionItem::factory()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $item->id,
            'quantity_allocated' => 1,
        ]);

        OpportunityCost::factory()->create([
            'virtual_stock_intake_id' => $intake->id,
            'opportunity_id' => $opportunity->id,
            'amount' => 500,
        ]);

        $built = app(VirtualStockIntakeProfitAndLossBuilder::class)->build($intake, 'even_split');
        expect($built['rows'][0]['revenue_minor'])->toBeGreaterThan(0)
            ->and($built['rows'][0]['margin_minor'])->toBeInt();

        $poIntake = VirtualStockIntake::factory()->confirmed()->create([
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'opportunity_id' => $opportunity->id,
            ])->id,
        ]);
        ShortageResolution::query()->where('virtual_stock_intake_id', $poIntake->id)->delete();

        $poBuilt = app(VirtualStockIntakeProfitAndLossBuilder::class)->build($poIntake, 'even_split');
        expect($poBuilt['rows'])->not->toBeEmpty();
    });
});

describe('MemberVatValidationService batch-6 branches', function () {
    it('handles validation expiry, tax number changes, and clearing state', function () {
        Queue::fake();
        Http::fake();
        settings()->set('finance.vat_validation_validity_days', 30);

        $service = app(MemberVatValidationService::class);
        $member = Member::factory()->organisation()->create([
            'tax_number' => 'GB123456789',
            'tax_number_validated' => true,
            'tax_number_validated_at' => now()->subDays(60),
        ]);

        expect($service->isValidatedForTaxResolution($member))->toBeFalse()
            ->and($service->scopeValidationExpired(Member::query())->pluck('id'))->toContain($member->id);

        $member->update(['tax_number' => '']);
        $service->handleTaxNumberChange($member, 'GB123456789');
        expect($member->fresh()->tax_number_validated)->toBeNull();

        $member->update(['tax_number' => 'GB999999999']);
        $service->requestValidation($member->fresh());
        Queue::assertPushed(\App\Jobs\ValidateMemberVatNumber::class);
    });
});

describe('InvoiceStatusResolver batch-6 branches', function () {
    it('throws for malformed rules and evaluates extended condition operators', function () {
        config([
            'invoices.status_resolution' => [
                ['when' => 'not-an-array', 'status' => 'broken'],
                ['when' => ['amount_paid' => ['gt' => 0]], 'status' => ''],
                ['when' => ['amount_outstanding' => ['gte' => 0]], 'status' => 'issued'],
            ],
        ]);

        $resolver = app(InvoiceStatusResolver::class);
        $now = Carbon::parse('2026-07-10T12:00:00Z');

        expect(fn () => $resolver->resolve('issued', 1, 100, 99, $now, $now))
            ->toThrow(InvalidArgumentException::class, 'missing a status key');

        $matches = new ReflectionMethod(InvoiceStatusResolver::class, 'matchesCondition');
        $matches->setAccessible(true);

        expect($matches->invoke($resolver, 5, ['lt' => 10], $now))->toBeTrue()
            ->and($matches->invoke($resolver, 5, ['lte' => 5], $now))->toBeTrue()
            ->and($matches->invoke($resolver, 5, ['gte' => 5], $now))->toBeTrue()
            ->and($matches->invoke($resolver, 'x', ['blank' => false], $now))->toBeTrue()
            ->and($matches->invoke($resolver, 'x', ['unknown' => true], $now))->toBeFalse()
            ->and($matches->invoke($resolver, $now->copy()->subDay(), ['past' => false], $now))->toBeFalse();
    });
});
