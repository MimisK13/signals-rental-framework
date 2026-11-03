<?php

/**
 * Coverage burn-up batch 5 — services, policies, and support classes.
 */

use App\Domain\Availability\Shortages\Enums\ShortageResolutionType;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageResolutionItem;
use App\Domain\Availability\Shortages\Services\CostApportionmentContext;
use App\Domain\Availability\Shortages\Services\Resolvers\SubHireResolver;
use App\Domain\Availability\Shortages\Services\Strategies\ProportionalQuantityDurationCostApportionmentStrategy;
use App\Domain\Availability\Shortages\ValueObjects\ResolutionOption;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Policies\CommunicationTemplatePolicy;
use App\Domain\Communications\Services\CustomerCommunicationAttachmentResolver;
use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Exceptions\DocumentRenderException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Policies\DocumentTemplatePolicy;
use App\Domain\Documents\Services\ReceiptDataResolver;
use App\Domain\Documents\Services\SandboxedLoopEnvironment;
use App\Domain\Documents\ValueObjects\DocumentRenderResult;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Members\Models\MemberStore;
use App\Domain\Notifications\Database\Seeders\NotificationTypeSeeder;
use App\Domain\Notifications\Enums\NotificationAudience;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Services\NotificationSettingsCatalog;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\PurchaseOrders\Services\PurchaseOrderNumberGenerator;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stores\Models\Store;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\VirtualStockIntakeProfitAndLossBuilder;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\mock;

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
function burnup5Invoke(object|string $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(is_string($object) ? null : $object, ...$args);
}

/**
 * @param  array<int, string>  $permissions
 */
function burnup5PolicyUser(array $permissions): User
{
    $user = User::factory()->create(['is_owner' => false]);
    $user->givePermissionTo($permissions);

    return $user;
}

function burnup5GrantStore(User $user, Store $store): void
{
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
}

it('CostApportionmentContext opportunityWindow returns null for unknown opportunities', function () {
    $intake = VirtualStockIntake::factory()->confirmed()->create();

    $context = new CostApportionmentContext(
        intake: $intake,
        quantitiesByOpportunity: [42 => 1],
        opportunities: [],
        primaryOpportunityId: 42,
        totalCostMinor: 500,
    );

    expect($context->opportunityWindow(42))->toBeNull();
});

it('ProportionalQuantityDurationCostApportionmentStrategy apportions when rental windows overlap', function () {
    $intake = VirtualStockIntake::factory()->confirmed()->create([
        'available_from' => Carbon::parse('2026-08-01T00:00:00Z'),
        'available_until' => Carbon::parse('2026-08-06T00:00:00Z'),
    ]);
    $opportunity = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-02T00:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T00:00:00Z'),
    ]);

    $context = new CostApportionmentContext(
        intake: $intake,
        quantitiesByOpportunity: [$opportunity->id => 2],
        opportunities: [$opportunity->id => $opportunity],
        primaryOpportunityId: $opportunity->id,
        totalCostMinor: 1000,
    );

    $allocations = app(ProportionalQuantityDurationCostApportionmentStrategy::class)->calculate($context);

    expect($allocations[$opportunity->id])->toBeGreaterThan(0);
});

it('PurchaseOrderNumberGenerator nextOrCreate provisions a missing sequence', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);

    DocumentSequence::query()
        ->where('store_id', $store->id)
        ->where('document_type', PurchaseOrderNumberGenerator::DOCUMENT_TYPE)
        ->delete();

    $number = app(PurchaseOrderNumberGenerator::class)->nextOrCreate($store->id);

    expect($number)->not->toBeEmpty()
        ->and(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', PurchaseOrderNumberGenerator::DOCUMENT_TYPE)
            ->exists())->toBeTrue();
});

it('CommunicationTemplatePolicy update and delete honour store access and system templates', function () {
    $policy = new CommunicationTemplatePolicy;
    $store = Store::factory()->create();
    $template = CommunicationTemplate::factory()->for($store)->create(['is_system' => false]);
    $denied = burnup5PolicyUser(['notifications.manage']);
    $allowed = burnup5PolicyUser(['notifications.manage']);
    burnup5GrantStore($allowed, $store);

    expect($policy->update($denied, $template))->toBeFalse()
        ->and($policy->update($allowed, $template))->toBeTrue()
        ->and($policy->delete($allowed, $template))->toBeTrue();

    $viewer = burnup5PolicyUser([]);
    expect($policy->update($viewer, $template))->toBeFalse();
});

it('DocumentTemplatePolicy view and delete honour store access', function () {
    $policy = new DocumentTemplatePolicy;
    $store = Store::factory()->create();
    $template = DocumentTemplate::factory()->for($store)->create();
    $denied = burnup5PolicyUser(['document_templates.view', 'document_templates.edit']);
    $allowed = burnup5PolicyUser(['document_templates.view', 'document_templates.edit']);
    burnup5GrantStore($allowed, $store);

    expect($policy->view($denied, $template))->toBeFalse()
        ->and($policy->view($allowed, $template))->toBeTrue()
        ->and($policy->delete($denied, $template))->toBeFalse()
        ->and($policy->delete($allowed, $template))->toBeTrue();
});

it('ReceiptDataResolver resolve rejects non-payments and formatDate handles null dates', function () {
    $resolver = app(ReceiptDataResolver::class);

    expect(fn () => $resolver->resolve(Invoice::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'InvoicePayment');

    expect(burnup5Invoke($resolver, 'formatDate', [null, 'UTC']))->toBeNull();
});

it('ReceiptDataResolver resolve formats payment and invoice data', function () {
    settings()->set('company.date_format_php', 'Y-m-d', 'string');
    $invoice = Invoice::factory()->issued()->create();
    $payment = InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'paid_at' => Carbon::parse('2026-06-15T12:00:00Z'),
    ]);

    $loaded = app(ReceiptDataResolver::class)->loadSource($payment->id);
    $data = app(ReceiptDataResolver::class)->resolve($loaded);

    expect($data['payment']['amount'])->toBeString()
        ->and($data['payment']['received_at'])->toBe('2026-06-15')
        ->and($data['invoice']['number'])->toBe($invoice->number);
});

it('SandboxedLoopEnvironment prepareLoopData and addLoop cover capped arrays and nested parents', function () {
    config(['documents.max_loop_iterations' => 2]);

    expect(SandboxedLoopEnvironment::prepareLoopData([1, 2, 3, 4]))->toBe([1, 2])
        ->and(fn () => SandboxedLoopEnvironment::prepareLoopData(LazyCollection::make([1, 2, 3])))
        ->toThrow(DocumentRenderException::class, 'maximum of 2 iterations');

    $env = new SandboxedLoopEnvironment;
    $env->addLoop([1, 2]);
    $env->addLoop([3]);
    $lastLoop = $env->getLastLoop();
    expect($lastLoop)->not->toBeNull();
    expect(Arr::get((array) $lastLoop, 'depth'))->toBe(2);
    expect(Arr::get((array) $lastLoop, 'parent'))->not->toBeNull();
});

it('NotificationSettingsCatalog normalizeChannels and audienceConfig split-audience paths', function () {
    $this->seed(NotificationTypeSeeder::class);
    $catalog = app(NotificationSettingsCatalog::class);
    $type = NotificationType::query()->firstOrFail();
    $type->update(['audience' => NotificationAudience::Both, 'default_channels' => ['email', 'sms']]);

    expect($catalog->normalizeChannels(['in_app', 'email', 'sms']))
        ->toBe(['database', 'mail', 'sms']);

    $setting = NotificationSetting::factory()->create([
        'notification_type_id' => $type->id,
        'channels' => ['database'],
        'channel_config' => [
            'audiences' => [
                'internal' => ['channels' => ['mail']],
            ],
        ],
    ]);

    expect($catalog->effectiveChannelsFor($type, $setting, 'internal'))->toBe(['mail'])
        ->and($catalog->effectiveChannelsFor($type, null, 'internal'))->toContain('mail')
        ->and($catalog->effectiveChannelsFor($type, NotificationSetting::factory()->make([
            'channel_config' => ['audiences' => 'invalid'],
        ]), 'internal'))->toContain('mail');
});

it('VirtualStockIntakeProfitAndLossBuilder build and allocatedLineRevenueMinor cover cost and zero-quantity lines', function () {
    $intake = VirtualStockIntake::factory()->confirmed()->create(['quantity' => 1, 'unit_cost' => 500]);
    $opportunity = Opportunity::factory()->create(['subject' => 'Batch 5 P&L']);
    $item = OpportunityItem::factory()->for($opportunity)->create(['total' => 0, 'quantity' => '0']);
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

    $built = app(VirtualStockIntakeProfitAndLossBuilder::class)->build($intake, 'even_split', 'Even split');

    expect($built['strategy_label'])->toBe('Even split')
        ->and($built['rows'][0]['opportunity_subject'])->toBe('Batch 5 P&L')
        ->and($built['rows'][0]['line_items'][0]['revenue_minor'])->toBe(0);
});

it('CustomerCommunicationAttachmentResolver resolve and isAllowedByTemplateConfig cover allow-list and success', function () {
    $documentTemplate = DocumentTemplate::factory()->create();
    $template = CommunicationTemplate::factory()->create([
        'attachments_config' => ['document_templates' => [$documentTemplate->id]],
    ]);
    $document = Document::factory()->create([
        'document_template_id' => $documentTemplate->id,
        'store_id' => $documentTemplate->store_id,
        'document_number' => 'DOC-5',
        'rendered_data' => ['invoice' => ['number' => 'INV-1']],
    ]);

    mock(DocumentRenderer::class)
        ->shouldReceive('renderFromSnapshot')
        ->once()
        ->andReturn(new DocumentRenderResult(
            success: true,
            html: '<p>ok</p>',
            pdfBinary: '%PDF-test',
        ));

    $attachment = app(CustomerCommunicationAttachmentResolver::class)->resolve($document, $template);

    expect($attachment)->not->toBeNull()
        ->and($attachment['filename'])->toBe('DOC-5.pdf')
        ->and($attachment['content'])->toBe('%PDF-test');

    $blocked = CommunicationTemplate::factory()->create([
        'attachments_config' => ['document_template_ids' => [999999]],
    ]);

    expect(app(CustomerCommunicationAttachmentResolver::class)->resolve($document, $blocked))->toBeNull();
});

it('SubHireResolver executeSubHire rejects options missing supplier metadata', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $resolver = app(SubHireResolver::class);
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
        startsAt: Carbon::now(),
        endsAt: Carbon::now()->addDay(),
        isCritical: false,
    );

    expect(fn () => $execute->invoke(
        $resolver,
        [1 => 1],
        $product->id,
        $store->id,
        1,
        Carbon::now(),
        Carbon::now()->addDay(),
        new ResolutionOption(
            resolverKey: 'subhire',
            type: ShortageResolutionType::Subhire,
            label: 'Sub-hire',
            description: 'Missing metadata',
            quantityResolved: 1,
            isPartial: false,
            autoExecutable: false,
            metadata: [],
        ),
        $shortage,
    ))->toThrow(ValidationException::class, 'missing supplier provenance');
});
