<?php

/**
 * P4-CL4b coverage burn-down batch 6 — first 30 service classes (cheapest-first slice).
 */

use App\Contracts\Documents\DocumentRenderer;
use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\TestChannelProviderData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Enums\DocumentSequenceResetFrequency;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Events\AuditableEvent;
use App\Models\ApiIdempotencyKey;
use App\Models\ChannelProvider;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityItemSurcharge;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\ProductTaxClass;
use App\Models\SerialisedComponent;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\User;
use App\Models\VirtualStockCostOverride;
use App\Models\VirtualStockIntake;
use App\Services\Api\IdempotencyKeyService;
use App\Services\Api\WebhookEventRegistry;
use App\Services\Availability\KitCompositionGuard;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Containers\KitContainerReturnCompletion;
use App\Services\Dashboard\DashboardKpiService;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\PageDimensions;
use App\Services\Documents\SandboxedLoopEnvironment;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use App\Services\Notifications\CommunicationPreferences;
use App\Services\Notifications\CustomerCommunicationAttachmentResolver;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Opportunities\OpportunityNumberGenerator;
use App\Services\Pricing\DiscountCriteriaPredicateRegistry;
use App\Services\Pricing\DiscountRuleEvaluator;
use App\Services\Pricing\OpportunitySurchargeApplicator;
use App\Services\Pricing\QuantityMinorArithmetic;
use App\Services\Shortages\CostApportionmentContext;
use App\Services\Shortages\CostApportionmentContextBuilder;
use App\Services\Shortages\Strategies\ManualCostApportionmentStrategy;
use App\Services\Shortages\Strategies\ProportionalQuantityCostApportionmentStrategy;
use App\Services\Shortages\Strategies\ProportionalQuantityDurationCostApportionmentStrategy;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use App\ValueObjects\ShortageGroup;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create();
});

describe('DashboardKpiService batch-6 branches', function () {
    it('storeIdsForUser returns null for a guest and percentChange returns null when previous is zero', function () {
        $service = app(DashboardKpiService::class);

        $storeIdsMethod = new ReflectionMethod(DashboardKpiService::class, 'storeIdsForUser');
        $storeIdsMethod->setAccessible(true);
        expect($storeIdsMethod->invoke($service, null))->toBeNull();

        $percentMethod = new ReflectionMethod(DashboardKpiService::class, 'percentChange');
        $percentMethod->setAccessible(true);
        expect($percentMethod->invoke($service, 0, 100))->toBeNull();
    });
});

describe('ChannelProviderRegistry batch-6 branches', function () {
    it('resolve throws when neither driverKey nor channel is provided', function () {
        expect(fn () => app(ChannelProviderRegistry::class)->resolve())
            ->toThrow(InvalidArgumentException::class, 'Either driverKey or channel must be provided.');
    });

    it('defaultDriverKeyForChannel throws for unknown channels during self-test', function () {
        expect(fn () => app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'nonexistent-channel',
        )))->toThrow(InvalidArgumentException::class, 'No channel driver registered for channel');
    });
});

describe('CommunicationLogStatusRegistry batch-6 branches', function () {
    it('reports terminal status and rejects unknown transition endpoints', function () {
        $registry = app(CommunicationLogStatusRegistry::class);

        expect($registry->isTerminal('failed'))->toBeTrue()
            ->and($registry->isTerminal('queued'))->toBeFalse()
            ->and($registry->canTransitionTo('queued', 'unknown-status'))->toBeFalse()
            ->and($registry->canTransitionTo('unknown', 'queued'))->toBeFalse();
    });
});

describe('EventNotificationMapRegistry batch-6 branches', function () {
    it('has returns false for empty mappings and notificationTypesForEntry returns empty for malformed entries', function () {
        $registry = new EventNotificationMapRegistry;

        expect($registry->has('never.registered.action'))->toBeFalse();

        $registry->register('plugin.only', [['when' => []]]);

        expect($registry->has('plugin.only'))->toBeTrue()
            ->and($registry->notificationTypesForEntry(['when' => []]))->toBe([]);
    });
});

describe('DiscountRuleEvaluator batch-6 branches', function () {
    it('skips non-priceable items and maps service transaction types', function () {
        $category = DiscountCategory::factory()->create();
        $rules = collect([
            DiscountRule::factory()->for($category)->blanket()->create(),
        ]);

        $opportunity = Opportunity::factory()->create();
        $header = OpportunityItem::factory()->for($opportunity)->create([
            'item_type' => OpportunityItemType::Header,
            'itemable_id' => null,
            'itemable_type' => null,
        ]);

        $product = Product::factory()->rental()->create();
        $serviceLine = OpportunityItem::factory()->for($opportunity)->create([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::Service,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $evaluator = app(DiscountRuleEvaluator::class);
        $at = now();

        expect($evaluator->evaluateForItem($header, $opportunity, $rules, $at))->toBeNull()
            ->and($evaluator->evaluateForItem($serviceLine->load('item'), $opportunity, $rules, $at))->toBeString();
    });
});

describe('OpportunitySurchargeApplicator batch-6 branches', function () {
    it('clears auto snapshots when no surcharge groups are active', function () {
        $opportunity = Opportunity::factory()->create(['surcharge_group_ids' => []]);
        $item = OpportunityItem::factory()->for($opportunity)->create();
        OpportunityItemSurcharge::factory()->create([
            'opportunity_item_id' => $item->id,
            'is_manual_override' => false,
        ]);

        $result = app(OpportunitySurchargeApplicator::class)->applyToItem(
            $item,
            $opportunity,
            10_000,
            'GBP',
            null,
        );

        expect($result['surcharge_total'])->toBe(0)
            ->and($result['surcharges'])->toBe([])
            ->and(OpportunityItemSurcharge::query()->where('opportunity_item_id', $item->id)->count())->toBe(0);
    });
});

describe('QuantityMinorArithmetic batch-6 branches', function () {
    it('returns zero for non-positive quantities and normalizes negatives to zero', function () {
        settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
        $arithmetic = app(QuantityMinorArithmetic::class);

        expect($arithmetic->multiplyMinorByQuantity(1000, '0', 'GBP'))->toBe(0)
            ->and($arithmetic->multiplyMinorByQuantity(1000, '-1', 'GBP'))->toBe(0)
            ->and($arithmetic->normalizeQuantity('-5'))->toBe('0.00');
    });
});

describe('ManualCostApportionmentStrategy batch-6 branches', function () {
    it('exposes the manual label and rejects non-context payloads', function () {
        $strategy = app(ManualCostApportionmentStrategy::class);

        expect($strategy->name())->toBe('Manual')
            ->and(fn () => $strategy->calculate(new stdClass))
            ->toThrow(InvalidArgumentException::class, 'Expected CostApportionmentContext.');
    });
});

describe('ProportionalQuantityCostApportionmentStrategy batch-6 branches', function () {
    it('exposes the quantity label and returns empty allocations for empty weights', function () {
        $strategy = app(ProportionalQuantityCostApportionmentStrategy::class);
        $intake = VirtualStockIntake::factory()->confirmed()->create();

        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [],
            opportunities: [],
            primaryOpportunityId: null,
            totalCostMinor: 0,
        );

        expect($strategy->name())->toBe('Proportional by quantity')
            ->and($strategy->calculate($context))->toBe([]);
    });
});

describe('TaxResolutionContextHolder batch-6 branches', function () {
    it('set, clear, and restore context across using()', function () {
        $holder = app(TaxResolutionContextHolder::class);
        $context = TaxResolutionContext::fromOpportunity(
            Opportunity::factory()->create()->loadMissing(['member', 'store']),
        );

        $holder->set($context);
        expect($holder->get())->toBe($context);

        $holder->clear();
        expect($holder->get())->toBeNull();

        $holder->set($context);
        $holder->using(TaxResolutionContext::fromOpportunity(
            Opportunity::factory()->create()->loadMissing(['member', 'store']),
        ), function () use ($holder, $context): void {
            expect($holder->get())->not->toBe($context);
        });
        expect($holder->get())->toBe($context);
    });
});

describe('KitCompositionGuard batch-6 branches', function () {
    it('hits recursion backstops in private tree walks', function () {
        $guard = app(KitCompositionGuard::class);

        foreach (['reaches', 'ancestorDepth', 'subtreeDepth'] as $methodName) {
            $method = new ReflectionMethod(KitCompositionGuard::class, $methodName);
            $method->setAccessible(true);

            if ($methodName === 'reaches') {
                expect($method->invoke($guard, 1, 2, 51))->toBeFalse();
            } else {
                expect($method->invoke($guard, 1, 51))->toBe(51);
            }
        }
    });

    it('rejects self-referential kit composition via reaches()', function () {
        $parent = Product::factory()->kit()->create();
        $child = Product::factory()->bulk()->create();

        SerialisedComponent::factory()->pool()->quantity(1)->create([
            'product_id' => $parent->id,
            'component_product_id' => $child->id,
        ]);

        expect(fn () => app(KitCompositionGuard::class)->assertCanAdd($parent->id, $parent->id))
            ->toThrow(ValidationException::class);
    });
});

describe('VirtualStockStatusRegistry batch-6 branches', function () {
    it('guards unknown statuses and invalid transitions', function () {
        $registry = app(VirtualStockStatusRegistry::class);

        expect($registry->canTransitionTo('draft', 'not-a-status'))->toBeFalse()
            ->and(fn () => $registry->assertKnown('missing-status'))
            ->toThrow(InvalidArgumentException::class, 'Unknown virtual stock intake status')
            ->and(fn () => $registry->assertCanTransitionTo('draft', 'retired'))
            ->toThrow(InvalidArgumentException::class, 'cannot transition');
    });
});

describe('WebhookEventRegistry batch-6 branches', function () {
    it('registerMany bulk-registers events and has reflects registration', function () {
        $registry = new WebhookEventRegistry;
        $registry->registerMany([
            'demo.batch.event' => ['label' => 'Demo', 'group' => 'demo'],
        ]);

        expect($registry->has('demo.batch.event'))->toBeTrue()
            ->and($registry->get('demo.batch.event')['label'])->toBe('Demo');
    });
});

describe('DocumentSequenceDefaults batch-6 branches', function () {
    it('falls back to invoice prefix for unknown document types and strips non-digits from opportunity numbers', function () {
        settings()->set('invoices.fallback_number_prefix', 'TEST-');

        expect(DocumentSequenceDefaults::prefixFor('unknown_type'))->toBe('TEST-');

        $method = new ReflectionMethod(DocumentSequenceDefaults::class, 'opportunityNumericPortion');
        $method->setAccessible(true);
        expect($method->invoke(null, 'OPP-ABC'))->toBe(0);
    });
});

describe('CommunicationPreferences batch-6 branches', function () {
    it('returns empty opt-outs for null preferences and masks invalid emails', function () {
        $service = app(CommunicationPreferences::class);
        $member = Member::factory()->create(['communication_preferences' => null]);

        expect($service->optedOutTypes($member))->toBe([]);

        $member->forceFill(['email' => ''])->saveQuietly();
        expect($service->maskedEmail($member))->toBeNull();

        $member->forceFill(['email' => 'not-an-email'])->saveQuietly();
        expect($service->maskedEmail($member))->toBeNull();
    });
});

describe('DiscountCriteriaPredicateRegistry batch-6 branches', function () {
    it('has reflects registration and get throws for unknown keys', function () {
        $registry = app(DiscountCriteriaPredicateRegistry::class);

        expect($registry->has('product_ids'))->toBeTrue()
            ->and(fn () => $registry->get('missing-predicate'))
            ->toThrow(InvalidArgumentException::class, 'Unknown discount criteria predicate');
    });
});

describe('RecalculationPipeline batch-6 private early returns', function () {
    it('no-ops batch upserts and previous availability reads for empty inputs', function () {
        $pipeline = app(RecalculationPipeline::class);

        foreach ([
            'batchUpsertSnapshots',
            'batchUpsertDailySummaries',
            'rollUpDailySummaries',
            'previousAvailability',
        ] as $methodName) {
            $method = new ReflectionMethod(RecalculationPipeline::class, $methodName);
            $method->setAccessible(true);

            if ($methodName === 'rollUpDailySummaries') {
                $method->invoke($pipeline, 1, 1, [], 'UTC', now());
            } elseif ($methodName === 'previousAvailability') {
                expect($method->invoke($pipeline, 1, 1, collect()))->toBe([]);
            } else {
                $method->invoke($pipeline, []);
            }
        }

        expect(true)->toBeTrue();
    });
});

describe('OpportunityNumberGenerator batch-6 branches', function () {
    it('ensureSequence provisions a store sequence and nextOrCreate survives missing sequences', function () {
        Verbs::shouldReceive('isReplaying')->andReturn(false);

        $store = Store::factory()->create();
        $generator = app(OpportunityNumberGenerator::class);

        DocumentSequence::query()->where('store_id', $store->id)
            ->where('document_type', OpportunityNumberGenerator::DOCUMENT_TYPE)
            ->delete();

        $sequence = $generator->ensureSequence($store->id);
        expect($sequence->document_type)->toBe(OpportunityNumberGenerator::DOCUMENT_TYPE);

        $number = $generator->nextOrCreate($store->id);
        expect($number)->not->toBeEmpty();
    });
});

describe('ProportionalQuantityDurationCostApportionmentStrategy batch-6 branches', function () {
    it('exposes the duration label and zeroes opportunities without rental windows', function () {
        $strategy = app(ProportionalQuantityDurationCostApportionmentStrategy::class);
        $intake = VirtualStockIntake::factory()->confirmed()->create([
            'available_from' => now()->subDays(2),
            'available_until' => now()->addDays(2),
        ]);
        $opportunity = Opportunity::factory()->create();

        $context = new CostApportionmentContext(
            intake: $intake,
            quantitiesByOpportunity: [$opportunity->id => 2],
            opportunities: [$opportunity->id => $opportunity],
            primaryOpportunityId: $opportunity->id,
            totalCostMinor: 1000,
        );

        expect($strategy->name())->toBe('Proportional by quantity × duration')
            ->and($strategy->calculate($context)[$opportunity->id])->toBe(0);
    });
});

describe('IdempotencyKeyService batch-6 branches', function () {
    it('covers resolveExistingClaim in-flight, normalizePayload nesting, decodeResponseBody guards, shouldPersist, and releaseClaim', function () {
        $service = app(IdempotencyKeyService::class);

        expect($service->resolveExistingClaim('missing', 1, 'fp'))
            ->toBe(['outcome' => 'in_flight'])
            ->and($service->shouldPersist(500))->toBeFalse()
            ->and($service->shouldPersist(422))->toBeTrue()
            ->and($service->decodeResponseBody(new Response('')))->toBeNull()
            ->and($service->decodeResponseBody(new Response('not-json')))->toBeNull();

        $normalize = new ReflectionMethod(IdempotencyKeyService::class, 'normalizePayload');
        $normalize->setAccessible(true);
        expect($normalize->invoke($service, ['b' => 1, 'a' => ['z' => 2, 'y' => 1]]))
            ->toBe(['a' => ['y' => 1, 'z' => 2], 'b' => 1]);

        $user = User::factory()->create();
        $token = $user->createToken('idempotency-test');
        $record = ApiIdempotencyKey::query()->create([
            'key' => 'release-me',
            'personal_access_token_id' => $token->accessToken->id,
            'request_fingerprint' => hash('sha256', 'payload'),
            'response_status' => 200,
            'response_body' => ['ok' => true],
            'expires_at' => now()->addDay(),
        ]);
        $service->releaseClaim($record);
        expect(ApiIdempotencyKey::query()->find($record->id))->toBeNull();
    });
});

describe('DocumentNumberGenerator batch-6 branches', function () {
    it('reuses outer transactions, applies period resets, and formats date segments', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(DocumentNumberGenerator::class);

        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'include_month' => true,
            'reset_frequency' => DocumentSequenceResetFrequency::Yearly->value,
            'current_period_year' => now()->subYear()->year,
            'next_number' => 7,
            'padding' => 4,
        ]);

        $withTx = new ReflectionMethod(DocumentNumberGenerator::class, 'withAllocationTransaction');
        $withTx->setAccessible(true);
        $connection = DocumentSequence::query()->getConnection();
        $connection->beginTransaction();
        $withTx->invoke($generator, fn (): string => 'ok');
        $connection->rollBack();

        $applyReset = new ReflectionMethod(DocumentNumberGenerator::class, 'applyPeriodReset');
        $applyReset->setAccessible(true);
        $applyReset->invoke($generator, $sequence, Carbon::parse('2026-01-15T12:00:00Z'));

        $nextAfter = new ReflectionMethod(DocumentNumberGenerator::class, 'nextNumberAfterPeriodCheck');
        $nextAfter->setAccessible(true);
        expect($nextAfter->invoke($generator, $sequence, Carbon::parse('2026-01-15T12:00:00Z')))->toBe(1);

        $monthly = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'credit_note',
            'include_year' => false,
            'include_month' => true,
            'reset_frequency' => DocumentSequenceResetFrequency::Never->value,
        ]);
        $dateSegment = new ReflectionMethod(DocumentNumberGenerator::class, 'dateSegment');
        $dateSegment->setAccessible(true);
        expect($dateSegment->invoke($generator, $monthly, Carbon::parse('2026-03-01T00:00:00Z')))->toBe('03');
    });
});

describe('DocumentResolverRegistry batch-6 branches', function () {
    it('caches field definitions, loads sources, and invalidates per type or globally', function () {
        $registry = app(DocumentResolverRegistry::class);
        $opportunity = Opportunity::factory()->create();

        expect($registry->fieldDefinitions('opportunity'))->not->toBeEmpty()
            ->and($registry->load('opportunity', $opportunity->id)->getKey())->toBe($opportunity->id);

        $registry->invalidate('opportunity');
        $registry->invalidate(null);
    });
});

describe('PageDimensions batch-6 branches', function () {
    it('covers LETTER/A3 landscape sizing and dompdf named formats', function () {
        expect(PageDimensions::sizeMm(['page_size' => 'LETTER', 'page_orientation' => 'landscape']))
            ->toBe(['width' => 279.0, 'height' => 216.0])
            ->and(PageDimensions::dompdfPaper(['page_size' => 'A3', 'page_orientation' => 'portrait']))
            ->toBe(['a3', 'portrait'])
            ->and(PageDimensions::browsershotPaper(['page_size' => 'LETTER']))
            ->toMatchArray(['format' => 'Letter']);
    });
});

describe('ChannelProviderConfigService batch-6 branches', function () {
    it('schema mirrors driver config, persists email settings, and skips null provider updates', function () {
        $service = app(ChannelProviderConfigService::class);
        $driver = app(EmailDriver::class);

        expect($service->schema($driver))->not->toBeEmpty();

        settings()->setMany([
            'email.mailer' => 'smtp',
            'email.smtp_host' => 'smtp.example.com',
            'email.from_address' => 'noreply@example.com',
        ]);

        $service->persist($driver, [
            'mailer' => 'smtp',
            'smtp_host' => 'mail.example.com',
            'from_address' => 'hello@example.com',
        ]);

        expect(settings('email.smtp_host'))->toBe('mail.example.com');

        $provider = ChannelProvider::factory()->create(['config' => ['token' => 'abc']]);
        $service->persist(new class implements ChannelDriver
        {
            public function key(): string
            {
                return 'noop';
            }

            public function channel(): string
            {
                return 'noop';
            }

            public function label(): string
            {
                return 'Noop';
            }

            public function configSchema(): array
            {
                return [new ChannelConfigFieldData('token', 'Token', 'string')];
            }

            public function resolveConfig(): array
            {
                return [];
            }

            public function send(\App\Data\Notifications\ChannelSendData $payload): void {}

            public function test(TestChannelProviderData $data): \App\Data\Notifications\ChannelTestResultData
            {
                return \App\Data\Notifications\ChannelTestResultData::passed($this, 'ok');
            }
        }, ['token' => 'updated'], null);

        expect($provider->fresh()->config['token'])->toBe('abc');
    });
});

describe('KitContainerReturnCompletion batch-6 branches', function () {
    it('requires individual check-in mode with all assets returned before completing', function () {
        $store = Store::factory()->create();
        $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->kit()->create([
            'container_checkin_mode' => ContainerCheckinMode::Individual,
        ]);
        $container = Container::factory()->create([
            'product_id' => $product->id,
            'opportunity_id' => $opportunity->id,
            'status' => ContainerStatus::Dispatched->value,
        ]);
        $stockLevel = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
        $item = OpportunityItem::factory()->for($opportunity)->create();
        OpportunityItemAsset::factory()->for($item, 'item')->create([
            'stock_level_id' => $stockLevel->id,
            'status' => AssetAssignmentStatus::Returned->value,
        ]);

        $service = app(KitContainerReturnCompletion::class);

        expect($service->allKitAssetsReturned($container))->toBeFalse()
            ->and($service->maybeCompleteIndividualReturn($container))->toBeFalse();
    });
});

describe('CustomerCommunicationAttachmentResolver batch-6 branches', function () {
    it('rejects documents blocked by template attachment config and failed renders', function () {
        $template = CommunicationTemplate::factory()->create([
            'attachments_config' => ['document_template_ids' => [999999]],
        ]);
        $document = Document::factory()->create(['document_template_id' => 1]);

        expect(app(CustomerCommunicationAttachmentResolver::class)->resolve($document, $template))->toBeNull();

        $allowedTemplate = CommunicationTemplate::factory()->create([
            'attachments_config' => ['document_template_ids' => [$document->document_template_id]],
        ]);
        $document->forceFill(['rendered_data' => null])->saveQuietly();

        expect(app(CustomerCommunicationAttachmentResolver::class)->resolve($document, $allowedTemplate))->toBeNull();
    });
});

describe('EventNotificationConditionEvaluator batch-6 branches', function () {
    it('supports neq, not_in, and old-value resolution paths', function () {
        $evaluator = app(EventNotificationConditionEvaluator::class);
        $member = Member::factory()->make(['account_number' => 'ACC-1']);
        $event = new AuditableEvent(
            model: $member,
            action: 'member.updated',
            oldValues: ['account_number' => 'ACC-0'],
            newValues: ['account_number' => 'ACC-1'],
        );

        expect($evaluator->matches([
            ['source' => 'old', 'field' => 'account_number', 'operator' => 'neq', 'value' => 'ACC-1'],
        ], $event))->toBeTrue()
            ->and($evaluator->matches([
                ['source' => 'new', 'field' => 'account_number', 'operator' => 'not_in', 'value' => ['ACC-9']],
            ], $event))->toBeTrue()
            ->and($evaluator->matches([
                ['source' => 'unknown', 'field' => 'missing', 'operator' => 'eq', 'value' => 'x'],
            ], $event))->toBeFalse();
    });
});
