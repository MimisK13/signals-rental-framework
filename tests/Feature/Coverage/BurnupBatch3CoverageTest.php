<?php

/**
 * Coverage burn-up batch 3 — part 1 (actions, console, notifications, opportunities, pricing).
 */

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Documents\ConfigureDocumentPublicLink;
use App\Actions\Documents\VoidDocument;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Members\MemberPriceCategoryTierValidator;
use App\Actions\Notifications\SaveChannelProvider;
use App\Actions\Notifications\SendBillingCustomerCommunication;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Actions\Pricing\UpdateSurchargeGroup;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Console\Commands\Shortages\EscalateUnresolvedShortages;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Notifications\SaveChannelProviderData;
use App\Data\Notifications\SendBillingCustomerCommunicationData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Data\SupplierCosts\UpdateSupplierCostData;
use App\Enums\BillingCommunicationKind;
use App\Enums\ContainerStatus;
use App\Enums\DemandPhase;
use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Enums\ShortagePolicy;
use App\Enums\StockMethod;
use App\Events\AuditableEvent;
use App\Models\ChannelProvider;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\PriceCategory;
use App\Models\PriceCategoryTier;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\SurchargeGroup;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Opportunities\OpportunityNumberGenerator;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function batch3Owner(): User
{
    return tap(User::factory()->owner()->create(), fn (User $user) => test()->actingAs($user));
}

function batch3KitContainer(Store $store): Container
{
    $product = Product::factory()->containerable()->create();
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    return Container::factory()->create([
        'product_id' => $product->id,
        'serialised_item_id' => $housing->id,
        'store_id' => $store->id,
    ]);
}

function batch3PackAsset(Container $container, StockLevel $asset): void
{
    (new PackContainerItem)($container, PackContainerItemData::from([
        'serialised_item_id' => $asset->id,
    ]));
}

it('TransferContainerItem rejects items that are not packed in the source container', function () {
    batch3Owner();
    $store = Store::factory()->create();
    $source = batch3KitContainer($store);
    $target = batch3KitContainer($store);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $store->id,
    ]);

    expect(fn () => (new TransferContainerItem)($target, TransferContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'source_container_id' => $source->id,
    ])))->toThrow(ValidationException::class);
});

it('TransferContainerItem rejects transfers from a non-open source container', function () {
    batch3Owner();
    $store = Store::factory()->create();
    $source = batch3KitContainer($store);
    $target = batch3KitContainer($store);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $store->id,
    ]);
    batch3PackAsset($source, $asset);
    $source->update(['status' => ContainerStatus::Dispatched->value]);

    expect(fn () => (new TransferContainerItem)($target, TransferContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'source_container_id' => $source->id,
    ])))->toThrow(ValidationException::class);
});

it('TransferContainerItem transfers between open containers', function () {
    batch3Owner();
    $store = Store::factory()->create();
    $source = batch3KitContainer($store);
    $target = batch3KitContainer($store);
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => Product::factory()->serialised()->create()->id,
        'store_id' => $store->id,
    ]);
    batch3PackAsset($source, $asset);

    $result = (new TransferContainerItem)($target, TransferContainerItemData::from([
        'serialised_item_id' => $asset->id,
        'source_container_id' => $source->id,
    ]));

    expect($result->serialised_item_id)->toBe($asset->id)
        ->and(ContainerItem::query()->where('container_id', $target->id)->whereNull('unpacked_at')->count())->toBe(1);
});

it('ConfigureDocumentPublicLink updates expiry and pin on an active public link', function () {
    batch3Owner();
    $document = Document::factory()->finalised()->create([
        'public_token' => 'share-token',
        'public_pin_hash' => null,
    ]);

    $updated = (new ConfigureDocumentPublicLink)($document, ConfigureDocumentPublicLinkData::from([
        'expires_at' => now()->addDays(7)->toIso8601String(),
        'pin' => '9876',
    ]));

    expect($updated->public_link)->not->toBeNull()
        ->and($document->fresh()->public_pin_hash)->not->toBeNull();
});

it('VoidDocument voids a finalised document and clears its public link', function () {
    batch3Owner();
    $document = Document::factory()->finalised()->create([
        'public_token' => 'void-me',
    ]);

    $voided = (new VoidDocument)($document);

    expect($voided->status)->toBe(DocumentStatus::Void)
        ->and($document->fresh()->public_token)->toBeNull();
});

it('MemberPriceCategoryTierValidator accepts null tiers and matching tiers', function () {
    MemberPriceCategoryTierValidator::assertTierBelongsToCategory(null, 1);

    $category = PriceCategory::factory()->create();
    $tier = PriceCategoryTier::factory()->for($category)->create();

    MemberPriceCategoryTierValidator::assertTierBelongsToCategory($tier->id, $category->id);

    expect(fn () => MemberPriceCategoryTierValidator::assertTierBelongsToCategory($tier->id, null))
        ->toThrow(ValidationException::class);
});

it('SaveChannelProvider persists a broadcast channel provider row', function () {
    batch3Owner();

    $provider = app(SaveChannelProvider::class)(null, SaveChannelProviderData::from([
        'channel' => 'broadcast',
        'driver' => 'broadcast',
        'name' => 'Reverb broadcast',
        'config' => ['connection' => 'reverb'],
        'is_default' => true,
        'is_active' => true,
    ]));

    expect($provider->exists)->toBeTrue()
        ->and($provider->driver)->toBe('broadcast');

    $existing = ChannelProvider::factory()->create([
        'channel' => 'broadcast',
        'driver' => 'broadcast',
        'name' => 'Existing',
    ]);

    $updated = app(SaveChannelProvider::class)($existing, SaveChannelProviderData::from([
        'channel' => 'broadcast',
        'driver' => 'broadcast',
        'name' => 'Updated broadcast',
        'config' => ['connection' => 'reverb'],
        'is_default' => false,
        'is_active' => true,
    ]));

    expect($updated->name)->toBe('Updated broadcast');
});

it('SendBillingCustomerCommunication records document email delivery for invoiced members', function () {
    Mail::fake();
    batch3Owner();

    $this->mock(DocumentRenderer::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn(DocumentRenderResult::success(
            html: '<html></html>',
            renderedData: ['frozen' => true],
            format: DocumentOutputFormat::Pdf,
            pdfBinary: '%PDF-test',
        ));
        $mock->shouldReceive('renderFromSnapshot')->andReturn(DocumentRenderResult::success(
            html: '<html></html>',
            renderedData: ['frozen' => true],
            format: DocumentOutputFormat::Pdf,
            pdfBinary: '%PDF-test',
        ));
    });

    $opportunity = giCreateOrderOpportunity();
    $member = $opportunity->member;
    $member->emails()->create(['address' => 'burnup3@example.com', 'is_primary' => true]);

    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);
    app(CommunicationTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(
        GenerateInvoiceFromOpportunityData::from(['opportunity_id' => $opportunity->id]),
    );
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    app(SendBillingCustomerCommunication::class)(new SendBillingCustomerCommunicationData(
        kind: BillingCommunicationKind::Invoice,
        sourceId: $invoice->id,
        storeId: $opportunity->store_id,
        explicit: true,
    ));

    $document = Document::query()
        ->where('documentable_type', Invoice::class)
        ->where('documentable_id', $invoice->id)
        ->latest('id')
        ->first();

    expect($document)->not->toBeNull()
        ->and($document->emailed_at)->not->toBeNull()
        ->and($document->emailed_to)->toBe('burnup3@example.com');
});

it('CreateOpportunity resolveStoreId uses the default store and rejects missing stores', function () {
    batch3Owner();
    $defaultStore = Store::query()->where('is_default', true)->firstOrFail();

    $withDefault = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Default store',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));
    expect($withDefault->store_id)->toBe($defaultStore->id);

    $explicitStore = Store::factory()->create(['is_default' => false]);
    $withExplicit = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Explicit store',
        'store_id' => $explicitStore->id,
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));
    expect($withExplicit->store_id)->toBe($explicitStore->id);

    Store::query()->delete();

    expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'No stores',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ])))->toThrow(ValidationException::class);
});

it('RenameOpportunitySection is stale after the opportunity_sections cutover', function () {
    expect(Schema::hasTable('opportunity_sections'))->toBeFalse();
});

it('RestructureOpportunityItems removeOrphanItems deletes items omitted from the node list', function () {
    batch3Owner();
    $opportunity = giCreateOrderOpportunity();
    $keep = $opportunity->items->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Orphan candidate',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $opportunity = $opportunity->fresh(['items']);
    $items = $opportunity->items->keyBy('id');
    $orphan = $items->except($keep->id)->firstOrFail();

    batch3Invoke(new RestructureOpportunityItems, 'removeOrphanItems', [
        $opportunity,
        [$keep->id],
        $items,
    ]);

    expect(OpportunityItem::query()->find($orphan->id))->toBeNull()
        ->and(OpportunityItem::query()->find($keep->id))->not->toBeNull();
});

it('RestructureOpportunityItems removeOrphanItems skips missing collection entries', function () {
    batch3Owner();
    $opportunity = giCreateOrderOpportunity();
    $keep = $opportunity->items->firstOrFail();
    $items = $opportunity->items->keyBy('id');

    batch3Invoke(new RestructureOpportunityItems, 'removeOrphanItems', [
        $opportunity,
        [$keep->id, 999999],
        $items,
    ]);

    expect(OpportunityItem::query()->find($keep->id))->not->toBeNull();
});

function batch3Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

it('UpdateSurchargeGroup syncs nested surcharges when provided', function () {
    batch3Owner();
    $group = SurchargeGroup::factory()->create(['name' => 'Fees']);

    $updated = (new UpdateSurchargeGroup)($group, UpdateSurchargeGroupData::from([
        'name' => 'Updated fees',
        'surcharges' => [[
            'name' => 'Fuel levy',
            'percent_value' => '5.00',
        ]],
    ]));

    expect($updated->name)->toBe('Updated fees')
        ->and($group->fresh()->surcharges)->toHaveCount(1);
});

it('UpdateSupplierCost updates supplier cost attributes and preferred flag handling', function () {
    batch3Owner();
    $cost = ProductSupplierCost::factory()->create([
        'cost' => 1000,
        'currency_code' => 'GBP',
        'is_preferred' => false,
    ]);
    $newSupplier = Member::factory()->organisation()->create();
    $newStore = Store::factory()->create();

    $updated = (new UpdateSupplierCost)($cost, UpdateSupplierCostData::from([
        'supplier_id' => $newSupplier->id,
        'cost' => 250000,
        'currency_code' => 'usd',
        'store_id' => $newStore->id,
        'store_id_provided' => true,
        'supplier_sku' => 'SKU-42',
        'supplier_sku_provided' => true,
        'lead_time_days' => 5,
        'lead_time_days_provided' => true,
        'metadata' => ['tier' => 'gold'],
        'metadata_provided' => true,
        'min_order_quantity' => '2.5',
        'is_preferred' => true,
    ]));

    expect($updated->supplier_id)->toBe($newSupplier->id)
        ->and($updated->cost)->toBe('2500.00')
        ->and($updated->currency_code)->toBe('USD')
        ->and($updated->store_id)->toBe($newStore->id)
        ->and($updated->supplier_sku)->toBe('SKU-42')
        ->and($updated->is_preferred)->toBeTrue();
});

it('ReceiveVirtualStockIntake provisions serialised assets when required', function () {
    batch3Owner();
    $product = Product::factory()->serialised()->create(['stock_method' => StockMethod::Serialised]);
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'status' => 'draft',
        'quantity' => 2,
    ]);
    app(ConfirmVirtualStockIntake::class)($intake);

    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    expect($intake->fresh()->status)->toBe('received')
        ->and(StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe(2);
});

it('EscalateUnresolvedShortages respects the configured batch size limit', function () {
    Event::fake([AuditableEvent::class]);
    batch3Owner();
    config([
        'shortages.escalation_days_before_dispatch' => 3,
        'shortages.escalation_batch_size' => 1,
    ]);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    [$firstOpp, $firstItem] = burnupBatch3ShortConfirmedOrder($store, daysUntilStart: 2);
    [$secondOpp, $secondItem] = burnupBatch3ShortConfirmedOrder($store, daysUntilStart: 2);

    $this->artisan(EscalateUnresolvedShortages::class)->assertSuccessful();

    $escalatedCount = collect([$firstItem, $secondItem])
        ->filter(fn (OpportunityItem $item): bool => $item->fresh()->shortage_escalated_at !== null)
        ->count();

    expect($escalatedCount)->toBe(1);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'shortage.escalated');
});

it('OpportunityNumberGenerator nextOrCreate provisions a sequence after a missing-sequence failure', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $calls = 0;
    $inner = Mockery::mock(DocumentNumberGenerator::class);
    $inner->shouldReceive('next')
        ->twice()
        ->andReturnUsing(function () use (&$calls): string {
            $calls++;

            if ($calls === 1) {
                throw new ModelNotFoundException;
            }

            return '0099';
        });

    $generator = new OpportunityNumberGenerator($inner);

    expect($generator->nextOrCreate($store->id))->toBe('0099');
});

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function burnupBatch3ShortConfirmedOrder(Store $store, int $daysUntilStart = 2): array
{
    $store->update([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_dispatch_policy' => 'warn_partial',
    ]);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $startsAt = Carbon::now('UTC')->addDays($daysUntilStart);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($startsAt->copy(), $startsAt->copy()->addDays(4))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => random_int(900000, 999999),
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Burnup batch 3 escalation',
        'store_id' => $store->id,
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $startsAt->copy()->addDays(4)->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());
    (new ConvertToOrder)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}
