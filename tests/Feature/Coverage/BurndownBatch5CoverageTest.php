<?php

/**
 * P4-CL4b burndown batch 5 — classes 1–18 (cheapest-first slice).
 */

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Documents\ImportDocumentTemplate;
use App\Actions\Documents\RenderDocumentPreview;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\ListValues\UpdateListName;
use App\Actions\Notifications\LogCommunication;
use App\Actions\Notifications\MarkNotificationRead;
use App\Actions\Notifications\SendBillingCustomerCommunication;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\MergeOpportunityItems;
use App\Actions\Pricing\DeleteSurcharge;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Documents\ImportDocumentTemplateData;
use App\Data\Documents\RenderDocumentPreviewData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\ListValues\UpdateListNameData;
use App\Data\Notifications\CreateCommunicationLogData;
use App\Data\Notifications\SendBillingCustomerCommunicationData;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Enums\BillingCommunicationKind;
use App\Enums\ContainerStatus;
use App\Enums\DocumentOutputFormat;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\ListNameController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\SurchargeController;
use App\Http\Middleware\LogApiRequest;
use App\Jobs\ExportApiUsage;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\CreditNote;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\RequestLog;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\UsageDaily;
use App\Models\User;
use App\Queries\Api\UsageQuery;
use App\Services\Api\PriceWeightResolver;
use App\Services\Api\UsageRecorder;
use App\Support\Api\MeteringBodyRedactor;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

/**
 * @param  array<int, mixed>  $args
 */
function batch5InvokeProtected(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function batch5Owner(): User
{
    return User::factory()->owner()->create();
}

it('ListNameController exposes update data and action class names', function () {
    $controller = new ListNameController;

    expect(batch5InvokeProtected($controller, 'updateDataClass'))->toBe(UpdateListNameData::class)
        ->and(batch5InvokeProtected($controller, 'updateActionClass'))->toBe(UpdateListName::class);
});

it('MemberController exposes plural and entity type keys', function () {
    $controller = new MemberController;

    expect(batch5InvokeProtected($controller, 'pluralKey'))->toBe('members')
        ->and(batch5InvokeProtected($controller, 'entityType'))->toBe('members');
});

it('ExportApiUsage applies token and channel filters when writing CSV', function () {
    Storage::fake();
    Carbon::setTestNow(now()->subDays(1));
    $user = User::factory()->create();
    $token = $user->createToken('usage-export', ['products:read']);

    UsageDaily::factory()->create([
        'date' => now()->toDateString(),
        'key' => 'api.v1.filtered.index',
        'channel' => 'api',
        'status_class' => '2xx',
        'personal_access_token_id' => $token->accessToken->id,
        'request_count' => 3,
        'weighted_units' => 3,
    ]);

    (new ExportApiUsage($user->id, [
        'dimension' => 'endpoint',
        'window' => '7d',
        'token_id' => (string) $token->accessToken->id,
        'channel' => 'api',
    ]))->handle(app(UsageQuery::class));

    $stored = Cache::get("api-usage-export:{$user->id}");
    expect($stored)->not->toBeNull();

    $content = Storage::get($stored);
    expect($content)->toContain('api.v1.filtered.index');

    Carbon::setTestNow();
});

it('SurchargeController deleteActionClass show and destroy endpoints are reachable', function () {
    Sanctum::actingAs(batch5Owner(), ['pricing:read', 'pricing:write']);

    expect(batch5InvokeProtected(new SurchargeController, 'deleteActionClass'))
        ->toBe(DeleteSurcharge::class);

    $surcharge = Surcharge::factory()->create();

    $this->getJson("/api/v1/surcharges/{$surcharge->id}")
        ->assertOk()
        ->assertJsonPath('surcharge.id', $surcharge->id);

    $this->deleteJson("/api/v1/surcharges/{$surcharge->id}")
        ->assertNoContent();
});

it('PurchaseOrderController exposes resource wiring class names', function () {
    $controller = new PurchaseOrderController;

    expect(batch5InvokeProtected($controller, 'createDataClass'))->toBe(CreatePurchaseOrderData::class)
        ->and(batch5InvokeProtected($controller, 'updateDataClass'))->toBe(UpdateDraftPurchaseOrderData::class)
        ->and(batch5InvokeProtected($controller, 'createActionClass'))->toBe(CreatePurchaseOrder::class)
        ->and(batch5InvokeProtected($controller, 'updateActionClass'))->toBe(UpdateDraftPurchaseOrder::class);
});

it('LogCommunication rejects unknown statuses and stamps delivered and opened timestamps', function () {
    $member = Member::factory()->create();

    expect(fn () => app(LogCommunication::class)(CreateCommunicationLogData::from([
        'notificationTypeKey' => 'invoice.created',
        'channel' => 'email',
        'notifiableType' => Member::class,
        'notifiableId' => $member->id,
        'subjectType' => Member::class,
        'subjectId' => $member->id,
        'status' => 'not-a-real-status',
    ])))->toThrow(InvalidArgumentException::class);

    $log = CommunicationLog::factory()->create(['status' => 'sent']);

    $delivered = app(LogCommunication::class)->transition(new TransitionCommunicationLogData(
        communicationLogId: $log->id,
        status: 'delivered',
        messageId: 'msg-123',
    ));

    expect($delivered->status)->toBe('delivered')
        ->and($delivered->delivered_at)->not->toBeNull()
        ->and($delivered->message_id)->toBe('msg-123');

    $opened = app(LogCommunication::class)->transition(new TransitionCommunicationLogData(
        communicationLogId: $delivered->id,
        status: 'opened',
        failureReason: 'ignored-on-open',
    ));

    expect($opened->status)->toBe('opened')
        ->and($opened->opened_at)->not->toBeNull();
});

it('RenderDocumentPreview rejects unsupported template model types and render failures', function () {
    $this->actingAs(batch5Owner());
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->for($store)->create();
    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'statement',
        'model_type' => Member::class,
        'body_html' => '<p>Statement</p>',
    ]);

    expect(fn () => app(RenderDocumentPreview::class)(new RenderDocumentPreviewData(
        document_template_id: $template->id,
        source_id: $opportunity->id,
    )))->toThrow(RuntimeException::class, 'Unsupported document template model type');

    $quoteTemplate = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<p>{{ $opportunity->subject }}</p>',
    ]);

    $this->mock(DocumentRenderer::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn(DocumentRenderResult::failure('Renderer unavailable', DocumentOutputFormat::Preview));
    });

    expect(fn () => app(RenderDocumentPreview::class)(new RenderDocumentPreviewData(
        document_template_id: $quoteTemplate->id,
        source_id: $opportunity->id,
    )))->toThrow(ValidationException::class, 'Renderer unavailable');
});

it('MemberNotificationPreferenceController returns 404 when member has no linked user', function () {
    $manager = User::factory()->create(['is_owner' => false]);
    $manager->givePermissionTo(['members.view', 'members.edit']);
    $store = Store::factory()->create();
    MemberStore::create([
        'member_id' => $manager->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);

    $memberWithoutUser = Member::factory()->organisation()->create();
    MemberStore::create([
        'member_id' => $memberWithoutUser->id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($manager, ['notification-preferences:read']);

    $this->getJson("/api/v1/members/{$memberWithoutUser->id}/notification_preferences")
        ->assertNotFound();
});

it('LogApiRequest terminate skips logging when disabled and handles short route names', function () {
    config(['api_metering.logging_enabled' => false]);

    $middleware = app(LogApiRequest::class);
    $request = Request::create('/api/v1/products', 'GET');
    $response = new Response('', 200);

    $middleware->terminate($request, $response);

    expect(RequestLog::query()->count())->toBe(0);

    config(['api_metering.logging_enabled' => true]);

    expect(batch5InvokeProtected($middleware, 'parseRouteName', ['api.short']))->toBe([null, null])
        ->and(batch5InvokeProtected($middleware, 'parseRouteName', ['api.v1.products.index']))->toBe(['products', 'index']);

    $requestWithoutStart = Request::create('/api/v1/schema', 'GET');
    expect(batch5InvokeProtected($middleware, 'resolveDurationMs', [$requestWithoutStart]))->toBeInt();

    /** @var UsageRecorder&MockInterface $recorder */
    $recorder = mock(UsageRecorder::class, function ($mock): void {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('recorder down'));
        $mock->shouldReceive('reportFailure')->once();
    });

    $middlewareWithMock = new LogApiRequest(
        $recorder,
        app(PriceWeightResolver::class),
        app(MeteringBodyRedactor::class),
    );

    $requestWithStart = Request::create('/api/v1/products', 'GET');
    $requestWithStart->attributes->set('api_metering.start', microtime(true) - 0.05);
    $middlewareWithMock->terminate($requestWithStart, new Response('', 200));
});

it('ImportDocumentTemplate validates export metadata and allocates unique slugs', function () {
    $this->actingAs(batch5Owner());
    $store = Store::factory()->create();
    DocumentTemplate::factory()->for($store)->create(['slug' => 'imported-template']);
    DocumentTemplate::factory()->for($store)->create(['slug' => 'imported-template-2']);

    expect(fn () => (new ImportDocumentTemplate)(ImportDocumentTemplateData::from([
        'store_id' => $store->id,
        'payload' => [
            'export_format' => 'signals.document_template',
            'export_version' => 99,
            'name' => 'Broken version',
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Body</p>',
        ],
    ])))->toThrow(ValidationException::class);

    $imported = (new ImportDocumentTemplate)(ImportDocumentTemplateData::from([
        'store_id' => $store->id,
        'payload' => [
            'export_format' => 'signals.document_template',
            'export_version' => 1,
            'name' => 'Imported Template',
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Imported</p>',
        ],
    ]));

    expect($imported->slug)->toBe('imported-template-3');
});

it('DispatchContainer guardTransportContainer and guardOpportunity reject invalid targets', function () {
    $this->actingAs(batch5Owner());
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->for($store)->create(['state' => OpportunityState::Quotation]);

    $transport = Container::factory()->transport()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Open,
    ]);
    $order = Opportunity::factory()->for($store)->create(['state' => OpportunityState::Order]);

    expect(fn () => batch5InvokeProtected(new DispatchContainer, 'dispatchTransport', [
        $transport,
        DispatchContainerData::from(['opportunity_id' => $order->id]),
    ]))->toThrow(ValidationException::class, 'Only sealed containers can be dispatched');

    $sealedKit = Container::factory()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Sealed,
    ]);

    expect(fn () => batch5InvokeProtected(new DispatchContainer, 'dispatchTransport', [
        $sealedKit,
        DispatchContainerData::from(['opportunity_id' => $order->id]),
    ]))->toThrow(ValidationException::class, 'Only transport-mode containers dissolve on dispatch');

    $sealedTransport = Container::factory()->transport()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Sealed,
    ]);

    expect(fn () => batch5InvokeProtected(new DispatchContainer, 'dispatchTransport', [
        $sealedTransport,
        DispatchContainerData::from(['opportunity_id' => $opportunity->id]),
    ]))->toThrow(ValidationException::class, 'The target opportunity must be an order');

    $closedOrder = Opportunity::factory()->for($store)->order()->create([
        'status' => OpportunityStatus::OrderComplete->statusValue(),
    ]);

    expect(fn () => batch5InvokeProtected(new DispatchContainer, 'dispatchTransport', [
        $sealedTransport,
        DispatchContainerData::from(['opportunity_id' => $closedOrder->id]),
    ]))->toThrow(ValidationException::class, 'A closed opportunity cannot receive a container dispatch');
});

it('MarkNotificationRead requires a member profile and existing notification id', function () {
    $userWithoutMember = User::factory()->create();
    $userWithoutMember->forceFill(['member_id' => null])->saveQuietly();

    expect(fn () => (new MarkNotificationRead)($userWithoutMember, '00000000-0000-0000-0000-000000000000'))
        ->toThrow(ValidationException::class, 'No member profile is linked to this account');

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);

    expect(fn () => (new MarkNotificationRead)($user, '00000000-0000-0000-0000-000000000000'))
        ->toThrow(ValidationException::class, 'Notification not found.');

    $notification = $member->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'App\Notifications\TestPipelineNotification',
        'data' => ['title' => 'Unread'],
    ]);

    (new MarkNotificationRead)($user, $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();

    $member->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'App\Notifications\TestPipelineNotification',
        'data' => ['title' => 'Also unread'],
    ]);

    (new MarkNotificationRead)->markAll($user);

    expect($member->fresh()->unreadNotifications)->toHaveCount(0);
});

it('MergeOpportunityItems copies survivor pricing and treats null hire instants as equal', function () {
    $this->actingAs(batch5Owner());
    $store = Store::factory()->create();
    $product = Product::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Merge matching lines',
        'store_id' => $store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    foreach (['1', '2'] as $qty) {
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => $qty,
            'unit_price' => $qty === '1' ? 1000 : 5000,
        ]));
    }

    $items = $opportunity->fresh()->items()->orderBy('id')->get();
    $survivor = $items->first();
    $duplicate = $items->last();

    (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$duplicate->id],
    ]));

    $merged = $survivor->fresh();

    expect((float) $merged->quantity)->toBe(3.0)
        ->and((int) $merged->unit_price)->toBe(5000)
        ->and($merged->starts_at?->toIso8601String())->toBe('2026-12-01T09:00:00+00:00');
});

it('ApportionVirtualStockCost skips ineligible intakes and removeForIntake no-ops when empty', function () {
    $this->actingAs(batch5Owner());
    $draftIntake = createTestVirtualStockIntake(['status' => 'draft']);

    $skipped = app(ApportionVirtualStockCost::class)($draftIntake->fresh(), authorize: false);

    expect($skipped->allocations)->toBe([]);

    app(ApportionVirtualStockCost::class)->removeForIntake($draftIntake->fresh(), authorize: false);
});

it('ContainerController dissolve and transfer endpoints delegate to actions', function () {
    $this->actingAs(batch5Owner());
    Sanctum::actingAs(batch5Owner(), ['containers:read', 'containers:write']);

    $store = Store::factory()->create();
    $target = Container::factory()->create(['store_id' => $store->id, 'status' => ContainerStatus::Open]);
    $source = Container::factory()->create(['store_id' => $store->id, 'status' => ContainerStatus::Open]);
    $product = Product::factory()->rental()->serialised()->create();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new PackContainerItem)($source, PackContainerItemData::from([
        'serialised_item_id' => $stock->id,
    ]));

    $this->postJson("/api/v1/containers/{$target->id}/transfer", [
        'serialised_item_id' => $stock->id,
        'source_container_id' => $source->id,
    ])->assertCreated()
        ->assertJsonPath('container_item.serialised_item_id', $stock->id);

    $emptyContainer = Container::factory()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Open,
    ]);

    $this->postJson("/api/v1/containers/{$emptyContainer->id}/dissolve")
        ->assertOk()
        ->assertJsonPath('container.status', ContainerStatus::Dissolved->value);
});

it('CreditNoteController exposes resource wiring and rejects unsupported mutations', function () {
    $controller = new CreditNoteController;

    expect(batch5InvokeProtected($controller, 'createDataClass'))->toBe(IssueCreditNoteData::class)
        ->and(batch5InvokeProtected($controller, 'updateDataClass'))->toBe(IssueCreditNoteData::class)
        ->and(batch5InvokeProtected($controller, 'createActionClass'))->toBe(IssueCreditNote::class)
        ->and(batch5InvokeProtected($controller, 'updateActionClass'))->toBe(IssueCreditNote::class)
        ->and(batch5InvokeProtected($controller, 'deleteActionClass'))->toBe(IssueCreditNote::class);

    Sanctum::actingAs(batch5Owner(), ['credit-notes:write']);
    $creditNote = CreditNote::factory()->create();

    $controller = app(CreditNoteController::class);

    expect(fn () => $controller->store(Request::create('/api/v1/credit_notes', 'POST')))->toThrow(LogicException::class);
    expect(fn () => $controller->update(Request::create('/api/v1/credit_notes/'.$creditNote->id, 'PUT'), $creditNote))->toThrow(LogicException::class);
    expect(fn () => $controller->destroy($creditNote))->toThrow(LogicException::class);
});

it('SendBillingCustomerCommunication resolves billing context helpers and missing sources', function () {
    $action = app(SendBillingCustomerCommunication::class);
    $store = Store::factory()->create();

    expect(batch5InvokeProtected($action, 'documentTypeForKind', [
        new SendBillingCustomerCommunicationData(
            kind: BillingCommunicationKind::Invoice,
            sourceId: 999999,
            storeId: $store->id,
        ),
    ]))->toBe(BillingCommunicationKind::Invoice->documentType());

    expect(batch5InvokeProtected($action, 'resolveInvoiceContext', [
        new SendBillingCustomerCommunicationData(
            kind: BillingCommunicationKind::Invoice,
            sourceId: 999999,
            storeId: $store->id,
            explicit: true,
        ),
    ]))->toBeNull();

    expect(batch5InvokeProtected($action, 'resolveCreditNoteContext', [
        new SendBillingCustomerCommunicationData(
            kind: BillingCommunicationKind::CreditNote,
            sourceId: 999999,
            storeId: $store->id,
            explicit: true,
        ),
    ]))->toBeNull();

    expect(batch5InvokeProtected($action, 'resolveReceiptContext', [
        new SendBillingCustomerCommunicationData(
            kind: BillingCommunicationKind::Receipt,
            sourceId: 999999,
            storeId: $store->id,
            explicit: true,
        ),
    ]))->toBeNull();

    expect(batch5InvokeProtected($action, 'resolveStatementContext', [
        new SendBillingCustomerCommunicationData(
            kind: BillingCommunicationKind::Statement,
            sourceId: 999999,
            storeId: $store->id,
            explicit: true,
        ),
    ]))->toBeNull();

    settings()->set('invoices.auto_email_on_issue', true);

    CommunicationTemplate::factory()->for($store)->create([
        'notification_type_id' => NotificationType::factory()->create([
            'key' => BillingCommunicationKind::Invoice->notificationTypeKey(),
        ])->id,
        'is_active' => true,
    ]);
    DocumentTemplate::factory()->for($store)->create([
        'document_type' => BillingCommunicationKind::Invoice->documentType(),
        'model_type' => Invoice::class,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs(batch5Owner());
    $otherUser = User::factory()->create();

    $action(new SendBillingCustomerCommunicationData(
        kind: BillingCommunicationKind::Invoice,
        sourceId: 999999,
        storeId: $store->id,
        explicit: true,
        userId: $otherUser->id,
    ));

    expect(auth()->id())->toBe($otherUser->id);
});

it('PackContainerItem resolveTransfer autoReturnAndPack outermostContainer and depthOf branches execute', function () {
    $this->actingAs(batch5Owner());
    $store = Store::factory()->create();

    $parent = Container::factory()->create(['store_id' => $store->id, 'status' => ContainerStatus::Open]);
    $child = Container::factory()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Open,
        'parent_container_id' => $parent->id,
    ]);

    $packer = new PackContainerItem;

    expect(batch5InvokeProtected($packer, 'outermostContainer', [$child])->id)->toBe($parent->id)
        ->and(batch5InvokeProtected($packer, 'depthOf', [$child]))->toBe(2);

    $stock = StockLevel::factory()->serialised()->create(['store_id' => $store->id]);

    expect(fn () => (new PackContainerItem)($child, PackContainerItemData::from([
        'serialised_item_id' => $stock->id,
        'resolve' => 'transfer',
    ])))->toThrow(ValidationException::class, 'No active container membership exists to transfer from');
});
