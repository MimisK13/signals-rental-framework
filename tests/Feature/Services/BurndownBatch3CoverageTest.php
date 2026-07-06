<?php

/**
 * Coverage burn-down batch 3 — remaining service methods not covered by sibling files.
 */

use App\Contracts\BatchShortageResolverContract;
use App\Data\Notifications\NotificationContextData;
use App\Enums\InvoiceType;
use App\Enums\OpportunityState;
use App\Enums\StockMethod;
use App\Events\AuditableEvent;
use App\Models\ContainerItem;
use App\Models\DiscountCategory;
use App\Models\DocumentSequence;
use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Api\RansackFilter;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\AvailabilityService;
use App\Services\ColumnRegistryResolver;
use App\Services\Documents\DocumentEventDispatcher;
use App\Services\Documents\DocumentRenderErrorSanitizer;
use App\Services\Documents\Drivers\DompdfDriver;
use App\Services\Documents\PdfDriverManager;
use App\Services\Documents\SandboxedBladeRenderer;
use App\Services\Documents\TemplateLineDiff;
use App\Services\Invoices\InvoiceSourceStateRule;
use App\Services\Notifications\MemberNotificationPreferenceCatalog;
use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Notifications\MergeFieldRenderer;
use App\Services\Notifications\NotificationChannelResolver;
use App\Services\Notifications\RecipientResolverRegistry;
use App\Services\Notifications\SmsSegmentCounter;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use App\Services\Pricing\DiscountCategoryApplier;
use App\Services\PurchaseOrders\PurchaseOrderEventRecorder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Services\SettingsService;
use App\Services\Shortages\CostApportionmentContext;
use App\Services\Shortages\MoneyApportionment;
use App\Services\Shortages\ShortageBatchGrouper;
use App\Services\Shortages\ShortageCrossOpportunityScanner;
use App\Services\Shortages\ShortageDetector;
use App\Services\Shortages\ShortageResolverRegistry;
use App\Services\Shortages\Strategies\EvenSplitCostApportionmentStrategy;
use App\Services\Shortages\VirtualStockCostApportionmentRecalculator;
use App\Services\Tax\TaxZoneResolver;
use App\Services\VirtualStock\VirtualStockAssetRetirer;
use App\Services\VirtualStock\VirtualStockIntakeEventRecorder;
use App\Support\Formatter;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use App\ValueObjects\ShortageGroup;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('RansackFilter::applyPredicate default branch', function () {
    it('ignores unknown predicates via the match default arm', function () {
        $filter = new RansackFilter;
        $query = Member::query();
        $baseSql = $query->toRawSql();

        $method = new ReflectionMethod(RansackFilter::class, 'applyPredicate');
        $method->setAccessible(true);
        $method->invoke($filter, $query, 'name', 'unknown_predicate', 'value');

        expect($query->toRawSql())->toBe($baseSql);
    });
});

describe('ColumnRegistryResolver::validateCostGatedColumns', function () {
    it('allows cost-gated columns when the user has costs.view', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('costs.view');

        app(ColumnRegistryResolver::class)->validateColumns(
            'purchase_orders',
            ['number', 'total'],
            'total',
            $user,
        );
    })->throwsNoExceptions();
});

describe('DompdfDriver::render', function () {
    it('renders plain html when no document parts are supplied', function () {
        Pdf::shouldReceive('loadHTML')->once()->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturn('%PDF-1.4');

        $pdf = (new DompdfDriver)->render('<p>Hello</p>', ['size' => 'A4']);

        expect($pdf)->toBe('%PDF-1.4');
    });
});

describe('PdfDriverManager::driver', function () {
    it('throws for unknown driver names', function () {
        expect(fn () => app(PdfDriverManager::class)->driver('missing-driver'))
            ->toThrow(InvalidArgumentException::class, 'Unknown document PDF driver');
    });
});

describe('SandboxedBladeRenderer::executeCompiledTemplate', function () {
    it('returns an empty string when no output buffer is active', function () {
        $compiledPath = tempnam(sys_get_temp_dir(), 'sandbox-blade-');
        file_put_contents($compiledPath, '<?php ob_end_clean();');

        $initialBufferLevel = ob_get_level();
        ob_start();

        $method = new ReflectionMethod(SandboxedBladeRenderer::class, 'executeCompiledTemplate');
        $method->setAccessible(true);

        try {
            expect($method->invoke(null, $compiledPath, []))->toBe('');
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }
        }

        @unlink($compiledPath);
    });
});

describe('TemplateLineDiff::computeOps', function () {
    it('returns no operations when both sides are empty', function () {
        expect((new TemplateLineDiff)->diff('', ''))->toBe([]);
    });
});

describe('InvoiceSourceStateRule::allowedStatesFor', function () {
    it('falls back to defaults when configured source states are empty', function () {
        app(SettingsService::class)->set('invoices.source_states', [], 'json');

        $states = app(InvoiceSourceStateRule::class)->allowedStatesFor(InvoiceType::Deposit);

        expect($states)->toContain(OpportunityState::Quotation->value)
            ->and($states)->toContain(OpportunityState::Order->value);
    });
});

describe('MergeFieldFilterRegistry::apply', function () {
    it('returns the original value when the filter is unknown', function () {
        $registry = app(MergeFieldFilterRegistry::class);

        expect($registry->apply('missing', 'keep-me', null, MergeFieldRenderContext::defaults()))
            ->toBe('keep-me');
    });
});

describe('MergeFieldRenderer::parseExpression', function () {
    it('returns null for empty expressions', function () {
        $renderer = app(MergeFieldRenderer::class);

        expect($renderer->parseExpression(''))->toBeNull();
    });
});

describe('NotificationChannelResolver::toLaravelChannels', function () {
    it('passes through non-email logical channels unchanged', function () {
        expect(app(NotificationChannelResolver::class)->toLaravelChannels(['sms']))
            ->toBe(['sms']);
    });
});

describe('RecipientResolverRegistry::resolveAll', function () {
    it('skips rules with unknown resolver types', function () {
        $type = NotificationType::factory()->create();
        $context = new NotificationContextData(
            type: $type,
            subject: Opportunity::factory()->create(),
        );

        expect(app(RecipientResolverRegistry::class)->resolveAll([
            ['type' => 'not_registered'],
        ], $context))->toBeEmpty();
    });
});

describe('OpportunityEditorTreeService::parentPathFromParentGroupKey', function () {
    it('returns null when parentGroupKey is null', function () {
        $method = new ReflectionMethod(OpportunityEditorTreeService::class, 'parentPathFromParentGroupKey');
        $method->setAccessible(true);

        expect($method->invoke(app(OpportunityEditorTreeService::class), collect(), null))->toBeNull();
    });
});

describe('OpportunityLineItemsTreeBuilder::destinations', function () {
    it('labels unknown group kinds without a prefix', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);

        $treeService = Mockery::mock(OpportunityEditorTreeService::class);
        $treeService->shouldReceive('buildDisplayGroups')->once()->andReturn([
            ['key' => 'custom:1', 'label' => 'Custom node', 'kind' => 'section'],
        ]);

        $builder = new OpportunityLineItemsTreeBuilder(
            app(Formatter::class),
            app(AvailabilityService::class),
            $treeService,
        );

        $labels = collect($builder->destinations($opportunity))->pluck('label');

        expect($labels)->toContain('Custom node')
            ->and($labels->first(fn (string $label): bool => str_starts_with($label, 'Section · ')))->toBeNull();
    });
});

describe('PurchaseOrderStatusRegistry::assertKnown', function () {
    it('throws for unknown purchase order statuses', function () {
        expect(fn () => app(PurchaseOrderStatusRegistry::class)->assertKnown('mystery'))
            ->toThrow(InvalidArgumentException::class, 'Unknown purchase order status');
    });
});

describe('MoneyApportionment::apportion', function () {
    it('returns floors immediately when the target divides evenly', function () {
        expect((new MoneyApportionment)->apportion([1 => 1, 2 => 1], 100))
            ->toBe([1 => 50, 2 => 50]);
    });
});

describe('AbstractCostApportionmentStrategy::calculate', function () {
    it('rejects non-context inputs', function () {
        expect(fn () => app(EvenSplitCostApportionmentStrategy::class)->calculate(new stdClass))
            ->toThrow(InvalidArgumentException::class, 'Expected CostApportionmentContext');
    });
});

describe('EvenSplitCostApportionmentStrategy::calculateForContext', function () {
    it('returns an empty allocation when no opportunities participate', function () {
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [],
            opportunities: [],
            primaryOpportunityId: null,
            totalCostMinor: 0,
        );

        expect(app(EvenSplitCostApportionmentStrategy::class)->calculate($context))->toBe([]);
    });
});

describe('TaxZoneResolver::resolveCountryCode', function () {
    it('returns null for blank country codes', function () {
        expect(app(TaxZoneResolver::class)->resolveCountryCode('   '))->toBeNull();
    });
});

describe('VirtualStockAssetRetirer::retireForIntake', function () {
    it('skips assets already in a terminal virtual status', function () {
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $asset = StockLevel::factory()->virtual()->create([
            'virtual_stock_intake_id' => $intake->id,
            'virtual_asset_status' => 'retired',
            'ends_at' => now()->subDay(),
        ]);

        app(VirtualStockAssetRetirer::class)->retireForIntake($intake, Carbon::now());

        expect($asset->fresh()->ends_at->toDateString())
            ->toBe(now()->subDay()->toDateString());
    });
});

describe('DocumentEventDispatcher::dispatchWebhook', function () {
    it('does not dispatch webhooks for unsupported actions during replay', function () {
        Queue::fake();
        Verbs::shouldReceive('isReplaying')->andReturnTrue();

        $dispatcher = app(DocumentEventDispatcher::class);
        $sequence = DocumentSequence::factory()->create();
        $method = new ReflectionMethod($dispatcher, 'dispatchWebhook');
        $method->setAccessible(true);
        $method->invoke($dispatcher, 'document_sequence.updated', ['document_sequence' => []]);

        Queue::assertNothingPushed();
    });

    it('does not dispatch webhooks for actions outside the webhook event list', function () {
        Queue::fake();
        Verbs::shouldReceive('isReplaying')->andReturnFalse();

        $dispatcher = app(DocumentEventDispatcher::class);
        $method = new ReflectionMethod($dispatcher, 'dispatchWebhook');
        $method->setAccessible(true);
        $method->invoke($dispatcher, 'document_sequence.custom_action', ['document_sequence' => []]);

        Queue::assertNothingPushed();
    });
});

describe('MemberNotificationPreferenceCatalog::normalizeChannels', function () {
    it('normalizes database and mail channels for member preferences', function () {
        $user = User::factory()->create();
        $type = NotificationType::factory()->create([
            'default_channels' => ['in_app', 'email'],
            'available_channels' => ['in_app', 'email'],
        ]);

        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type_id' => $type->id,
            'channels' => ['database', 'mail'],
            'is_muted' => false,
        ]);

        $row = app(MemberNotificationPreferenceCatalog::class)
            ->forUser($user)
            ->first();

        expect($row['member_channels'])->toBe(['in_app', 'email']);
    });
});

describe('DiscountCategoryApplier::apply', function () {
    it('returns early when the opportunity has no member discount category', function () {
        $initialBufferLevel = ob_get_level();
        $opportunity = Opportunity::factory()->create(['member_id' => null]);
        OpportunityItem::factory()->for($opportunity)->create();

        try {
            app(DiscountCategoryApplier::class)->apply($opportunity);

            foreach ($opportunity->fresh()->items as $item) {
                expect($item->discount_percent)->toBeNull();
            }
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }
        }
    });

    it('returns early when the resolved discount category is inactive', function () {
        $initialBufferLevel = ob_get_level();
        $category = DiscountCategory::factory()->create(['is_active' => false]);
        $member = Member::factory()->create(['discount_category_id' => $category->id]);
        $opportunity = Opportunity::factory()->create(['member_id' => $member->id]);
        OpportunityItem::factory()->for($opportunity)->create();

        try {
            app(DiscountCategoryApplier::class)->apply($opportunity, $category->id);

            foreach ($opportunity->fresh()->items as $item) {
                expect($item->discount_percent)->toBeNull();
            }
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }
        }
    });
});

describe('CostApportionmentContext::opportunityWindow', function () {
    it('derives rental windows from opportunity charge dates when explicit windows are absent', function () {
        $opportunity = Opportunity::factory()->create([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(3),
            'charge_starts_at' => null,
            'charge_ends_at' => null,
        ]);
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [$opportunity->id => 1],
            opportunities: [$opportunity->id => $opportunity],
            primaryOpportunityId: $opportunity->id,
            totalCostMinor: 1000,
        );

        $window = $context->opportunityWindow($opportunity->id);

        expect($window)->not->toBeNull()
            ->and($window['from']->toIso8601String())->toBe($opportunity->starts_at->copy()->utc()->toIso8601String())
            ->and($window['until']->toIso8601String())->toBe($opportunity->ends_at->copy()->utc()->toIso8601String());
    });

    it('returns null when neither explicit nor derived dates exist', function () {
        $opportunity = Opportunity::factory()->create([
            'starts_at' => null,
            'ends_at' => null,
            'charge_starts_at' => null,
            'charge_ends_at' => null,
        ]);
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [$opportunity->id => 1],
            opportunities: [$opportunity->id => $opportunity],
            primaryOpportunityId: $opportunity->id,
            totalCostMinor: 1000,
        );

        expect($context->opportunityWindow($opportunity->id))->toBeNull();
    });

    it('returns precomputed rental windows when provided on the context', function () {
        $from = Carbon::now()->subDays(2)->utc();
        $until = Carbon::now()->addDays(2)->utc();
        $intake = VirtualStockIntake::factory()->confirmed()->create();
        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [99 => 1],
            opportunities: [],
            primaryOpportunityId: 99,
            totalCostMinor: 1000,
            rentalWindowsByOpportunity: [
                99 => ['from' => $from, 'until' => $until],
            ],
        );

        expect($context->opportunityWindow(99))->toBe([
            'from' => $from,
            'until' => $until,
        ]);
    });
});

describe('ShortageBatchGrouper::buildGroup', function () {
    it('expands grouped date ranges and totals across multiple shortages', function () {
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $early = Shortage::make(
            opportunityItemId: 1,
            opportunityId: 10,
            productId: $product->id,
            productName: $product->name,
            storeId: $store->id,
            requestedQuantity: 5,
            availableQuantity: 1,
            trackingType: StockMethod::Bulk,
            startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
            endsAt: Carbon::parse('2026-07-03T17:00:00Z'),
            isCritical: false,
        );
        $late = Shortage::make(
            opportunityItemId: 2,
            opportunityId: 11,
            productId: $product->id,
            productName: $product->name,
            storeId: $store->id,
            requestedQuantity: 4,
            availableQuantity: 0,
            trackingType: StockMethod::Bulk,
            startsAt: Carbon::parse('2026-06-28T09:00:00Z'),
            endsAt: Carbon::parse('2026-07-06T17:00:00Z'),
            isCritical: false,
        );

        $group = app(ShortageBatchGrouper::class)->groupsFrom(new ShortageCollection([$early, $late]))[0];

        expect($group->dateRangeStart->toDateString())->toBe('2026-06-28')
            ->and($group->dateRangeEnd->toDateString())->toBe('2026-07-06')
            ->and($group->totalShortfall)->toBe($early->remainingShortfall() + $late->remainingShortfall());
    });
});

describe('SmsSegmentCounter::analyse', function () {
    it('returns zero segments for empty messages', function () {
        expect(app(SmsSegmentCounter::class)->analyse(''))->toBe([
            'characters' => 0,
            'segments' => 0,
            'per_segment' => 160,
        ]);
    });
});

describe('ShortageCrossOpportunityScanner::withPeerShortages', function () {
    it('returns local shortages unchanged when the store id is missing', function () {
        $opportunity = Opportunity::factory()->create(['store_id' => null]);
        $shortage = Shortage::make(
            opportunityItemId: 1,
            opportunityId: $opportunity->id,
            productId: 1,
            productName: 'Widget',
            storeId: 1,
            requestedQuantity: 2,
            availableQuantity: 0,
            trackingType: StockMethod::Bulk,
            startsAt: Carbon::now(),
            endsAt: Carbon::now()->addDay(),
            isCritical: false,
        );
        $collection = new ShortageCollection([$shortage]);

        expect(app(ShortageCrossOpportunityScanner::class)->withPeerShortages($opportunity, $collection))
            ->toBe($collection);
    });

    it('merges unresolved peer shortages for the same product at the same store', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity_held' => 0,
        ]);

        $localOpp = Opportunity::factory()->create([
            'store_id' => $store->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(5),
        ]);
        $peerOpp = Opportunity::factory()->create([
            'store_id' => $store->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(5),
        ]);

        OpportunityItem::factory()->for($localOpp)->create([
            'item_type' => 'product',
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => '3',
        ]);
        $peerItem = OpportunityItem::factory()->for($peerOpp)->create([
            'item_type' => 'product',
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => '2',
        ]);

        $localShortage = app(ShortageDetector::class)->forItem(
            $localOpp->items()->first(),
            $localOpp,
        );
        $collection = new ShortageCollection([$localShortage]);

        $merged = app(ShortageCrossOpportunityScanner::class)->withPeerShortages($localOpp, $collection);

        expect($merged)->toHaveCount(2)
            ->and($merged->contains(fn (Shortage $s): bool => $s->opportunityItemId === $peerItem->id))->toBeTrue();
    });
});

describe('ShortageResolverRegistry::batchApplicableTo', function () {
    it('returns batch resolvers that support and accept the group', function () {
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $supplier = Member::factory()->organisation()->create();

        ProductSupplierCost::factory()->preferred()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
        ]);

        $group = new ShortageGroup(
            productId: $product->id,
            productName: $product->name,
            storeId: $store->id,
            dateRangeStart: Carbon::now(),
            dateRangeEnd: Carbon::now()->addDay(),
            totalShortfall: 2,
            shortages: [],
        );

        $applicable = app(ShortageResolverRegistry::class)->batchApplicableTo($group);

        expect($applicable)->not->toBeEmpty()
            ->and($applicable[0])->toBeInstanceOf(BatchShortageResolverContract::class);
    });
});

describe('PurchaseOrderEventRecorder::record', function () {
    it('skips audit and webhook dispatch while verbs are replaying', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Verbs::shouldReceive('isReplaying')->andReturnTrue();

        $purchaseOrder = PurchaseOrder::factory()->create();
        app(PurchaseOrderEventRecorder::class)->updated($purchaseOrder);

        Event::assertNotDispatched(AuditableEvent::class);
        Queue::assertNothingPushed();
    });
});

describe('VirtualStockIntakeEventRecorder::record', function () {
    it('skips audit and webhook dispatch while verbs are replaying', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Verbs::shouldReceive('isReplaying')->andReturnTrue();

        $intake = VirtualStockIntake::factory()->create();
        app(VirtualStockIntakeEventRecorder::class)->updated($intake);

        Event::assertNotDispatched(AuditableEvent::class);
        Queue::assertNothingPushed();
    });
});

describe('VirtualStockCostApportionmentRecalculator::usesDateSensitiveStrategy', function () {
    it('detects date-sensitive strategies from intake overrides and settings defaults', function () {
        $recalculator = app(VirtualStockCostApportionmentRecalculator::class);
        $method = new ReflectionMethod($recalculator, 'usesDateSensitiveStrategy');
        $method->setAccessible(true);

        $explicit = VirtualStockIntake::factory()->create([
            'cost_apportionment_strategy' => 'proportional_quantity_duration',
        ]);
        expect($method->invoke($recalculator, $explicit))->toBeTrue();

        settings()->set('shortages.cost_apportionment_strategy', 'proportional_quantity_duration');
        $fromSettings = VirtualStockIntake::factory()->create(['cost_apportionment_strategy' => null]);
        expect($method->invoke($recalculator, $fromSettings))->toBeTrue();

        settings()->set('shortages.cost_apportionment_strategy', 'primary_job');
        $nonSensitive = VirtualStockIntake::factory()->create(['cost_apportionment_strategy' => '']);
        expect($method->invoke($recalculator, $nonSensitive))->toBeFalse();
    });
});

describe('ContainerDemandResolver::isFixedSlot', function () {
    it('returns false when the container product relation is missing', function () {
        $resolver = app(ContainerDemandResolver::class);
        $method = new ReflectionMethod($resolver, 'isFixedSlot');
        $method->setAccessible(true);

        $item = ContainerItem::factory()->make();
        $item->setRelation('container', null);

        expect($method->invoke($resolver, $item))->toBeFalse();
    });
});

describe('DocumentRenderErrorSanitizer::sanitize', function () {
    it('returns the fallback message for empty exceptions and truncates long messages', function () {
        expect(DocumentRenderErrorSanitizer::sanitize(new Exception('')))
            ->toBe('Unable to render document template.');

        $sanitized = DocumentRenderErrorSanitizer::sanitize(new Exception(str_repeat('x', 600)));

        expect(strlen($sanitized))->toBe(502)
            ->and(str_ends_with($sanitized, '…'))->toBeTrue();
    });
});
