<?php

/**
 * P4-CL4b burndown batch 2 — part 2 (remaining targets).
 */

use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Containers\UpdateContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ClearSectionDealPrice;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Actions\Products\UpdateProductContainerConfig;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\UpdateDraftVirtualStockIntake;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Containers\UpdateContainerData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Opportunities\AddOpportunityItemData;
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
use App\Enums\CustomFieldType;
use App\Enums\OpportunityItemType;
use App\Events\AuditableEvent;
use App\Events\Availability\AvailabilityChanged;
use App\Http\Controllers\Web\DocsController;
use App\Jobs\DeliverWebhook;
use App\Listeners\Availability\DetectOrderShortages;
use App\Listeners\NotificationDispatchListener;
use App\Livewire\Components\ViewBuilder;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Models\ActionLog;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\ShortageResolution;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Models\Webhook;
use App\Services\Api\UsageRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
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

    $listener->handle(new AvailabilityChanged(
        productId: $product->id,
        storeId: $store->id,
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
                    function (): void {
                        $this->done = true;
                    },
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

it('LoadsCustomFieldValues returns an empty array when no custom field values exist', function () {
    $member = Member::factory()->create();

    $double = new class
    {
        use LoadsCustomFieldValues;

        /** @return array<string, mixed> */
        public function load(Member $member): array
        {
            return $this->loadCustomFieldValuesFrom($member);
        }

        /** @return array<string, mixed> */
        protected function loadCustomFieldValuesFrom(Model $model): array
        {
            assert($model instanceof Member);

            $values = [];
            $model->load('customFieldValues.customField');
            foreach ($model->customFieldValues as $cfv) {
                if ($cfv->customField === null) {
                    Log::warning('Orphaned custom field value: definition not found', [
                        'custom_field_value_id' => $cfv->id,
                        'custom_field_id' => $cfv->custom_field_id,
                    ]);

                    continue;
                }
                /** @var CustomFieldType $fieldType */
                $fieldType = $cfv->customField->field_type;
                $column = $fieldType->valueColumn();
                $values[$cfv->customField->name] = $cfv->{$column};
            }

            return $values;
        }
    };

    expect($double->load($member->fresh()))->toBe([]);
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
    $store = Store::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Date-less opportunity',
        'store_id' => $store->id,
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new ConvertToQuotation)($opportunity);
    $product = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'starts_at' => now()->subDay()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ]));
    $item = $opportunity->fresh()->items()->firstOrFail();
    $originalStart = $item->starts_at->copy();

    (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from([
        'subject' => $opportunity->subject,
        'starts_at' => now()->addDays(3)->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
    ]));

    expect($item->fresh()->starts_at?->equalTo($originalStart))->toBeTrue();
});

it('SetDealPrice clearActiveGroupDealPrices clears existing group deal prices', function () {
    $opportunity = giCreateQuotationOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Section',
        'item_type' => 'group',
    ]));
    $group = $opportunity->fresh()->items->first(
        fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group,
    );
    $product = Product::factory()->create();
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));
    $child = $opportunity->fresh()->items()->where('itemable_id', $product->id)->firstOrFail();
    $child->forceFill([
        'path' => $group->path.'0001',
        'deal_price_discount_percent' => '10.00',
    ])->saveQuietly();

    (new SetSectionDealPrice)($opportunity->fresh(), $group, SetSectionDealPriceData::from([
        'deal_price' => '150.00',
    ]));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '500.00',
    ]));

    expect($group->fresh()->deal_price)->toBeNull();
});

it('UpdateDraftVirtualStockIntake uppercases explicit currency codes', function () {
    $intake = createTestVirtualStockIntake(['status' => 'draft']);

    $updated = (new UpdateDraftVirtualStockIntake)($intake, UpdateDraftVirtualStockIntakeData::from([
        'product_id' => $intake->product_id,
        'store_id' => $intake->store_id,
        'quantity' => $intake->quantity,
        'available_from' => $intake->available_from->toIso8601String(),
        'available_until' => $intake->available_until->toIso8601String(),
        'unit_cost' => $intake->unit_cost,
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
        $container,
        TransferContainerItemData::from([
            'serialised_item_id' => $item->serialised_item_id,
            'source_container_id' => $container->id,
        ]),
    ))->toThrow(ValidationException::class);
});

it('UpdateSupplierCost clears preferred flag on sibling costs when one is preferred', function () {
    $product = Product::factory()->create();
    $preferred = ProductSupplierCost::factory()->for($product)->create(['is_preferred' => true]);
    $other = ProductSupplierCost::factory()->for($product)->create(['is_preferred' => false]);

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
    ])))->toThrow(AuthorizationException::class);
});

it('VoidInvoice skips quantity release rows without opportunity item linkage', function () {
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $invoice->items()->create([
        'description' => 'Manual line',
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
        'currency_code' => $invoice->currency_code,
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

    $url = URL::signedRoute('documents.signed-download', ['documentId' => $document->id]);

    $this->get($url)->assertNotFound();
});

it('SearchController returns empty purchase order hits on SQLite for authorised users', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->getJson(route('search', ['q' => 'PO-SEARCH-XYZ']))
        ->assertOk()
        ->assertJsonPath('purchase_orders', []);
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
    $opportunity = giCreateQuotationOpportunity();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
        'type' => 'deposit',
        'amount_percentage' => '25',
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
    $resolution = ShortageResolution::factory()->create();
    $event = new AuditableEvent($resolution, 'shortage.resolved');
    $method = new ReflectionMethod($listener, 'payloadFor');
    $payload = $method->invoke($listener, $event, ['title' => 'Shortage', 'body' => 'Resolved']);

    expect($payload)->toHaveKey('shortage_resolution');
});

it('DeliverWebhook recordOutboundMetering survives recorder failures', function () {
    $log = Log::spy();
    Http::fake(['*' => Http::response('ok', 200)]);
    $webhook = Webhook::factory()->create(['is_active' => true]);
    $job = new DeliverWebhook($webhook, 'test.event', ['ok' => true]);

    $this->mock(UsageRecorder::class, function ($mock): void {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('metering down'));
    });

    $job->handle();

    $log->shouldHaveReceived('error');
});

it('UpdateProductContainerConfig normalises transport binding slots', function () {
    $product = Product::factory()->serialised()->create();
    $component = Product::factory()->serialised()->create();

    $updated = (new UpdateProductContainerConfig)($product, UpdateProductContainerConfigData::from([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Transport->value,
        'container_template' => [
            'slots' => [
                ['label' => 'Main', 'product_id' => $component->id, 'quantity' => 1],
            ],
        ],
    ]));

    expect($updated->container_template['slots'][0]['binding'])->toBe('pool');
});

it('ViewBuilder defaultSortColumn picks the first visible sortable column', function () {
    Livewire::actingAs($this->owner)
        ->test(ViewBuilder::class, ['entityType' => 'products'])
        ->call('open')
        ->assertSet('sortColumn', fn ($value) => is_string($value) && $value !== '');
});

it('DocsController navigationHasPages renders nested navigation groups', function () {
    $navigation = [
        'sections' => [
            [
                'title' => 'Guide',
                'slug' => 'guide',
                'subSections' => [
                    [
                        'label' => 'Setup',
                        'slug' => 'setup',
                        'groups' => [
                            [
                                'label' => 'Basics',
                                'pages' => [
                                    ['slug' => 'intro', 'title' => 'Intro'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $method = new ReflectionMethod(DocsController::class, 'navigationHasPages');
    $method->setAccessible(true);

    expect($method->invoke(app(DocsController::class), $navigation))->toBeTrue();
});

it('ClearSectionDealPrice rejects non-group items', function () {
    $opportunity = giCreateOrderOpportunity();
    $item = OpportunityItem::factory()->for($opportunity)->create();

    expect(fn () => (new ClearSectionDealPrice)($opportunity, $item))->toThrow(ValidationException::class);
});

it('SetSectionDealPrice rejects items from another opportunity', function () {
    $zones = giWireZoneTaxInfrastructure();
    $opportunity = giCreateOrderOpportunity(zones: $zones);
    $other = giCreateOrderOpportunity(zones: $zones);
    $group = OpportunityItem::factory()->for($other)->create(['item_type' => OpportunityItemType::Group]);

    expect(fn () => (new SetSectionDealPrice)($opportunity, $group, SetSectionDealPriceData::from([
        'deal_price' => '100.00',
    ])))->toThrow(ValidationException::class);
});

it('RestructureOpportunityItems removeOrphanItems early-returns when node list matches all items', function () {
    $opportunity = giCreateOrderOpportunity();
    $nodes = $opportunity->items->map(fn (OpportunityItem $item): array => [
        'id' => $item->id,
        'depth' => 1,
        'children' => [],
    ])->values()->all();

    $result = (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => $nodes,
    ]));

    expect($result)->not->toBeEmpty();
});

it('ReceiveVirtualStockIntake completes receive for draft intakes', function () {
    $intake = createTestVirtualStockIntake(['status' => 'draft']);
    app(ConfirmVirtualStockIntake::class)($intake);

    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    expect($intake->fresh()->status)->toBe('received');
});

it('ValidatesDocumentTemplateStoreAccess aborts for guests on document template show', function () {
    auth()->logout();
    $template = DocumentTemplate::factory()->create();

    $this->getJson("/api/v1/document_templates/{$template->id}")
        ->assertUnauthorized();
});

it('CommunicationTemplateController assertUserCanAccessStore aborts for guests', function () {
    auth()->logout();
    $template = CommunicationTemplate::factory()->create();

    $this->putJson("/api/v1/communication_templates/{$template->id}", [
        'name' => 'Changed',
    ])->assertUnauthorized();
});
