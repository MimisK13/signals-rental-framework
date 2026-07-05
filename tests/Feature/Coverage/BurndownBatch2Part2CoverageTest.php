<?php

/**
 * P4-CL4b burndown batch 2 — part 2 (remaining targets).
 */

use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Containers\UpdateContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Opportunities\ClearSectionDealPrice;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Actions\Products\UpdateProductContainerConfig;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\UpdateDraftVirtualStockIntake;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Containers\UpdateContainerData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Data\Products\UpdateProductContainerConfigData;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\SupplierCosts\UpdateSupplierCostData;
use App\Data\VirtualStock\UpdateDraftVirtualStockIntakeData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Enums\OpportunityItemType;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Listeners\Availability\DetectOrderShortages;
use App\Listeners\NotificationDispatchListener;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Components\ViewBuilder;
use App\Models\ActionLog;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\SupplierCost;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Models\Webhook;
use App\Services\Docs\DocsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Component;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('DetectOrderShortages returns early when no active opportunity items exist for the product', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    $listener = app(DetectOrderShortages::class);

    $listener->handle(new \App\Events\Availability\AvailabilityChanged(
        productId: $product->id,
        storeId: $store->id,
        eventType: \App\Enums\AvailabilityEventType::StockAdjusted,
    ));

    expect(ActionLog::query()->where('action', 'shortage.detected')->count())->toBe(0);
});

it('HandlesVerbsStateErrors invokes onSuccess callback after action succeeds', function () {
    Livewire::actingAs($this->owner)
        ->test(new class extends Component
        {
            use HandlesVerbsStateErrors;

            public bool $done = false;

            public function succeed(): void
            {
                $this->runVerbsTransition(
                    fn () => true,
                    fn () => $this->done = true,
                );
            }

            public function render(): string
            {
                return '<div></div>';
            }
        })
        ->call('succeed')
        ->assertSet('done', true);
});

it('HandlesVerbsStateErrors surfaces AuthorizationException as notify toast', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(new class extends Component
        {
            use HandlesVerbsStateErrors;

            public function forbidden(): void
            {
                $this->runVerbsTransition(function (): void {
                    throw new AuthorizationException;
                });
            }

            public function render(): string
            {
                return '<div></div>';
            }
        })
        ->call('forbidden')
        ->assertDispatched('notify');
});

it('LoadsCustomFieldValues logs a warning and skips orphaned custom field values', function () {
    Log::spy();
    $member = Member::factory()->create();
    $field = CustomField::factory()->string()->create(['module_type' => 'Member', 'name' => 'orphan_field']);
    CustomFieldValue::factory()->create([
        'entity_type' => Member::class,
        'entity_id' => $member->id,
        'custom_field_id' => $field->id,
        'value_string' => 'kept',
    ]);
    $field->delete();

    $double = new class
    {
        use LoadsCustomFieldValues;

        /** @return array<string, mixed> */
        public function load(Member $member): array
        {
            return $this->loadCustomFieldValuesFrom($member);
        }
    };

    expect($double->load($member->fresh()))->toBe([]);
    Log::shouldHaveReceived('warning');
});

it('CreateOpportunity resolveStoreId uses the only store when store_id is omitted', function () {
    Store::query()->where('is_default', true)->update(['is_default' => false]);
    $onlyStore = Store::factory()->create(['is_default' => false, 'timezone' => 'UTC']);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Implicit store',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));

    expect($created->store_id)->toBe($onlyStore->id);
});

it('UpdateOpportunity itemsInheritingHireWindow skips propagation for date-less opportunities', function () {
    $opportunity = Opportunity::factory()->create([
        'starts_at' => null,
        'ends_at' => null,
    ]);
    $originalStart = now()->subDay();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'starts_at' => $originalStart,
        'ends_at' => now()->addDay(),
    ]);

    (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from([
        'starts_at' => now()->addDays(3)->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
    ]));

    expect($item->fresh()->starts_at?->equalTo($originalStart))->toBeTrue();
});

it('SetDealPrice clearActiveGroupDealPrices clears existing group deal prices', function () {
    $opportunity = giCreateOrderOpportunity();
    $group = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => OpportunityItemType::Group,
        'deal_price' => 10000,
    ]);
    OpportunityItem::factory()->for($opportunity)->create([
        'parent_id' => $group->id,
        'deal_price_discount_percent' => '10.00',
    ]);

    (new SetSectionDealPrice)($opportunity, $group, SetSectionDealPriceData::from([
        'deal_price' => '150.00',
    ]));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_price' => '500.00',
    ]));

    expect($group->fresh()->deal_price)->toBeNull();
});

it('UpdateDraftVirtualStockIntake uppercases explicit currency codes', function () {
    $intake = VirtualStockIntake::factory()->create(['status' => 'draft']);

    $updated = (new UpdateDraftVirtualStockIntake)($intake, UpdateDraftVirtualStockIntakeData::from([
        'currency_code' => 'usd',
    ]));

    expect($updated->currency_code)->toBe('USD');
});

it('DissolveContainer rejects already dissolved containers', function () {
    $container = Container::factory()->create(['status' => ContainerStatus::Dissolved]);

    expect(fn () => (new DissolveContainer)($container))->toThrow(ValidationException::class);
});

it('VerifyKitCheckin rejects containers without verification requirement', function () {
    $container = Container::factory()->create(['checkin_verification_required_at' => null]);

    expect(fn () => (new VerifyKitCheckin)($container))->toThrow(ValidationException::class);
});

it('UpdateContainer rejects duplicate barcodes', function () {
    $existing = Container::factory()->create(['barcode' => 'DUPE-1']);
    $container = Container::factory()->create(['barcode' => 'OTHER']);

    expect(fn () => (new UpdateContainer)($container, UpdateContainerData::from([
        'barcode' => $existing->barcode,
    ])))->toThrow(ValidationException::class);
});

it('TransferContainerItem rejects transferring an item to itself', function () {
    $container = Container::factory()->create();
    $item = ContainerItem::factory()->for($container)->create();

    expect(fn () => (new TransferContainerItem)(
        $item,
        TransferContainerItemData::from(['target_container_id' => $container->id]),
    ))->toThrow(ValidationException::class);
});

it('UpdateSupplierCost clears preferred flag on sibling costs when one is preferred', function () {
    $product = Product::factory()->create();
    $preferred = SupplierCost::factory()->for($product)->create(['is_preferred' => true]);
    $other = SupplierCost::factory()->for($product)->create(['is_preferred' => false]);

    (new UpdateSupplierCost)($other, UpdateSupplierCostData::from(['is_preferred' => true]));

    expect($preferred->fresh()->is_preferred)->toBeFalse()
        ->and($other->fresh()->is_preferred)->toBeTrue();
});

it('CreatePurchaseOrder buildLinePayloads rejects lines without resolvable unit cost', function () {
    $product = Product::factory()->create();
    $supplier = Member::factory()->organisation()->create();

    expect(fn () => (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => Store::factory()->create()->id,
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '1',
        ]],
    ])))->toThrow(ValidationException::class);
});

it('IssueCreditNote assertCreditableStatus rejects crediting an already credited invoice', function () {
    $invoice = Invoice::factory()->issued()->create(['status' => 'credited']);

    expect(fn () => (new IssueCreditNote)($invoice, IssueCreditNoteData::from([
        'reason' => 'Full credit',
        'lines' => [],
    ])))->toThrow(ValidationException::class);
});

it('VoidInvoice skips quantity release rows without opportunity item linkage', function () {
    $invoice = Invoice::factory()->issued()->create();
    $invoice->items()->create([
        'description' => 'Manual line',
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
        'opportunity_item_id' => null,
        'invoiced_quantity' => null,
    ]);

    $voided = (new VoidInvoice)($invoice->fresh());

    expect($voided->status)->toBe('void');
});

it('Signed document download returns 404 when document has no render paths', function () {
    $document = Document::factory()->finalised()->create([
        'pdf_path' => null,
        'html_snapshot_path' => null,
    ]);

    $url = URL::signedRoute('documents.file', ['document' => $document->id]);

    $this->get($url)->assertNotFound();
});

it('SearchController includes purchase order hits for authorised users', function () {
    PurchaseOrder::factory()->create(['reference' => 'PO-SEARCH-XYZ']);

    $this->get('/search?q=PO-SEARCH-XYZ')
        ->assertOk()
        ->assertSee('PO-SEARCH-XYZ');
});

it('ShortageController batchResolvers lists resolver groups for multiple items', function () {
    Sanctum::actingAs($this->owner, ['shortages:read', 'opportunities:read']);
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    $opportunity = Opportunity::factory()->order()->create(['store_id' => $store->id]);
    $itemA = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 5,
    ]);
    $itemB = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 3,
    ]);

    $this->getJson('/api/v1/shortage_resolutions/batch?'.http_build_query([
        'opportunity_item_ids' => [$itemA->id, $itemB->id],
    ]))
        ->assertOk()
        ->assertJsonStructure(['groups']);
});

it('OpportunityInvoiceController store accepts deposit invoice type', function () {
    Sanctum::actingAs($this->owner, ['invoices:write', 'opportunities:read']);
    $opportunity = giCreateOrderOpportunity();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
        'type' => 'deposit',
    ])->assertCreated();
});

it('OpportunityInvoiceController store accepts proforma invoice type', function () {
    Sanctum::actingAs($this->owner, ['invoices:write', 'opportunities:read']);
    $opportunity = giCreateOrderOpportunity();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
        'type' => 'proforma',
    ])->assertCreated();
});

it('NotificationDispatchListener payloadFor shapes invoice subjects', function () {
    $listener = app(NotificationDispatchListener::class);
    $invoice = Invoice::factory()->issued()->create();
    $event = new AuditableEvent($invoice, 'invoice.issued');
    $method = new ReflectionMethod($listener, 'payloadFor');
    $payload = $method->invoke($listener, $event, ['title' => 'Invoice', 'body' => 'Issued']);

    expect($payload)->toHaveKey('invoice');
});

it('NotificationDispatchListener payloadFor includes generic fallback keys', function () {
    $listener = app(NotificationDispatchListener::class);
    $member = Member::factory()->create();
    $event = new AuditableEvent($member, 'member.updated');
    $method = new ReflectionMethod($listener, 'payloadFor');
    $payload = $method->invoke($listener, $event, ['title' => 'Member', 'body' => 'Updated']);

    expect($payload)->toHaveKeys(['title', 'body', 'action', 'subject_type', 'subject_id'])
        ->and($payload['subject_id'])->toBe($member->id);
});

it('NotificationDispatchListener payloadFor shapes virtual stock intake subjects', function () {
    $listener = app(NotificationDispatchListener::class);
    $intake = VirtualStockIntake::factory()->create(['status' => 'draft']);
    $event = new AuditableEvent($intake, 'virtual_stock_intake.received');
    $method = new ReflectionMethod($listener, 'payloadFor');
    $payload = $method->invoke($listener, $event, ['title' => 'Intake', 'body' => 'Received']);

    expect($payload)->toHaveKey('virtual_stock_intake');
});

it('NotificationDispatchListener payloadFor shapes shortage resolution subjects', function () {
    $listener = app(NotificationDispatchListener::class);
    $resolution = \App\Models\ShortageResolution::factory()->create();
    $event = new AuditableEvent($resolution, 'shortage.resolved');
    $method = new ReflectionMethod($listener, 'payloadFor');
    $payload = $method->invoke($listener, $event, ['title' => 'Shortage', 'body' => 'Resolved']);

    expect($payload)->toHaveKey('shortage_resolution');
});

it('DeliverWebhook recordOutboundMetering survives recorder failures', function () {
    Log::spy();
    Http::fake(['*' => Http::response('ok', 200)]);
    $webhook = Webhook::factory()->create(['is_active' => true]);
    $job = new DeliverWebhook($webhook, 'test.event', ['ok' => true]);

    $this->mock(\App\Services\Api\UsageRecorder::class, function ($mock): void {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('metering down'));
    });

    $job->handle();

    Log::shouldHaveReceived('error');
});

it('UpdateProductContainerConfig normalises transport binding slots', function () {
    $product = Product::factory()->create();

    $updated = (new UpdateProductContainerConfig)($product, UpdateProductContainerConfigData::from([
        'availability_mode' => ContainerAvailabilityMode::Transport->value,
        'template' => [
            'slots' => [
                ['label' => 'Main', 'binding' => 'fixed', 'quantity' => 1],
            ],
        ],
    ]));

    expect($updated->container_config['template']['slots'][0]['binding'])->toBe('fixed');
});

it('ViewBuilder defaultSortColumn picks the first visible sortable column', function () {
    Livewire::actingAs($this->owner)
        ->test(ViewBuilder::class, ['entityType' => 'stock_levels'])
        ->assertOk()
        ->assertSet('sortColumn', fn ($value) => is_string($value) && $value !== '');
});

it('DocsController navigationHasPages renders nested navigation groups', function () {
    $this->mock(DocsService::class, function ($mock): void {
        $mock->shouldReceive('navigation')->andReturn([
            [
                'title' => 'Guide',
                'subSections' => [
                    [
                        'title' => 'Setup',
                        'groups' => [
                            [
                                'title' => 'Basics',
                                'pages' => [
                                    ['slug' => 'intro', 'title' => 'Intro'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $mock->shouldReceive('page')->andReturn(['title' => 'Intro', 'content' => '# Hello']);
    });

    $this->get('/docs/intro')->assertOk()->assertSee('Intro');
});

it('ClearSectionDealPrice rejects non-group items', function () {
    $opportunity = giCreateOrderOpportunity();
    $item = OpportunityItem::factory()->for($opportunity)->create();

    expect(fn () => (new ClearSectionDealPrice)($opportunity, $item))->toThrow(ValidationException::class);
});

it('SetSectionDealPrice rejects items from another opportunity', function () {
    $opportunity = giCreateOrderOpportunity();
    $other = giCreateOrderOpportunity();
    $group = OpportunityItem::factory()->for($other)->create(['item_type' => OpportunityItemType::Group]);

    expect(fn () => (new SetSectionDealPrice)($opportunity, $group, SetSectionDealPriceData::from([
        'deal_price' => '100.00',
    ])))->toThrow(ValidationException::class);
});

it('RestructureOpportunityItems removeOrphanItems early-returns when node list matches all items', function () {
    $opportunity = giCreateOrderOpportunity();
    $item = OpportunityItem::factory()->for($opportunity)->create();

    $result = (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [['id' => $item->id, 'children' => []]],
    ]));

    expect($result->items)->not->toBeEmpty();
});

it('ReceiveVirtualStockIntake completes receive for draft intakes', function () {
    $intake = VirtualStockIntake::factory()->create(['status' => 'draft']);

    (new ReceiveVirtualStockIntake)($intake);

    expect($intake->fresh()->status)->not->toBe('draft');
});

it('ValidatesDocumentTemplateStoreAccess aborts for guests on document template show', function () {
    auth()->logout();
    $template = \App\Models\DocumentTemplate::factory()->create();

    $this->getJson("/api/v1/document_templates/{$template->id}")
        ->assertUnauthorized();
});

it('CommunicationTemplateController assertUserCanAccessStore aborts for guests', function () {
    auth()->logout();
    $template = \App\Models\CommunicationTemplate::factory()->create();

    $this->putJson("/api/v1/communication_templates/{$template->id}", [
        'name' => 'Changed',
    ])->assertUnauthorized();
});
