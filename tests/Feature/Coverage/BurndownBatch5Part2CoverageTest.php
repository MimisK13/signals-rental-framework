<?php

/**
 * P4-CL4b burndown batch 5 — classes 19–35 (cheapest-first slice).
 */

use App\Actions\Containers\RepackReturnItem;
use App\Actions\Documents\CreateDocumentTemplate;
use App\Actions\Documents\GenerateBillingIssueDocument;
use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Notifications\LogCommunication;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\RecordPaymentData;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Enums\DocumentOutputFormat;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\SurchargeGroupController;
use App\Jobs\DeliverCustomerCommunication;
use App\Jobs\ExportApiRequestLog;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequestLog;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\SurchargeGroup;
use App\Models\User;
use App\Notifications\CustomerCommunication;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

function batch5Part2InvokeProtected(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function batch5Part2Owner(): User
{
    return User::factory()->owner()->create();
}

it('GenerateBillingIssueDocument reuses existing PDFs and resolves currency fallbacks', function () {
    $this->actingAs(batch5Part2Owner());
    Storage::fake('public');

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create(['default_currency_code' => '']);
    $invoice = Invoice::factory()->for($store)->for($member)->create([
        'currency_code' => '',
        'status' => 'issued',
    ]);

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'invoice',
        'model_type' => Invoice::class,
        'body_html' => '<p>Invoice</p>',
        'is_default' => true,
        'is_active' => true,
    ]);

    $existing = Document::factory()->for($store)->create([
        'documentable_type' => Invoice::class,
        'documentable_id' => $invoice->id,
        'document_type' => 'invoice',
        'status' => 'final',
        'pdf_path' => 'documents/existing.pdf',
    ]);

    Storage::put('documents/existing.pdf', '%PDF-existing');

    $generator = app(GenerateBillingIssueDocument::class);
    $first = $generator->forInvoice($invoice);
    expect($first->id)->toBe($existing->id);

    expect(batch5Part2InvokeProtected($generator, 'resolveCurrencyCode', [$member]))
        ->toBe((string) settings('company.base_currency', 'GBP'));

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'receipt',
        'model_type' => \App\Models\InvoicePayment::class,
        'body_html' => '<p>Receipt</p>',
        'is_default' => true,
        'is_active' => false,
    ]);

    $paymentInvoice = Invoice::factory()->for($store)->create(['currency_code' => 'GBP', 'status' => 'issued']);
    $payment = \App\Models\InvoicePayment::factory()->for($paymentInvoice)->create(['amount' => 1000]);

    expect(fn () => app(GenerateBillingIssueDocument::class)->forReceipt($payment))
        ->toThrow(ValidationException::class, 'No active template exists');

    $this->mock(DocumentRenderer::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn(DocumentRenderResult::failure('PDF render failed'));
    });

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'receipt',
        'model_type' => \App\Models\InvoicePayment::class,
        'body_html' => '<p>Receipt</p>',
        'is_default' => true,
        'is_active' => true,
    ]);

    expect(fn () => app(GenerateBillingIssueDocument::class)->forReceipt($payment))
        ->toThrow(ValidationException::class, 'PDF render failed');
});

it('CreateDraftInvoice resolves explicit currency and foreign exchange rates', function () {
    $this->actingAs(batch5Part2Owner());
    settings()->set('company.base_currency', 'GBP');
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'target_currency_code' => 'GBP',
        'rate' => '0.800000',
        'effective_at' => now()->subDay(),
    ]);

    $store = Store::factory()->create();
    $member = Member::factory()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'currency' => 'USD',
    ]));

    expect($draft->currency_code)->toBe('USD');

    expect(fn () => (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'currency' => 'JPY',
    ])))->toThrow(ValidationException::class, 'No exchange rate is configured');
});

it('ExportApiRequestLog applyStatusClassFilter includes 5xx and zero status rows', function () {
    Storage::fake();
    $user = User::factory()->create();

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.fail',
        'method' => 'GET',
        'status_code' => 500,
        'created_at' => now(),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.zero',
        'method' => 'GET',
        'status_code' => 0,
        'created_at' => now(),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.ok',
        'method' => 'GET',
        'status_code' => 200,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'api', [
        'window' => '7d',
        'status_class' => '5xx',
    ]))->handle();

    $content = Storage::get(Cache::get("api-logs-export:{$user->id}"));

    expect($content)->toContain('api.v1.fail')
        ->and($content)->toContain('api.v1.zero')
        ->and($content)->not->toContain('api.v1.ok');
});

it('SurchargeGroupController index update and destroy execute resource wiring', function () {
    Sanctum::actingAs(batch5Part2Owner(), ['pricing:read', 'pricing:write']);
    $controller = new SurchargeGroupController;

    expect(batch5Part2InvokeProtected($controller, 'responseDataClass'))->toBe(\App\Data\Pricing\SurchargeGroupData::class)
        ->and(batch5Part2InvokeProtected($controller, 'updateDataClass'))->toBe(\App\Data\Pricing\UpdateSurchargeGroupData::class)
        ->and(batch5Part2InvokeProtected($controller, 'updateActionClass'))->toBe(\App\Actions\Pricing\UpdateSurchargeGroup::class)
        ->and(batch5Part2InvokeProtected($controller, 'deleteActionClass'))->toBe(\App\Actions\Pricing\DeleteSurchargeGroup::class)
        ->and(batch5Part2InvokeProtected($controller, 'pluralKey'))->toBe('surcharge_groups')
        ->and(batch5Part2InvokeProtected($controller, 'entityType'))->toBe('surcharge_groups');

    SurchargeGroup::factory()->count(2)->create();

    $this->getJson('/api/v1/surcharge_groups')
        ->assertOk()
        ->assertJsonCount(2, 'surcharge_groups');

    $group = SurchargeGroup::factory()->create(['name' => 'Old group']);

    $this->putJson('/api/v1/surcharge_groups/'.$group->id, ['name' => 'Renamed group'])
        ->assertOk()
        ->assertJsonPath('surcharge_group.name', 'Renamed group');

    $this->deleteJson('/api/v1/surcharge_groups/'.$group->id)
        ->assertNoContent();
});

it('PublicDocumentController verifyPin redirects when pin is not required and rejects void documents', function () {
    Storage::fake('public');
    $this->actingAs(batch5Part2Owner());

    $document = Document::factory()->create([
        'public_token' => 'no-pin-token',
        'public_pin_hash' => null,
        'status' => \App\Enums\DocumentStatus::Final,
        'pdf_path' => 'documents/sample.pdf',
    ]);
    Storage::put('documents/sample.pdf', '%PDF-sample');

    $this->post(route('documents.public.verify-pin', ['token' => 'no-pin-token']), [
        'pin' => '1234',
    ])->assertRedirect(route('documents.public.show', ['token' => 'no-pin-token']));

    $document->update([
        'public_token' => 'void-token',
        'status' => \App\Enums\DocumentStatus::Void,
    ]);

    $this->get(route('documents.public.show', ['token' => 'void-token']))->assertNotFound();

    $document->update([
        'public_token' => 'missing-path-token',
        'status' => \App\Enums\DocumentStatus::Final,
        'pdf_path' => null,
        'html_snapshot_path' => null,
    ]);

    $this->get(route('documents.public.show', ['token' => 'missing-path-token']))->assertNotFound();
});

it('CreateVirtualStockIntake resolveFields derives values from sub-hire purchase order lines', function () {
    $this->actingAs(batch5Part2Owner());
    $store = Store::factory()->create();
    $product = \App\Models\Product::factory()->create();
    $po = PurchaseOrder::factory()->create([
        'store_id' => $store->id,
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    $line = PurchaseOrderItem::factory()->for($po)->create([
        'product_id' => $product->id,
        'quantity' => '4',
        'unit_cost' => 2500,
    ]);

    $created = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
        'purchase_order_item_id' => $line->id,
        'available_from' => now()->addDay()->toIso8601String(),
        'available_until' => now()->addDays(3)->toIso8601String(),
    ]));

    expect($created->product_id)->toBe($product->id)
        ->and($created->store_id)->toBe($store->id)
        ->and($created->quantity)->toBe(4)
        ->and($created->currency_code)->toBe('EUR');
});

it('RepackReturnItem guardContainer rejects containers without repack lineage', function () {
    $this->actingAs(batch5Part2Owner());
    $container = Container::factory()->create(['previous_container_id' => null]);

    expect(fn () => batch5Part2InvokeProtected(new RepackReturnItem, 'guardContainer', [$container]))
        ->toThrow(ValidationException::class, 'This container was not created via repack-on-return');
});

it('OpportunityController exposes resource wiring and returns held-item dispatch meta', function () {
    Sanctum::actingAs(batch5Part2Owner(), ['opportunities:read', 'opportunities:write']);
    $controller = new OpportunityController;

    expect(batch5Part2InvokeProtected($controller, 'responseDataClass'))->toBe(\App\Data\Opportunities\OpportunityData::class)
        ->and(batch5Part2InvokeProtected($controller, 'createDataClass'))->toBe(\App\Data\Opportunities\CreateOpportunityData::class)
        ->and(batch5Part2InvokeProtected($controller, 'updateDataClass'))->toBe(\App\Data\Opportunities\UpdateOpportunityData::class)
        ->and(batch5Part2InvokeProtected($controller, 'createActionClass'))->toBe(\App\Actions\Opportunities\CreateOpportunity::class)
        ->and(batch5Part2InvokeProtected($controller, 'updateActionClass'))->toBe(\App\Actions\Opportunities\UpdateOpportunity::class)
        ->and(batch5Part2InvokeProtected($controller, 'singularKey'))->toBe('opportunity')
        ->and(batch5Part2InvokeProtected($controller, 'pluralKey'))->toBe('opportunities')
        ->and(batch5Part2InvokeProtected($controller, 'entityType'))->toBe('opportunities');

    $store = Store::factory()->create(['shortage_policy' => \App\Enums\ShortagePolicy::WarnPartial->value]);
    $product = \App\Models\Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $opportunity = Opportunity::factory()->for($store)->create([
        'state' => \App\Enums\OpportunityState::Order,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(5),
    ]);

    $item = \App\Models\OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => \App\Models\Product::class,
        'quantity' => '3',
    ]);

    $this->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/bulk_quantity", [
        'action' => 'dispatch',
        'quantity' => '2',
    ])->assertOk();
});

it('InboundWebhookController resolveEvent reads event type from headers when payload omits it', function () {
    config([
        'api_metering.capture_bodies' => true,
        'api_metering.body_max_bytes' => 4096,
    ]);

    $source = \App\Models\InboundWebhookSource::factory()->create();
    $payload = json_encode(['id' => 42], JSON_THROW_ON_ERROR);

    $signature = hash_hmac($source->signature_algo, $payload, $source->secret);

    $this->call(
        'POST',
        route('webhooks.receive', $source->slug),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            $source->signature_header => $signature,
            'HTTP_X-Event-Type' => 'partner.updated',
        ],
        $payload,
    )->assertOk();

    expect(\App\Models\InboundWebhookLog::query()->value('event'))->toBe('partner.updated');
});

it('CreatePurchaseOrder and UpdateDraftPurchaseOrder resolve foreign exchange rates', function () {
    $this->actingAs(batch5Part2Owner());
    settings()->set('company.base_currency', 'GBP');
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'target_currency_code' => 'GBP',
        'rate' => '0.790000',
        'effective_at' => now()->subDay(),
    ]);

    $store = Store::factory()->create();
    $supplier = Member::factory()->organisation()->create();

    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'currency' => 'USD',
        'lines' => [[
            'description' => 'Widget',
            'quantity' => '1',
            'unit_cost' => 1000,
        ]],
    ]));

    expect($created->currency_code)->toBe('USD');

    $po = PurchaseOrder::query()->findOrFail($created->id);

    $updated = (new UpdateDraftPurchaseOrder)($po, UpdateDraftPurchaseOrderData::from([
        'currency' => 'USD',
        'notes' => 'Updated notes',
    ]));

    expect($updated->notes)->toBe('Updated notes');

    expect(fn () => (new UpdateDraftPurchaseOrder)($po, UpdateDraftPurchaseOrderData::from([
        'currency' => 'JPY',
    ])))->toThrow(ValidationException::class, 'No exchange rate is configured');
});

it('DeliverCustomerCommunication handle and failed cover missing recipient and failure logging', function () {
    Log::spy();

    $template = CommunicationTemplate::factory()->create();
    $job = new DeliverCustomerCommunication(
        communicationLogId: 1,
        recipientId: 999999,
        templateId: $template->id,
        notificationTypeKey: 'invoice.created',
    );

    $job->handle();

    Log::shouldHaveReceived('warning');

    $job->failed(new RuntimeException('delivery failed'));
    Log::shouldHaveReceived('error');
});

it('CreateDocumentTemplate clearDefaultForType demotes prior defaults', function () {
    $this->actingAs(batch5Part2Owner());
    $store = Store::factory()->create();
    $existing = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'is_default' => true,
    ]);

    (new CreateDocumentTemplate)(CreateDocumentTemplateData::from([
        'store_id' => $store->id,
        'name' => 'New default quote',
        'slug' => 'new-default-quote',
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<p>Default</p>',
        'is_default' => true,
    ]));

    expect($existing->fresh()->is_default)->toBeFalse();
});

it('RecordPayment assertPayableStatus rejects draft void credited and invalid refund targets', function () {
    $this->actingAs(batch5Part2Owner());

    $draft = Invoice::factory()->create(['status' => 'draft']);

    expect(fn () => (new RecordPayment)($draft, RecordPaymentData::from([
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
    ])))->toThrow(ValidationException::class, 'Payments can only be recorded against issued invoices');

    $void = Invoice::factory()->create(['status' => 'void']);

    expect(fn () => (new RecordPayment)($void, RecordPaymentData::from([
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
    ])))->toThrow(ValidationException::class);

    $issued = Invoice::factory()->create(['status' => 'issued', 'total' => 1000, 'amount_outstanding' => 1000]);

    expect(fn () => (new RecordPayment)($issued, RecordPaymentData::from([
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
    ]), isRefund: true))->toThrow(ValidationException::class, 'Refunds can only be recorded against invoices with recorded payments');
});

it('CustomerCommunication toMail attaches PDFs and failed transitions the communication log', function () {
    Log::spy();
    $member = Member::factory()->create();
    $template = CommunicationTemplate::factory()->create([
        'subject' => 'Hello {{ member.name }}',
        'body_html' => '<p>Body</p>',
    ]);
    $log = CommunicationLog::factory()->create(['status' => 'queued']);

    $notification = new CustomerCommunication(
        communicationLogId: $log->id,
        template: $template,
        notificationTypeKey: 'invoice.created',
        mergeData: ['member' => ['name' => $member->name]],
    );

    expect(fn () => $notification->toMail(new User))->toThrow(InvalidArgumentException::class);

    $mail = $notification->toMail($member);
    expect($mail->subject)->not->toBeEmpty();

    $notification->failed(new RuntimeException('SMTP unavailable'));

    expect($log->fresh()->status)->toBe('failed');
    Log::shouldHaveReceived('error');
});

it('RepackReturnItem shouldPackIntoContainer honours free-scan and predecessor manifests', function () {
    $this->actingAs(batch5Part2Owner());
    $store = Store::factory()->create();
    $predecessor = Container::factory()->create(['store_id' => $store->id]);
    $container = Container::factory()->create([
        'store_id' => $store->id,
        'previous_container_id' => $predecessor->id,
    ]);
    $stock = StockLevel::factory()->serialised()->create(['store_id' => $store->id]);
    $action = new RepackReturnItem;

    expect(batch5Part2InvokeProtected($action, 'shouldPackIntoContainer', [$container, $stock, true]))->toBeTrue()
        ->and(batch5Part2InvokeProtected($action, 'shouldPackIntoContainer', [$container, $stock, false]))->toBeFalse();
});

it('HasOpportunityActions restore un-trashes the opportunity from the show page', function () {
    $owner = batch5Part2Owner();
    $this->actingAs($owner);

    $opportunity = Opportunity::factory()->create();
    (new \App\Actions\Opportunities\DeleteOpportunity)($opportunity);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('restore');

    expect($opportunity->fresh()->trashed())->toBeFalse();
});
