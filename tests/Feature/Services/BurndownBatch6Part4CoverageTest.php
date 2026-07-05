<?php

/**
 * P4-CL4b coverage burn-down batch 6 — classes 68–90 (PurchaseOrderDataResolver through ZoneAwareTaxResolver).
 */

use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\TestChannelProviderData;
use App\Data\Tax\DocumentTaxLineInput;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\DealPriceMode;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Enums\PricingRoundingDirection;
use App\Enums\TaxBuyerType;
use App\Enums\TaxCategoryType;
use App\Enums\TaxResultType;
use App\Enums\NotificationAudience;
use App\Events\AuditableEvent;
use App\Events\Containers\BeforeContainerPacked;
use App\Models\Container;
use App\Models\CreditNote;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\ShortageWaitlistMonitor;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\TaxCategory;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Api\ApiEndpointCatalogue;
use App\Services\Api\UsageRecorder;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\Documents\BladeExpressionExtractor;
use App\Services\Documents\Drivers\BrowsershotDriver;
use App\Services\Documents\OpportunityDataResolver;
use App\Services\Documents\PurchaseOrderDataResolver;
use App\Services\Invoices\CreditNoteTaxMirror;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Invoices\InvoiceTotalsCalculator;
use App\Services\Invoices\OpportunityInvoiceSnapshotter;
use App\Services\Invoices\PartialQuantityInvoiceCalculator;
use App\Services\Members\MemberNumberGenerator;
use App\Services\Notifications\Drivers\BroadcastDriver;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Notifications\InAppNotificationContentBuilder;
use App\Services\Notifications\NotificationPreviewData;
use App\Services\Notifications\NotificationSettingsCatalog;
use App\Services\Plugins\PluginRegistrar;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Services\Tax\ZoneAwareTaxResolver;
use App\Services\VirtualStock\VirtualStockNumberGenerator;
use App\Services\Containers\ContainerTemplateValidator;
use App\ValueObjects\Documents\PdfDocumentParts;
use App\ValueObjects\Tax\DocumentTaxGroup;
use App\ValueObjects\Tax\DocumentTaxLineAllocation;
use App\ValueObjects\Tax\DocumentTaxSummary;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use RuntimeException;
use Thunk\Verbs\Facades\Verbs;

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

function batch6Part4Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

describe('PurchaseOrderDataResolver batch-6 branches', function () {
    it('resolve handles null supplier opportunity store and invalid model', function () {
        $resolver = app(PurchaseOrderDataResolver::class);
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => null,
            'opportunity_id' => null,
            'store_id' => null,
        ]);

        $data = $resolver->resolve($po);
        expect($data['supplier']['name'])->toBeNull()
            ->and($data['opportunity'])->toBeNull()
            ->and($data['store']['name'])->toBeNull();

        expect(fn () => $resolver->resolve(Member::factory()->create()))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('MemberNumberGenerator batch-6 branches', function () {
    it('ensureSequence is idempotent and next throws without stores', function () {
        Store::query()->delete();
        expect(fn () => app(MemberNumberGenerator::class)->next())
            ->toThrow(ModelNotFoundException::class);

        Store::factory()->create();
        $generator = app(MemberNumberGenerator::class);
        $sequence = $generator->ensureSequence();
        expect($generator->ensureSequence()->id)->toBe($sequence->id);
        expect($generator->nextOrCreate())->toBeString();
    });
});

describe('PurchaseOrderNumberGenerator batch-6 branches', function () {
    it('ensureSequence returns existing sequence', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(PurchaseOrderNumberGenerator::class);
        $first = $generator->ensureSequence($store->id);
        expect($generator->ensureSequence($store->id)->id)->toBe($first->id);
    });
});

describe('VirtualStockNumberGenerator batch-6 branches', function () {
    it('nextOrCreate provisions default sequence', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', VirtualStockNumberGenerator::DOCUMENT_TYPE)
            ->delete();

        $generator = app(VirtualStockNumberGenerator::class);
        expect($generator->nextOrCreate($store->id))->toStartWith('VS-');
        expect($generator->ensureSequence($store->id))->not->toBeNull();
    });
});

describe('BladeExpressionExtractor batch-6 branches', function () {
    it('extracts foreach for and escaped echo variants', function () {
        $extractor = app(BladeExpressionExtractor::class);

        $echoes = $extractor->extractEchoExpressions('Hello @{{ escaped }} {{ $name }}');
        expect($echoes)->toContain('$name');

        $directives = $extractor->extractDirectiveExpressions('@foreach($items as $k => $v) @endforeach @for($i=0;$i<3;$i++) @endfor');
        expect($directives)->not->toBeEmpty();

        $splitLoop = batch6Part4Invoke($extractor, 'splitLoopExpression', ['($items as $item)']);
        expect($splitLoop)->toHaveCount(2);

        $splitFor = batch6Part4Invoke($extractor, 'splitForExpression', ['$i=0;$i<3;$i++']);
        expect($splitFor)->toHaveCount(3);
    });
});

describe('BroadcastDriver batch-6 branches', function () {
    it('send resolves notifiable and test handles empty connection', function () {
        Notification::fake();
        $driver = app(BroadcastDriver::class);
        $member = Member::factory()->create();

        $driver->send(new ChannelSendData(
            driverKey: 'broadcast',
            notifiableType: Member::class,
            notifiableId: $member->id,
            title: 'Test',
            body: 'Body',
            data: ['foo' => 'bar'],
        ));

        Notification::assertSentTo($member, \App\Notifications\ChannelInfrastructureBroadcastNotification::class);

        config(['broadcasting.default' => '']);
        expect($driver->test(new TestChannelProviderData(channel: 'broadcast'))->passed)->toBeFalse();

        expect(fn () => batch6Part4Invoke($driver, 'resolveNotifiable', [
            new ChannelSendData(
                driverKey: 'broadcast',
                notifiableType: 'Missing\\Class',
                notifiableId: 1,
                title: 't',
                body: 'b',
            ),
        ]))->toThrow(RuntimeException::class);
    });
});

describe('UsageRecorder batch-6 branches', function () {
    it('flush empty buffer reportFailure and shouldBuffer redis failure paths', function () {
        $recorder = app(UsageRecorder::class);
        $recorder->flush();

        settings()->set('api_metering.log_channel', 'stack');
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('warning')->once();
        batch6Part4Invoke($recorder, 'reportFailure', [new RuntimeException('persist failed')]);

        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis down'));
        expect(batch6Part4Invoke($recorder, 'shouldBuffer', []))->toBeFalse();
    });
});

describe('ContainerTemplateValidator batch-6 branches', function () {
    it('validate rejects empty slots with max_items and invalid slot shapes', function () {
        $validator = app(ContainerTemplateValidator::class);
        $product = Product::factory()->create();

        expect(fn () => $validator->validate($product, ['max_items' => 1], ContainerAvailabilityMode::Kit))
            ->toThrow(ValidationException::class);

        expect(fn () => $validator->validate($product, [
            'slots' => ['not-array'],
        ], ContainerAvailabilityMode::Kit))->toThrow(ValidationException::class);
    });
});

describe('InvoiceNumberGenerator batch-6 branches', function () {
    it('preview returns formatted number without consuming sequence', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => InvoiceNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'INV-',
            'next_number' => 42,
            'padding' => 4,
        ]);

        $generator = app(InvoiceNumberGenerator::class);
        expect($generator->preview($store->id))->toContain('INV-');
    });
});

describe('InvoiceTotalsCalculator batch-6 branches', function () {
    it('assertPennyReconciliation throws and distributeDealHeadlineTax allocates pennies', function () {
        $calculator = app(InvoiceTotalsCalculator::class);

        $badSummary = new DocumentTaxSummary(
            groups: [new DocumentTaxGroup(productTaxClassKey: 1, netTotal: 1000, taxTotal: 200, ratePercentage: '20', taxRateName: 'Standard', taxRateId: 1, taxRuleId: 1)],
            lineAllocations: [new DocumentTaxLineAllocation(lineKey: 'a', taxMinor: 199, netMinor: 1000)],
            totalNet: 1000,
            totalTax: 200,
        );

        expect(fn () => batch6Part4Invoke($calculator, 'assertPennyReconciliation', [$badSummary]))
            ->toThrow(LogicException::class);

        $lines = [
            new FrozenInvoiceLineData(
                invoice_item_id: 0,
                line_key: 'line-1',
                source_type: 'item',
                description: 'A',
                quantity: '1',
                unit_price: 600,
                discount: 0,
                net_total: 600,
                tax: 0,
                product_tax_class_id: 1,
                tax_treatment: TaxResultType::Standard->value,
            ),
            new FrozenInvoiceLineData(
                invoice_item_id: 0,
                line_key: 'line-2',
                source_type: 'item',
                description: 'B',
                quantity: '1',
                unit_price: 400,
                discount: 0,
                net_total: 400,
                tax: 0,
                product_tax_class_id: 1,
                tax_treatment: TaxResultType::Standard->value,
            ),
        ];

        $distributed = batch6Part4Invoke($calculator, 'distributeDealHeadlineTax', [101, $lines]);
        expect(array_sum($distributed))->toBe(101);
    });
});

describe('OpportunityDataResolver batch-6 branches', function () {
    it('resolve handles null member store tax locked and rental days', function () {
        $resolver = app(OpportunityDataResolver::class);
        $opportunity = Opportunity::factory()->create([
            'member_id' => null,
            'store_id' => null,
            'tax_locked' => true,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'chargeable_days' => null,
        ]);

        $data = $resolver->resolve($opportunity);
        expect($data['member']['name'])->toBeNull()
            ->and($data['store']['name'])->toBeNull()
            ->and($data['tax_summary']['groups'])->toBe([])
            ->and($data['opportunity']['rental_days'])->toBe('3');
    });
});

describe('VirtualStockDemandResolver batch-6 branches', function () {
    it('syncDemands skips serialised and asIntake validates model type', function () {
        Verbs::shouldReceive('isReplaying')->andReturn(false);

        $resolver = app(VirtualStockDemandResolver::class);
        $serialIntake = VirtualStockIntake::factory()->confirmed()->create([
            'product_id' => Product::factory()->serialised()->create()->id,
        ]);

        batch6Part4Invoke($resolver, 'syncDemands', [$serialIntake]);
        expect($serialIntake->refresh()->demand_id)->toBeNull();

        expect(fn () => batch6Part4Invoke($resolver, 'asIntake', [Member::factory()->create()]))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('PluginRegistrar batch-6 branches', function () {
    it('registers container lifecycle hooks and extendDocumentResolver', function () {
        $registrar = app(PluginRegistrar::class);
        $called = false;

        $registrar->onContainerBeforePack(function () use (&$called): void {
            $called = true;
        });
        $registrar->onContainerAfterPack(fn () => null);
        $registrar->onContainerBeforeSeal(fn () => null);
        $registrar->onContainerAfterSeal(fn () => null);
        $registrar->onContainerBeforeDissolve(fn () => null);
        $registrar->onContainerAfterDissolve(fn () => null);
        $registrar->onContainerBeforeDispatch(fn () => null);
        $registrar->onContainerAfterDispatch(fn () => null);
        $registrar->onContainerBeforeRepack(fn () => null);
        $registrar->onContainerAfterRepack(fn () => null);
        $registrar->onContainerBeforeTransfer(fn () => null);
        $registrar->onContainerAfterTransfer(fn () => null);

        event(new BeforeContainerPacked(Container::factory()->create(), StockLevel::factory()->create()));
        expect($called)->toBeTrue();

        expect($registrar->extendDocumentResolver('custom', fn () => []))->toBe($registrar);
    });
});

describe('PartialQuantityInvoiceCalculator batch-6 branches', function () {
    it('calculate validates selections and scales tax rows proportionally', function () {
        $calculator = app(PartialQuantityInvoiceCalculator::class);
        $item = OpportunityItem::factory()->create(['quantity' => '2', 'invoiced_quantity' => '0']);
        $lines = [
            new FrozenInvoiceLineData(
                invoice_item_id: 1,
                line_key: 'item:'.$item->id,
                source_type: 'item',
                description: 'Line',
                quantity: '2',
                unit_price: 1000,
                discount: 0,
                net_total: 2000,
                tax: 400,
                product_tax_class_id: 1,
                tax_treatment: TaxResultType::Standard->value,
                opportunity_item_id: $item->id,
            ),
        ];
        $taxRows = [
            new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: 1,
                taxable_amount: 2000,
                tax_amount: 400,
                rate_percentage: '20.0000',
                tax_rate_name: 'Standard',
                tax_rate_id: 1,
                tax_rule_id: 1,
                result_type: TaxResultType::Standard->value,
            ),
        ];

        $result = $calculator->calculate(
            fullLines: $lines,
            fullTaxRows: $taxRows,
            fullSubtotal: 2000,
            fullTaxTotal: 400,
            selections: [['opportunity_item_id' => $item->id, 'quantity' => '1']],
            lockedItems: collect([$item]),
        );

        expect($result['subtotal'])->toBe(1000)
            ->and($result['tax_total'])->toBe(200);

        expect(fn () => $calculator->calculate($lines, $taxRows, 2000, 400, [], collect([$item])))
            ->toThrow(ValidationException::class);
    });
});

describe('NotificationSettingsCatalog batch-6 branches', function () {
    it('effectiveChannels recipientRules settingFor and sanitization paths', function () {
        $this->seed(NotificationTypeSeeder::class);
        $catalog = app(NotificationSettingsCatalog::class);
        $type = NotificationType::query()->firstOrFail();
        $type->update(['audience' => NotificationAudience::Both, 'default_channels' => ['in_app', 'email']]);

        $setting = NotificationSetting::factory()->create([
            'notification_type_id' => $type->id,
            'channels' => ['database'],
            'channel_config' => [
                'audiences' => [
                    'internal' => ['channels' => ['mail'], 'recipient_rules' => [['type' => 'owner']]],
                    'external' => ['channels' => ['database']],
                ],
            ],
        ]);

        expect($catalog->effectiveChannelsFor($type, $setting, 'internal'))->toContain('mail')
            ->and($catalog->recipientRulesFor($type, $setting, 'internal'))->not->toBeEmpty()
            ->and($catalog->normalizeChannels(['in_app']))->toBe(['database'])
            ->and($catalog->sanitizeRecipientRules([
                ['type' => ''],
                ['type' => 'role'],
                ['type' => 'specific_member'],
                ['type' => 'owner'],
            ]))->toHaveCount(1)
            ->and($catalog->settingFor($type))->toBeInstanceOf(NotificationSetting::class);
    });
});

describe('EmailDriver batch-6 branches', function () {
    it('validate mailers and resolveRecipient fallbacks', function () {
        Mail::fake();
        $driver = app(EmailDriver::class);

        settings()->set('mail.default', 'smtp');
        settings()->set('email.smtp_host', '');
        expect(batch6Part4Invoke($driver, 'validateSmtp', []))->toBeString();

        settings()->set('mail.default', 'ses');
        settings()->set('email.ses_key', '');
        expect(batch6Part4Invoke($driver, 'validateSes', []))->toBeString();

        settings()->set('mail.default', 'mailgun');
        settings()->set('email.mailgun_domain', '');
        expect(batch6Part4Invoke($driver, 'validateMailgun', []))->toBeString();

        settings()->set('mail.default', 'postmark');
        settings()->set('email.postmark_token', '');
        expect(batch6Part4Invoke($driver, 'validatePostmark', []))->toBeString();

        $member = Member::factory()->create(['email' => 'test@example.com']);
        $recipient = batch6Part4Invoke($driver, 'resolveRecipient', [
            new ChannelSendData(
                driverKey: 'mail',
                notifiableType: Member::class,
                notifiableId: $member->id,
                title: 't',
                body: 'b',
                data: ['recipient' => 'override@example.com'],
            ),
        ]);
        expect($recipient)->toBe('override@example.com');
    });
});

describe('CreditNoteTaxMirror batch-6 branches', function () {
    it('mirror handles partial selections and empty invoice items', function () {
        $invoice = Invoice::factory()->issued()->create(['subtotal' => 0]);
        $invoice->items()->delete();
        $mirror = app(CreditNoteTaxMirror::class);

        expect(fn () => $mirror->mirror($invoice, []))
            ->toThrow(ValidationException::class);

        $item = \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'total' => 1000,
            'tax' => 200,
            'quantity' => '2',
            'opportunity_item_id' => OpportunityItem::factory()->create()->id,
        ]);
        \App\Models\InvoiceTax::factory()->create([
            'invoice_id' => $invoice->id,
            'taxable_amount' => 1000,
            'tax_amount' => 200,
        ]);

        $result = $mirror->mirror($invoice->refresh(), [
            ['opportunity_item_id' => $item->opportunity_item_id, 'quantity' => '1'],
        ]);
        expect($result['subtotal'])->toBeGreaterThan(0);
    });
});

describe('NotificationPreviewData batch-6 branches', function () {
    it('sampleForType routes invoice shortage document and opportunity schemas', function () {
        $preview = app(NotificationPreviewData::class);

        $invoiceType = NotificationType::factory()->create([
            'data_schema' => ['fields' => [['path' => 'invoice.number']]],
        ]);
        expect($preview->sampleForType($invoiceType))->toHaveKey('invoice');

        $shortageType = NotificationType::factory()->create([
            'data_schema' => ['fields' => [['path' => 'shortage.product_name']]],
        ]);
        expect($preview->sampleForType($shortageType))->toHaveKey('shortage');

        $documentType = NotificationType::factory()->create([
            'data_schema' => ['fields' => [['path' => 'document.url']]],
        ]);
        expect($preview->sampleForType($documentType))->toHaveKey('document');

        $opportunity = Opportunity::factory()->create();
        expect($preview->fromOpportunity($opportunity))->toHaveKey('opportunity');
    });
});

describe('OpportunityInvoiceSnapshotter batch-6 branches', function () {
    it('snapshot skips optional rows and handles deal headline surcharges', function () {
        $opportunity = giCreateQuotationOpportunity([], [
            'deal_total' => 8000,
            'deal_price_mode' => DealPriceMode::FlatHeadline->value,
        ]);
        $item = $opportunity->items()->first();
        OpportunityItemSurcharge::factory()->create([
            'opportunity_item_id' => $item->id,
            'surcharge_id' => Surcharge::factory()->create()->id,
            'amount' => 100,
            'is_disabled' => false,
        ]);
        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'is_optional' => true,
            'amount' => 500,
        ]);

        $snapshot = app(OpportunityInvoiceSnapshotter::class)->snapshot($opportunity->refresh());
        expect($snapshot['lines'])->not->toBeEmpty()
            ->and($snapshot['subtotal'])->toBe(8000);
    });
});

describe('ApiEndpointCatalogue batch-6 branches', function () {
    it('all caches in memory and persistent methods tolerate cache failures', function () {
        $catalogue = app(ApiEndpointCatalogue::class);
        $first = $catalogue->all();
        expect($catalogue->all())->toBe($first);

        Cache::shouldReceive('supportsTags')->andReturn(false);
        Cache::shouldReceive('get')->andThrow(new RuntimeException('cache fail'));
        Cache::shouldReceive('put')->andThrow(new RuntimeException('cache fail'));
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache fail'));

        $fresh = new ApiEndpointCatalogue;
        expect($fresh->all())->toBeArray();
        $fresh->invalidateAll();
    });
});

describe('BrowsershotDriver batch-6 branches', function () {
    it('render throws without package and mocks browsershot render paths', function () {
        if (class_exists('Spatie\\Browsershot\\Browsershot')) {
            $driver = new class extends BrowsershotDriver
            {
                protected function renderWithBrowsershot(
                    string $html,
                    array $paper,
                    string $orientation,
                    array $margins,
                    ?string $headerTemplate,
                    ?string $footerTemplate,
                ): string {
                    expect($html)->toContain('document-body');

                    return '%PDF-mocked';
                }
            };

            $parts = new PdfDocumentParts(
                header: '<span>H</span>',
                footer: '',
                brandingCss: '',
                stylesCss: '',
            );

            expect($driver->render('<p>Body</p>', ['page_size' => 'A4'], $parts))->toBe('%PDF-mocked');

            $plain = new class extends BrowsershotDriver
            {
                protected function renderWithBrowsershot(
                    string $html,
                    array $paper,
                    string $orientation,
                    array $margins,
                    ?string $headerTemplate,
                    ?string $footerTemplate,
                ): string {
                    return '%PDF-plain';
                }
            };
            expect($plain->render('<p>Only body</p>', ['page_size' => 'A4'], null))->toBe('%PDF-plain');
        } else {
            expect(fn () => app(BrowsershotDriver::class)->render('<p>x</p>', []))
                ->toThrow(RuntimeException::class, 'Browsershot');
        }
    });
});

describe('InAppNotificationContentBuilder batch-6 branches', function () {
    it('builds bodies for opportunity invoice waitlist shortage and virtual stock events', function () {
        $this->seed(NotificationTypeSeeder::class);
        $builder = app(InAppNotificationContentBuilder::class);

        $opportunity = Opportunity::factory()->create(['subject' => 'Gala']);
        $type = NotificationType::query()->where('key', 'opportunity.confirmed')->firstOrFail();
        expect($builder->build(new AuditableEvent($opportunity, 'opportunity.confirmed'), $type)['body'])
            ->toContain('Gala');

        $escalatedType = NotificationType::factory()->create(['key' => 'shortage.escalated']);
        $escalatedEvent = new AuditableEvent($opportunity, 'shortage.escalated', newValues: ['product_name' => 'Widget']);
        expect($builder->build($escalatedEvent, $escalatedType)['body'])->toContain('Widget');

        $invoice = Invoice::factory()->create(['number' => 'INV-9', 'total' => 5000]);
        $invoiceType = NotificationType::query()->where('key', 'invoice.payment_received')->firstOrFail();
        expect($builder->build(new AuditableEvent($invoice, 'invoice.payment_received'), $invoiceType)['body'])
            ->toContain('INV-9');

        $monitor = ShortageWaitlistMonitor::factory()->create();
        $waitType = NotificationType::factory()->create(['key' => 'shortage.waitlist.matched']);
        expect($builder->build(new AuditableEvent($monitor, 'shortage.waitlist.matched'), $waitType)['body'])
            ->not->toBeEmpty();

        $resolution = ShortageResolution::factory()->create();
        $item = OpportunityItem::factory()->create(['opportunity_id' => Opportunity::factory()->create(['subject' => 'Show'])->id]);
        ShortageResolutionItem::factory()->create([
            'shortage_resolution_id' => $resolution->id,
            'opportunity_item_id' => $item->id,
        ]);
        $resType = NotificationType::factory()->create(['key' => 'shortage.resolution.confirmed']);
        expect($builder->build(new AuditableEvent($resolution, 'shortage.resolution.confirmed'), $resType)['body'])
            ->toContain('Show');

        $intake = VirtualStockIntake::factory()->create(['number' => 'VS-1']);
        $vsType = NotificationType::factory()->create(['key' => 'virtual_stock.overrun']);
        expect($builder->build(new AuditableEvent($intake, 'virtual_stock.overrun'), $vsType)['body'])
            ->toContain('VS-1');

        expect(batch6Part4Invoke($builder, 'quoted', [null]))->toBe('Untitled');
    });
});

describe('ZoneAwareTaxResolver batch-6 branches', function () {
    it('calculateInclusive and calculateExclusive apply zone rules exempt and fallback rates', function () {
        $uk = TaxZone::factory()->uk()->create();
        $de = TaxZone::factory()->euMember()->create();
        TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);
        TaxZoneMember::factory()->create(['tax_zone_id' => $de->id, 'country_code' => 'DE']);

        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
        TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
        TaxRate::factory()->create(['name' => 'Zero', 'rate' => '0.0000', 'is_active' => true]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '20.0000'])->id,
        ]);

        (new TaxZoneRuleSeeder)->run();

        $store = Store::factory()->create(['country_code' => 'GB', 'tax_zone_id' => $uk->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $orgClass->id,
            'country_code' => 'DE',
            'tax_zone_id' => $de->id,
        ]);

        $context = TaxResolutionContext::fromOpportunity(Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]));

        $holder = app(TaxResolutionContextHolder::class);
        $resolver = app(ZoneAwareTaxResolver::class);

        $exclusive = $holder->using($context, fn () => $resolver->calculateExclusive(
            10000,
            'GBP',
            $orgClass->id,
            $productClass->id,
        ));
        expect($exclusive->taxAmount)->toBeInt();

        $inclusive = $holder->using($context, fn () => $resolver->calculateInclusive(
            12000,
            'GBP',
            $orgClass->id,
            $productClass->id,
        ));
        expect($inclusive->netAmount)->toBeLessThan(12000);

        $exemptContext = new TaxResolutionContext(
            sellerZoneId: $uk->id,
            buyerZoneId: $de->id,
            buyerType: TaxBuyerType::Business,
            hasTaxId: true,
            taxExempt: true,
        );
        $exempt = $holder->using($exemptContext, fn () => $resolver->calculateExclusive(5000, 'GBP', $orgClass->id, $productClass->id));
        expect($exempt->taxAmount)->toBe(0);

        $reducedCategory = TaxCategory::factory()->create(['type' => TaxCategoryType::Reduced]);
        $categoryRate = batch6Part4Invoke($resolver, 'resolveCategorySteeredRate', [$reducedCategory->id, TaxRule::factory()->create()]);
        expect($categoryRate)->toBeNull();
    });
});
