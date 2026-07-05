<?php

/**
 * Coverage burn-up batch 3 — part 2 (data, HTTP, jobs, listeners, Livewire, models, queries, services).
 */

use App\Actions\Notifications\LogCommunication;
use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\InvoiceData;
use App\Data\Notifications\NotificationContextData;
use App\Data\Notifications\TestChannelProviderData;
use App\Data\Products\ProductData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\MeteringChannel;
use App\Enums\TaxResultType;
use App\Http\Controllers\Web\SearchController;
use App\Jobs\ExportActionLog;
use App\Listeners\RecordCustomerCommunicationDelivery;
use App\Livewire\Components\DataTable;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Models\Accessory;
use App\Models\ActionLog;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\UsageDaily;
use App\Models\User;
use App\Notifications\CustomerCommunication;
use App\Queries\Api\UsageQuery;
use App\Services\Containers\RepackOnReturnEligibility;
use App\Services\Documents\DocumentRenderPipeline;
use App\Services\Documents\InvoiceDataResolver;
use App\Services\Documents\TemplateBladeRenderer;
use App\Services\Invoices\InvoiceTotalsCalculator;
use App\Services\Notifications\Drivers\DatabaseDriver;
use App\Services\Notifications\RecipientResolvers\ContactRecipientResolver;
use App\Services\Pricing\Predicates\ProductGroupIdsPredicate;
use App\Support\DateWindow;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function batch3Part2Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

it('InvoiceData reference maps member names through lazy includes', function () {
    $member = Member::factory()->create(['name' => 'Billing Org Ltd']);
    $invoice = Invoice::factory()->create(['member_id' => $member->id]);

    $reference = InvoiceData::fromModel($invoice->fresh()->load('member'))
        ->include('member')
        ->toArray()['member'];

    expect($reference['name'])->toBe('Billing Org Ltd');
});

it('ProductData fromModel maps accessories without loaded accessory products and icon URLs', function () {
    $product = Product::factory()->rental()->bulk()->create([
        'icon_url' => 'icons/widget.png',
        'icon_thumb_url' => 'icons/widget-thumb.png',
    ]);
    $accessoryProduct = Product::factory()->create(['name' => 'Hidden accessory']);
    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
        'quantity' => 2,
    ]);

    $output = ProductData::fromModel($product->fresh()->load('accessories'))->toArray();

    expect($output['accessories'][0]['related_name'])->toBe('')
        ->and($output['icon']['url'])->toBe('icons/widget.png')
        ->and($output['icon']['thumb_url'])->toBe('icons/widget-thumb.png');
});

it('MemberNotificationPreferenceController hides members outside the actor store scope', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $manager = User::factory()->create(['is_owner' => false]);
    $manager->givePermissionTo(['members.view']);
    MemberStore::query()->create(['member_id' => $manager->member_id, 'store_id' => $storeA->id]);

    $targetMember = Member::factory()->contact()->create();
    User::factory()->create(['member_id' => $targetMember->id]);
    MemberStore::query()->create(['member_id' => $targetMember->id, 'store_id' => $storeB->id]);

    $this->actingAs($manager);

    $this->getJson("/api/v1/members/{$targetMember->id}/notification_preferences")
        ->assertNotFound();
});

it('MemberNotificationPreferenceController allows members without store links', function () {
    $owner = User::factory()->owner()->create();
    $member = Member::factory()->contact()->create();
    User::factory()->create(['member_id' => $member->id]);

    $this->actingAs($owner);

    $this->getJson("/api/v1/members/{$member->id}/notification_preferences")
        ->assertOk()
        ->assertJsonStructure(['notification_preferences']);
});

it('SearchController iconUrl resolves signed URLs when a thumbnail path exists', function () {
    Storage::fake('public');
    Storage::disk('public')->put('icons/search.png', 'bytes');

    $controller = app(SearchController::class);
    $url = batch3Part2Invoke($controller, 'iconUrl', ['icons/search.png']);

    expect($url)->toBeString()->not->toBeEmpty();
});

it('ExportActionLog writes System for action logs without a linked user', function () {
    Storage::fake();

    ActionLog::factory()->create([
        'user_id' => null,
        'action' => 'system.event',
        'auditable_type' => Store::class,
        'auditable_id' => Store::factory()->create()->id,
    ]);

    $job = new ExportActionLog(userId: 77);
    $job->handle();

    $filename = Cache::get('action-log-export:77');
    $csv = Storage::get($filename);

    expect($csv)->toContain('System');
});

it('RecordCustomerCommunicationDelivery transitions customer communication logs on delivery', function () {
    $log = CommunicationLog::factory()->create(['status' => 'queued']);
    $template = CommunicationTemplate::factory()->create([
        'subject' => 'Invoice issued',
        'body' => 'Please find your invoice attached.',
    ]);
    $notification = new CustomerCommunication(
        communicationLogId: $log->id,
        template: $template,
        notificationTypeKey: 'invoice.created',
        mergeData: ['member' => ['name' => 'Customer']],
    );

    app(RecordCustomerCommunicationDelivery::class)->handle(new NotificationSent(
        notifiable: Member::factory()->create(),
        notification: $notification,
        channel: 'mail',
    ));

    expect($log->fresh()->status)->toBe('sent');
});

it('RecordCustomerCommunicationDelivery logs transition failures without rethrowing', function () {
    Log::spy();
    $this->mock(LogCommunication::class, function ($mock): void {
        $mock->shouldReceive('transition')->andThrow(new RuntimeException('transition failed'));
    });

    $template = CommunicationTemplate::factory()->create();
    $notification = new CustomerCommunication(
        communicationLogId: 999999,
        template: $template,
        notificationTypeKey: 'invoice.created',
        mergeData: [],
    );

    app(RecordCustomerCommunicationDelivery::class)->handle(new NotificationSent(
        notifiable: Member::factory()->create(),
        notification: $notification,
        channel: 'mail',
    ));

    Log::shouldHaveReceived('warning');
});

it('DataTable exportCsv prepends an ID column when it is not already visible', function () {
    $owner = User::factory()->owner()->create();

    /** @var DataTable $instance */
    $instance = Livewire::actingAs($owner)->test(DataTable::class, [
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
        ],
        'model' => Member::class,
    ])->instance();

    Member::factory()->create(['name' => 'Export target']);

    $response = $instance->exportCsv();
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($csv)->toContain('ID,Name');
});

it('LoadsCustomFieldValues maps values and warns on orphaned custom field rows', function () {
    Log::spy();

    $field = CustomField::factory()->forModule('Member')->string()->create([
        'name' => 'po_reference',
    ]);
    $member = Member::factory()->create();
    $validValue = CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => 'Member',
        'entity_id' => $member->id,
        'value_string' => 'PO-123',
    ]);
    $validValue->load('customField');

    $orphanValue = new CustomFieldValue([
        'custom_field_id' => 999999,
        'entity_type' => 'Member',
        'entity_id' => $member->id,
        'value_string' => 'orphan',
    ]);
    $orphanValue->setRelation('customField', null);

    $member = Mockery::mock($member)->makePartial();
    $member->shouldReceive('load')
        ->once()
        ->with('customFieldValues.customField')
        ->andReturnUsing(function () use ($member, $validValue, $orphanValue) {
            $member->setRelation('customFieldValues', collect([$validValue, $orphanValue]));

            return $member;
        });

    $double = new class
    {
        use LoadsCustomFieldValues;

        /** @return array<string, mixed> */
        public function load(Member $member): array
        {
            return $this->loadCustomFieldValuesFrom($member);
        }
    };

    $values = (new $double)->load($member);

    expect($values)->toBe(['po_reference' => 'PO-123']);
    Log::shouldHaveReceived('warning');
});

it('Opportunity remainingInvoiceQuantitiesByItem loads items when the relation is not eager loaded', function () {
    $opportunity = Opportunity::factory()->order()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'quantity' => '3.00',
        'invoiced_quantity' => '1.00',
        'is_optional' => false,
    ]);

    $remaining = $opportunity->fresh()->remainingInvoiceQuantitiesByItem();

    expect($remaining[$item->id])->toBe('2.00');
});

it('UsageQuery normalizeKey formats null day keys and ISO date strings', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 1,
        'weighted_units' => 1,
    ]);

    $query = app(UsageQuery::class);
    $window = DateWindow::fromRequest('custom', '2026-07-01', '2026-07-01');
    $result = $query($window, dimension: 'day');

    expect($result->rows[0]['key'])->toBe('2026-07-01');

    $normalizer = batch3Part2Invoke($query, 'normalizeKey', [null]);
    expect($normalizer)->toBe('null');
});

it('RepackOnReturnEligibility rejects kit housings that are not transport mode', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Kit,
        'container_repack_on_return' => true,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    expect(app(RepackOnReturnEligibility::class)->isEligibleHousing($housing))->toBeFalse();
});

it('DocumentRenderPipeline renderWithData sanitises unexpected renderer failures', function () {
    $this->mock(TemplateBladeRenderer::class, function ($mock): void {
        $mock->shouldReceive('render')->andThrow(new RuntimeException('renderer exploded'));
    });

    $template = DocumentTemplate::factory()->create();
    $result = app(DocumentRenderPipeline::class)->renderFromSnapshot(
        $template,
        ['invoice' => ['number' => 'INV-1']],
    );

    expect($result->success)->toBeFalse()
        ->and($result->error)->not->toBeEmpty();
});

it('InvoiceDataResolver resolveTaxGroup falls back to related tax rate metadata', function () {
    $invoice = Invoice::factory()->issued()->create();
    $taxRate = TaxRate::factory()->create(['name' => 'Reduced', 'rate' => '5.00']);
    InvoiceTax::factory()->create([
        'invoice_id' => $invoice->id,
        'tax_rate_id' => $taxRate->id,
        'tax_rate_name' => null,
        'rate_percentage' => null,
        'result_type' => TaxResultType::ZeroRated->value,
        'legal_text' => 'Reduced rate applied',
    ]);

    $resolved = app(InvoiceDataResolver::class)->resolve($invoice->fresh()->load(['taxes.taxRate', 'items', 'member', 'store']));
    $group = $resolved['tax_summary']['groups'][0];

    expect($group['tax_rate_name'])->toBe('Reduced')
        ->and($group['rate_percentage'])->toBe('5.0000')
        ->and($group['result_type'])->toBe(TaxResultType::ZeroRated->label());
});

it('InvoiceTotalsCalculator distributeDealHeadlineTax handles empty and zero-weight groups', function () {
    $calculator = app(InvoiceTotalsCalculator::class);

    expect(batch3Part2Invoke($calculator, 'distributeDealHeadlineTax', [100, []]))->toBe([]);

    $zeroWeightLines = [
        new FrozenInvoiceLineData(
            invoice_item_id: 0,
            line_key: 'zero',
            source_type: 'item',
            description: 'Zero',
            quantity: '1',
            unit_price: 0,
            discount: 0,
            net_total: 0,
            tax: 0,
            product_tax_class_id: 1,
            tax_treatment: TaxResultType::Standard->value,
        ),
    ];

    expect(batch3Part2Invoke($calculator, 'distributeDealHeadlineTax', [50, $zeroWeightLines]))
        ->toBe(['zero' => 0]);
});

it('DatabaseDriver test persists and verifies a database notification', function () {
    $driver = new DatabaseDriver;

    $result = $driver->test(new TestChannelProviderData(channel: 'database'));

    expect($result->success)->toBeTrue();
});

it('ContactRecipientResolver returns empty when the subject has no member id', function () {
    $subject = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };

    $recipients = app(ContactRecipientResolver::class)->resolve([], new NotificationContextData(
        type: NotificationType::factory()->create(),
        subject: $subject,
    ));

    expect($recipients)->toBeEmpty();
});

it('RecordCustomerCommunicationDelivery ignores non-customer notifications', function () {
    app(RecordCustomerCommunicationDelivery::class)->handle(new NotificationSent(
        notifiable: User::factory()->create(),
        notification: new Notification,
        channel: 'mail',
    ));

    expect(true)->toBeTrue();
});

it('ProductGroupIdsPredicate accepts empty configured product group lists', function () {
    $predicate = new ProductGroupIdsPredicate;

    expect($predicate->matches([], new DiscountCriteriaContext(product_group_id: null)))->toBeTrue()
        ->and($predicate->matches('ignored', new DiscountCriteriaContext(product_group_id: null)))->toBeTrue()
        ->and($predicate->matches([10, 20], new DiscountCriteriaContext(product_group_id: null)))->toBeFalse()
        ->and($predicate->matches([10, 20], new DiscountCriteriaContext(product_group_id: 20)))->toBeTrue();
});
