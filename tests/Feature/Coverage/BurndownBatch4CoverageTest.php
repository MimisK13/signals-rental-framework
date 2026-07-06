<?php

/**
 * P4-CL4b burndown batch 4 — models, provider, queries, support, verb states (part 1).
 */

use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Enums\TaxResultType;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\ApiIdempotencyKey;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteTax;
use App\Models\CustomView;
use App\Models\DocumentTemplate;
use App\Models\InboundWebhookSource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\OpportunitySurchargeGroup;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\RequestLog;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\UsageDaily;
use App\Models\User;
use App\Models\UserViewPreference;
use App\Models\VirtualStockCostOverride;
use App\Models\VirtualStockIntake;
use App\Providers\AppServiceProvider;
use App\Queries\Api\UsageQuery;
use App\Services\Api\ApiEndpointCatalogue;
use App\Services\Documents\SandboxedBladeCompiler;
use App\Support\Api\MeteringBodyRedactor;
use App\Support\DateWindow;
use App\Verbs\States\OpportunityCostState;
use App\Verbs\States\OpportunityVersionState;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<int, mixed>  $args
 */
function batch4InvokeProtected(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function batch4Owner(): User
{
    return User::factory()->owner()->create();
}

it('ApiIdempotencyKey token relation and completion helpers', function () {
    $user = User::factory()->create();
    $token = $user->createToken('batch4-idempotency');

    $record = ApiIdempotencyKey::query()->create([
        'key' => 'batch4-key',
        'personal_access_token_id' => $token->accessToken->id,
        'request_fingerprint' => hash('sha256', 'fp'),
        'response_status' => null,
        'expires_at' => now()->addDay(),
    ]);

    expect($record->token?->id)->toBe($token->accessToken->id)
        ->and($record->isInFlight())->toBeTrue()
        ->and($record->isCompleted())->toBeFalse();

    $record->update(['response_status' => 201]);

    expect($record->fresh()->isCompleted())->toBeTrue()
        ->and($record->fresh()->isInFlight())->toBeFalse();
});

it('CreditNote creditAmount and scopeForAccessibleStores branches', function () {
    $creditNote = CreditNote::factory()->create(['total' => -5000]);

    expect($creditNote->creditAmount())->toBe(5000);

    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $invoice = Invoice::factory()->create(['store_id' => $store->id]);
    $scoped = CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'store_id' => $store->id,
        'total' => -1000,
    ]);

    $member = Member::factory()->create();
    $restricted = User::factory()->create(['member_id' => $member->id]);
    $restricted->givePermissionTo('invoices.view');
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);

    $this->actingAs($restricted);

    expect(CreditNote::query()->forAccessibleStores()->pluck('id'))->not->toContain($scoped->id);

    $this->actingAs(batch4Owner());

    expect(CreditNote::query()->forAccessibleStores()->pluck('id'))->toContain($scoped->id);

    auth()->logout();

    expect(CreditNote::query()->forAccessibleStores()->count())->toBe(CreditNote::count());
});

it('Invoice ensureDraft and effectiveOutstanding', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'draft',
        'total' => 10000,
        'amount_paid' => 3000,
        'credited_total' => 2000,
    ]);

    $invoice->ensureDraft();

    expect($invoice->effectiveOutstanding())->toBe(5000);

    $issued = Invoice::factory()->issued()->create();

    expect(fn () => $issued->ensureDraft())->toThrow(ValidationException::class);
});

it('InvoiceItem invoice and productTaxClass relations', function () {
    $invoice = Invoice::factory()->create();
    $taxClass = ProductTaxClass::factory()->create();
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_tax_class_id' => $taxClass->id,
    ]);

    expect($item->invoice?->is($invoice))->toBeTrue()
        ->and($item->productTaxClass?->is($taxClass))->toBeTrue();
});

it('Member primaryContactEmailAddress and broadcast routing', function () {
    $organisation = Member::factory()->organisation()->create();
    $contact = Member::factory()->contact()->create();
    $contact->emails()->create(['address' => 'billing@example.com', 'is_primary' => true]);
    MemberRelationship::factory()->primary()->create([
        'member_id' => $contact->id,
        'related_member_id' => $organisation->id,
    ]);

    expect($organisation->fresh()->primaryContactEmailAddress())->toBe('billing@example.com')
        ->and($organisation->routeNotificationForBroadcast())->toBe('member.'.$organisation->id);

    $loadedOrg = $organisation->fresh();
    $loadedOrg->load('contacts');

    expect($loadedOrg->primaryContactEmailAddress())->toBe('billing@example.com');

    $emptyOrg = Member::factory()->organisation()->create();

    expect($emptyOrg->primaryContactEmailAddress())->toBeNull();
});

it('OpportunitySurchargeGroup opportunity and surchargeGroup relations', function () {
    $opportunity = Opportunity::factory()->create();
    $group = SurchargeGroup::factory()->create();
    $link = OpportunitySurchargeGroup::factory()->create([
        'opportunity_id' => $opportunity->id,
        'surcharge_group_id' => $group->id,
    ]);

    expect($link->opportunity?->is($opportunity))->toBeTrue()
        ->and($link->surchargeGroup?->is($group))->toBeTrue();
});

it('UsageDaily token and inboundWebhookSource relations', function () {
    $user = User::factory()->create();
    $token = $user->createToken('usage');
    $source = InboundWebhookSource::factory()->create();

    $row = UsageDaily::factory()->create([
        'personal_access_token_id' => $token->accessToken->id,
        'inbound_webhook_source_id' => $source->id,
    ]);

    expect($row->token?->id)->toBe($token->accessToken->id)
        ->and($row->inboundWebhookSource?->is($source))->toBeTrue();
});

it('UserViewPreference user and customView relations', function () {
    $user = User::factory()->create();
    $view = CustomView::factory()->create(['user_id' => $user->id]);

    $preference = UserViewPreference::create([
        'user_id' => $user->id,
        'entity_type' => 'opportunities',
        'custom_view_id' => $view->id,
    ]);

    expect($preference->user?->is($user))->toBeTrue()
        ->and($preference->customView?->is($view))->toBeTrue();
});

it('AppServiceProvider register binds SandboxedBladeCompiler and route clear listener no-ops', function () {
    $cachePath = storage_path('framework/testing/sandbox-blade-'.uniqid());
    config(['documents.sandbox_cache_path' => $cachePath]);

    expect(app(SandboxedBladeCompiler::class))->toBeInstanceOf(SandboxedBladeCompiler::class)
        ->and(is_dir($cachePath))->toBeTrue();

    @rmdir($cachePath);

    $catalogue = app(ApiEndpointCatalogue::class);
    $catalogue->all();

    $memory = new ReflectionProperty(ApiEndpointCatalogue::class, 'memory');
    $memory->setAccessible(true);
    expect($memory->getValue($catalogue))->not->toBeNull();

    event(new CommandFinished(
        'config:clear',
        new ArrayInput([]),
        new BufferedOutput,
        0,
    ));

    expect($memory->getValue($catalogue))->not->toBeNull();

    $provider = new AppServiceProvider(app());
    batch4InvokeProtected($provider, 'registerApiCatalogueCacheInvalidation');

    event(new CommandFinished(
        'route:clear',
        new ArrayInput([]),
        new BufferedOutput,
        0,
    ));

    expect($memory->getValue($catalogue))->toBeNull();
});

it('UsageQuery rejects unknown dimensions and normalizes null day keys', function () {
    $query = app(UsageQuery::class);
    $window = DateWindow::fromRequest('7d');

    expect(fn () => $query($window, 'not-a-dimension'))
        ->toThrow(InvalidArgumentException::class);

    UsageDaily::factory()->create([
        'date' => now()->subDay()->toDateString(),
        'personal_access_token_id' => null,
        'key' => 'api.v1.usage.index',
        'status_class' => '2xx',
        'request_count' => 1,
        'weighted_units' => 1,
    ]);

    $result = $query($window, 'token');

    expect(collect($result->rows)->firstWhere('key', 'null'))->not->toBeNull();
});

it('OpportunityCostState costType and transactionType accessors', function () {
    $state = new OpportunityCostState;
    $state->cost_type = OpportunityCostType::Delivery->value;
    $state->transaction_type = LineItemTransactionType::Service->value;

    expect($state->costType())->toBe(OpportunityCostType::Delivery)
        ->and($state->transactionType())->toBe(LineItemTransactionType::Service);
});

it('OpportunityVersionState status and versionType accessors', function () {
    $state = new OpportunityVersionState;
    $state->status = VersionStatus::Draft->value;
    $state->version_type = VersionType::Revision->value;

    expect($state->status())->toBe(VersionStatus::Draft)
        ->and($state->versionType())->toBe(VersionType::Revision);
});

it('InvoicePayment moneyFormattingCurrency createdBy and accessible store scope', function () {
    $invoice = Invoice::factory()->create(['currency_code' => 'EUR']);
    $creator = User::factory()->create();
    $payment = InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'created_by' => $creator->id,
    ]);

    expect($payment->load('invoice')->formatMoneyCost('amount'))->toBeString()
        ->and($payment->createdBy?->is($creator))->toBeTrue();

    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $scopedInvoice = Invoice::factory()->create(['store_id' => $store->id]);
    InvoicePayment::factory()->create(['invoice_id' => $scopedInvoice->id]);

    $member = Member::factory()->create();
    $restricted = User::factory()->create(['member_id' => $member->id]);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);

    $this->actingAs($restricted);

    expect(InvoicePayment::query()->forAccessibleStores()->count())->toBe(0);

    auth()->logout();

    expect(InvoicePayment::query()->forAccessibleStores()->count())->toBe(InvoicePayment::count());
});

it('RequestLog scopes filter token status and created range', function () {
    $user = User::factory()->create();
    $token = PersonalAccessToken::findToken($user->createToken('log')->plainTextToken);

    $match = RequestLog::factory()->create([
        'personal_access_token_id' => $token->id,
        'status_code' => 404,
        'created_at' => now()->subDays(2),
    ]);

    RequestLog::factory()->create([
        'personal_access_token_id' => null,
        'status_code' => 200,
        'created_at' => now()->subDays(10),
    ]);

    $from = now()->subDays(3)->toDateTimeString();
    $to = now()->toDateTimeString();

    expect(
        RequestLog::query()
            ->forToken($token->id)
            ->forStatus(404)
            ->createdBetween($from, $to)
            ->pluck('id')
    )->toContain($match->id);
});

it('HasCostVisibility trait methods on Product and OpportunityCost', function () {
    $product = Product::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('costs.view');

    expect($product->canViewCosts($viewer))->toBeTrue()
        ->and($product->costColumns())->toContain('purchase_price')
        ->and($product->withoutCostColumns(['purchase_price' => 999], $viewer))->toHaveKey('purchase_price');

    $denied = User::factory()->create();

    expect($product->withoutCostColumns(['purchase_price' => 999], $denied))->not->toHaveKey('purchase_price');

    $cost = OpportunityCost::factory()->create();

    expect($cost->canViewCosts($viewer))->toBeTrue()
        ->and($cost->costColumns())->not->toBeEmpty();
});

it('VirtualStockCostOverride relations resolve', function () {
    $intake = createTestVirtualStockIntake();
    $opportunity = Opportunity::factory()->create();
    $creator = User::factory()->create();

    $override = VirtualStockCostOverride::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'opportunity_id' => $opportunity->id,
        'created_by' => $creator->id,
    ]);

    expect($override->virtualStockIntake?->is($intake))->toBeTrue()
        ->and($override->opportunity?->is($opportunity))->toBeTrue()
        ->and($override->creator?->is($creator))->toBeTrue();
});

it('CreditNoteTax CreditNoteItem and InvoiceTax model helpers', function () {
    $creditNote = CreditNote::factory()->create(['currency_code' => 'USD']);
    $creditTax = CreditNoteTax::factory()->create(['credit_note_id' => $creditNote->id]);

    expect($creditTax->load('creditNote')->formatMoneyCost('tax_amount'))->toBeString()
        ->and($creditTax->creditNote?->is($creditNote))->toBeTrue();

    $invoice = Invoice::factory()->create(['currency_code' => 'EUR']);
    $invoiceItem = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
    $creditItem = CreditNoteItem::factory()->create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $invoiceItem->id,
        'tax_treatment' => 'standard',
    ]);

    expect($creditItem->load('creditNote')->formatMoneyCost('total'))->toBeString()
        ->and($creditItem->taxTreatmentEnum())->toBe(TaxResultType::Standard)
        ->and($creditItem->creditNote?->is($creditNote))->toBeTrue()
        ->and($creditItem->invoiceItem?->is($invoiceItem))->toBeTrue();

    $invoiceTax = InvoiceTax::factory()->create(['invoice_id' => $invoice->id]);

    expect($invoiceTax->load('invoice')->formatMoneyCost('tax_amount'))->toBeString()
        ->and($invoiceTax->invoice?->is($invoice))->toBeTrue();
});

it('PurchaseOrder and VirtualStockIntake ensureDraft and scopeOfStatus', function () {
    $draftPo = PurchaseOrder::factory()->create(['status' => 'draft']);
    $draftPo->ensureDraft();

    expect(PurchaseOrder::query()->ofStatus('draft')->whereKey($draftPo->id)->exists())->toBeTrue();

    $sentPo = PurchaseOrder::factory()->create(['status' => 'sent']);

    expect(fn () => $sentPo->ensureDraft())->toThrow(ValidationException::class);

    $draftIntake = createTestVirtualStockIntake(['status' => 'draft']);
    $draftIntake->ensureDraft();

    expect(VirtualStockIntake::query()->ofStatus('draft')->whereKey($draftIntake->id)->exists())->toBeTrue();

    $confirmed = createTestVirtualStockIntake(['status' => 'draft']);
    app(ConfirmVirtualStockIntake::class)($confirmed);

    expect(fn () => $confirmed->fresh()->ensureDraft())->toThrow(ValidationException::class);
});

it('DocumentTemplate resolverType fallback and scopeForStore', function () {
    $store = Store::factory()->create();
    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'custom_type',
        'model_type' => Opportunity::class,
    ]);

    expect($template->resolverType())->toBe('opportunity')
        ->and(DocumentTemplate::query()->forStore($store->id)->whereKey($template->id)->exists())->toBeTrue();

    $memberTemplate = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'custom_statement',
        'model_type' => Member::class,
    ]);
    $invoiceTemplate = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'custom_invoice',
        'model_type' => Invoice::class,
    ]);
    $creditTemplate = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'custom_credit',
        'model_type' => CreditNote::class,
    ]);

    expect($memberTemplate->resolverType())->toBe('statement')
        ->and($invoiceTemplate->resolverType())->toBe('invoice')
        ->and($creditTemplate->resolverType())->toBe('credit_note');

    $unsupported = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'custom',
        'model_type' => Store::class,
    ]);

    expect(fn () => $unsupported->resolverType())->toThrow(InvalidArgumentException::class);
});

it('VirtualStockIntakeData private reference helpers return null for missing models', function () {
    $reference = new ReflectionMethod(VirtualStockIntakeData::class, 'reference');
    $reference->setAccessible(true);
    $referencePo = new ReflectionMethod(VirtualStockIntakeData::class, 'referencePo');
    $referencePo->setAccessible(true);
    $referencePoItem = new ReflectionMethod(VirtualStockIntakeData::class, 'referencePoItem');
    $referencePoItem->setAccessible(true);

    expect($reference->invoke(null, null))->toBeNull()
        ->and($referencePo->invoke(null, null))->toBeNull()
        ->and($referencePoItem->invoke(null, null))->toBeNull();
});

it('OpportunityItemSurcharge resolved helpers and productTaxClass relation', function () {
    $surcharge = Surcharge::factory()->create([
        'name' => 'Fuel levy',
        'percent_value' => '12.50',
        'fixed_value_minor' => null,
    ]);
    $taxClass = ProductTaxClass::factory()->create();
    $item = OpportunityItem::factory()->create();
    $row = OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $item->id,
        'surcharge_id' => $surcharge->id,
        'name' => null,
        'surcharge_type' => null,
        'percent_value' => null,
        'fixed_value_minor' => null,
        'product_tax_class_id' => $taxClass->id,
    ]);

    expect($row->resolvedName())->toBe('Fuel levy')
        ->and($row->resolvedSurchargeType())->toBe('percent')
        ->and($row->resolvedPercentValue())->toBe('12.50')
        ->and($row->resolvedFixedValueMinor())->toBeNull()
        ->and($row->load('opportunityItem.opportunity')->formatMoneyCost('amount'))->toBeString()
        ->and($row->productTaxClass?->is($taxClass))->toBeTrue();

    $fixedRow = OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $item->id,
        'surcharge_id' => null,
        'name' => 'Manual',
        'surcharge_type' => 'fixed',
        'percent_value' => null,
        'fixed_value_minor' => 500,
    ]);

    expect($fixedRow->resolvedSurchargeType())->toBe('fixed')
        ->and($fixedRow->resolvedFixedValueMinor())->toBe(500);

    $otherItem = OpportunityItem::factory()->create();
    $loadedRow = OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $otherItem->id,
        'surcharge_id' => $surcharge->id,
        'name' => null,
        'surcharge_type' => null,
        'percent_value' => null,
        'fixed_value_minor' => null,
    ]);
    $loadedRow->setRelation('surcharge', $surcharge);

    expect($loadedRow->resolvedName())->toBe('Fuel levy')
        ->and($loadedRow->resolvedSurchargeType())->toBe('percent')
        ->and($loadedRow->resolvedPercentValue())->toBe('12.50')
        ->and($loadedRow->resolvedFixedValueMinor())->toBeNull();
});

it('VirtualStockIntakeData reference helpers via fromModel and null purchase order links', function () {
    $intake = createTestVirtualStockIntake();
    $intake->load(['product', 'store']);

    $withoutPo = VirtualStockIntakeData::fromModel($intake);

    expect($withoutPo->product)->not->toBeNull()
        ->and($withoutPo->store)->not->toBeNull();

    $po = createTestPurchaseOrder([
        'po_type' => 'sub_hire',
        'lines' => [[
            'product_id' => $intake->product_id,
            'description' => 'Linked line',
            'quantity' => '1',
            'unit_cost' => 1000,
        ]],
    ]);
    $poItem = $po->items()->firstOrFail();
    $linked = createTestVirtualStockIntake([
        'purchase_order_id' => $po->id,
        'purchase_order_item_id' => $poItem->id,
    ]);
    $linked->load(['purchaseOrder', 'purchaseOrderItem']);

    $withPo = VirtualStockIntakeData::fromModel($linked);

    expect($withPo->purchase_order)->not->toBeNull()
        ->and($withPo->purchase_order_item)->not->toBeNull();
});

it('MeteringBodyRedactor redactAndTruncate plain text truncation and redaction branches', function () {
    $redactor = new MeteringBodyRedactor;

    expect($redactor->redactAndTruncate(null, ['password'], 100))->toBeNull()
        ->and($redactor->redactAndTruncate('', ['password'], 100))->toBeNull();

    $plain = '{"password":"secret-value","note":"ok"}';
    $redacted = $redactor->redactAndTruncate($plain, ['password'], 100);

    expect($redacted)->toContain('[REDACTED]')
        ->and($redacted)->not->toContain('secret-value');

    $longBody = str_repeat('x', 50);
    $truncated = $redactor->redactAndTruncate($longBody, [], 10);

    expect(strlen($truncated))->toBeLessThanOrEqual(10);
});
