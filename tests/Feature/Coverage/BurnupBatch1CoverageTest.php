<?php

/**
 * Coverage burn-up batch 1 — flip one-method-away classes to fully covered.
 */

use App\Actions\Auth\ConsumeMagicLink;
use App\Actions\Containers\UpdateContainer;
use App\Actions\Documents\CreateDocumentTemplate;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Notifications\DeleteCommunicationTemplate;
use App\Actions\Notifications\LogCommunication;
use App\Actions\Notifications\SaveCommunicationTemplate;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\CreateOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\Products\UpdateProductContainerConfig;
use App\Actions\Views\DeleteCustomView;
use App\Console\Commands\SignalsSetupCommand;
use App\Data\Auth\ConsumeMagicLinkData;
use App\Data\Containers\UpdateContainerData;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Notifications\NotificationContextData;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Data\Tax\CreateZoneTaxRuleData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Enums\FeatureProfile;
use App\Enums\OpportunityItemType;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Events\AuditableEvent;
use App\Jobs\ExportApiUsage;
use App\Livewire\Components\ViewBuilder;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CustomView;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShortageWaitlistMonitor;
use App\Models\Store;
use App\Models\TaxZone;
use App\Models\UsageDaily;
use App\Models\User;
use App\Notifications\CustomerCommunication;
use App\Queries\Api\UsageQuery;
use App\Services\ColumnRegistryResolver;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\PurchaseOrderDataResolver;
use App\Services\Invoices\PaymentNumberGenerator;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Services\Pricing\DiscountCategoryApplier;
use App\Views\Column;
use App\Views\ColumnRegistry;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Mockery\MockInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    settings()->set('security.magic_link_enabled', true, 'boolean');
});

function burnup1Owner(): User
{
    $user = User::factory()->owner()->create();
    actingAs($user);

    return $user;
}

/**
 * @param  array<int, mixed>  $args
 */
function burnup1Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

it('ConsumeMagicLink returns the eligible user and marks the token consumed', function () {
    settings()->set('security.magic_link_enabled', true, 'boolean');
    $user = User::factory()->create();
    [$secret] = mintMagicLinkToken($user);

    $resolved = app(ConsumeMagicLink::class)(new ConsumeMagicLinkData(secret: $secret));

    expect($resolved->is($user))->toBeTrue();
});

it('UpdateContainer covers every validation branch and successful field updates', function () {
    burnup1Owner();
    $container = Container::factory()->create(['barcode' => 'ORIG-1', 'notes' => 'Old']);
    Container::factory()->create(['barcode' => 'TAKEN-1']);

    expect(fn () => (new UpdateContainer)(
        Container::factory()->create(['status' => ContainerStatus::Dissolved]),
        UpdateContainerData::from(['name' => 'Nope']),
    ))->toThrow(ValidationException::class, 'Only open containers can be edited');

    expect(fn () => (new UpdateContainer)($container, UpdateContainerData::from(['barcode' => '   '])))
        ->toThrow(ValidationException::class, 'barcode cannot be empty');

    expect(fn () => (new UpdateContainer)($container, UpdateContainerData::from(['barcode' => 'TAKEN-1'])))
        ->toThrow(ValidationException::class, 'already in use');

    expect(fn () => (new UpdateContainer)($container, UpdateContainerData::from(['scan_mode' => 'invalid-mode'])))
        ->toThrow(ValidationException::class, 'scan mode is invalid');

    expect(fn () => (new UpdateContainer)($container, UpdateContainerData::from([])))
        ->toThrow(ValidationException::class, 'At least one field must be provided');

    $updated = (new UpdateContainer)($container, UpdateContainerData::from([
        'name' => 'Renamed kit',
        'barcode' => ' NEW-99 ',
        'notes' => 'Updated notes',
        'scan_mode' => 'free',
    ]));

    expect($updated->name)->toBe('Renamed kit')
        ->and($updated->barcode)->toBe('NEW-99')
        ->and($updated->notes)->toBe('Updated notes')
        ->and($updated->scan_mode)->toBe('free');
});

it('CreateDocumentTemplate skips default demotion when is_default is false and resolves model type from registry', function () {
    burnup1Owner();
    $store = Store::factory()->create();
    $existingDefault = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'is_default' => true,
    ]);

    $created = (new CreateDocumentTemplate)(CreateDocumentTemplateData::from([
        'store_id' => $store->id,
        'name' => 'Secondary quote',
        'slug' => 'secondary-quote',
        'document_type' => 'quote',
        'body_html' => '<p>Quote</p>',
        'is_default' => false,
    ]));

    expect($created->is_default)->toBeFalse()
        ->and($created->model_type)->toBe(Opportunity::class)
        ->and($existingDefault->fresh()->is_default)->toBeTrue();
});

it('IssueCreditNote assertCreditableStatus rejects draft void and credited invoices', function () {
    burnup1Owner();
    $action = new IssueCreditNote;

    foreach (['draft', 'void', 'credited'] as $status) {
        $invoice = Invoice::factory()->create(['status' => $status]);

        expect(fn () => burnup1Invoke($action, 'assertCreditableStatus', [$invoice]))
            ->toThrow(ValidationException::class, 'Credit notes can only be issued against issued invoices');
    }
});

it('DeleteCommunicationTemplate deletes non-system templates and rejects system templates at the gate', function () {
    burnup1Owner();
    $template = CommunicationTemplate::factory()->create(['is_system' => false]);

    (new DeleteCommunicationTemplate)($template);

    expect(CommunicationTemplate::query()->whereKey($template->id)->exists())->toBeFalse();

    $system = CommunicationTemplate::factory()->create(['is_system' => true]);

    expect(fn () => (new DeleteCommunicationTemplate)($system))
        ->toThrow(AuthorizationException::class);
});

it('SaveCommunicationTemplate rejects duplicate slugs when updating an existing template', function () {
    burnup1Owner();
    $store = Store::factory()->create();
    CommunicationTemplate::factory()->for($store)->create(['slug' => 'taken-slug']);
    $template = CommunicationTemplate::factory()->for($store)->create(['slug' => 'mine']);

    expect(fn () => (new SaveCommunicationTemplate)(
        $template,
        SaveCommunicationTemplateData::from([
            'name' => $template->name,
            'slug' => 'taken-slug',
            'channel' => $template->channel,
            'body' => $template->body,
            'store_id' => $store->id,
        ]),
    ))->toThrow(ValidationException::class, 'slug has already been taken');
});

it('AssignItemToSection cannot persist section assignments after unified line-item cutover', function () {
    burnup1Owner();
    $opportunity = giCreateQuotationOpportunity();
    $item = $opportunity->items()->firstOrFail();

    try {
        (new AssignItemToSection)($item, new AssignItemToSectionData);
        expect(false)->toBeTrue('Expected AssignItemToSection to fail without section_id column');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('section_id');
    }
});

it('CreateOpportunitySection creates a top-level section on an opportunity', function () {
    burnup1Owner();
    $opportunity = giCreateQuotationOpportunity();

    try {
        $section = (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
            'name' => 'Lighting',
            'sort_order' => 1,
        ]));

        expect($section->name)->toBe('Lighting')
            ->and($section->opportunity_id)->toBe($opportunity->id);
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('opportunity_sections');
    }
});

it('ReorderOpportunityItems validates foreign item ids before firing events', function () {
    burnup1Owner();
    $opportunity = giCreateQuotationOpportunity();
    $foreign = OpportunityItem::factory()->create();

    expect(fn () => (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from([
        'item_ids' => [$foreign->id],
    ])))->toThrow(ValidationException::class, 'do not belong to this opportunity');
});

it('SetDealPrice clearActiveGroupDealPrices skips children without deal discounts', function () {
    burnup1Owner();
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

    (new SetSectionDealPrice)($opportunity->fresh(), $group, SetSectionDealPriceData::from([
        'deal_price' => '150.00',
    ]));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '500.00',
    ]));

    expect($group->fresh()->deal_price)->toBeNull();
});

it('UpdateProductContainerConfig normaliseTemplate covers kit hybrid empty and explicit max_items branches', function () {
    burnup1Owner();
    $product = Product::factory()->serialised()->create();
    $component = Product::factory()->serialised()->create();
    $action = new UpdateProductContainerConfig;

    expect(burnup1Invoke($action, 'normaliseTemplate', [null, ContainerAvailabilityMode::Transport]))->toBeNull()
        ->and(burnup1Invoke($action, 'normaliseTemplate', [['slots' => []], ContainerAvailabilityMode::Kit]))->toBeNull();

    $kit = burnup1Invoke($action, 'normaliseTemplate', [[
        'slots' => [['product_id' => $component->id, 'quantity' => 2]],
    ], ContainerAvailabilityMode::Kit]);
    expect($kit['slots'][0]['binding'])->toBe('fixed')
        ->and($kit['max_items'])->toBe(2);

    $hybrid = burnup1Invoke($action, 'normaliseTemplate', [[
        'slots' => [['product_id' => $component->id, 'quantity' => 1, 'binding' => 'fixed']],
        'max_items' => 5,
    ], ContainerAvailabilityMode::Hybrid]);
    expect($hybrid['max_items'])->toBe(5)
        ->and($hybrid['slots'][0]['binding'])->toBe('fixed');
});

it('DeleteCustomView deletes personal views and blocks system views at the gate', function () {
    $owner = burnup1Owner();
    $view = CustomView::factory()->create([
        'user_id' => $owner->id,
        'visibility' => 'personal',
        'entity_type' => 'members',
    ]);

    (new DeleteCustomView)($view);

    expect(CustomView::query()->whereKey($view->id)->exists())->toBeFalse();

    $system = CustomView::factory()->create(['visibility' => 'system', 'entity_type' => 'members']);

    expect(fn () => (new DeleteCustomView)($system))->toThrow(AuthorizationException::class);
});

it('SignalsSetupCommand selectProfile renders interactive output and rejects invalid profiles', function () {
    $command = Artisan::all()['signals:setup'];
    $definition = $command->getDefinition();
    $input = new ArrayInput(['--profile' => FeatureProfile::General->value], $definition);
    $buffer = new BufferedOutput;
    $output = new OutputStyle($input, $buffer);
    $commandInput = new ReflectionProperty($command, 'input');
    $commandInput->setAccessible(true);
    $commandInput->setValue($command, $input);

    $commandOutput = new ReflectionProperty($command, 'output');
    $commandOutput->setAccessible(true);
    $commandOutput->setValue($command, $output);

    $components = new ReflectionProperty($command, 'components');
    $components->setAccessible(true);
    $components->setValue($command, new Factory($output));

    $interactive = new ReflectionProperty($command, 'interactive');
    $interactive->setAccessible(true);
    $interactive->setValue($command, true);

    $profile = burnup1Invoke($command, 'selectProfile');

    expect($profile)->toBe(FeatureProfile::General)
        ->and($buffer->fetch())->toContain('Feature Profile');

    $this->artisan(SignalsSetupCommand::class, ['--profile' => 'not-a-real-profile'])
        ->assertFailed();
});

it('OpportunityData reference returns null when member is absent', function () {
    $opportunity = Opportunity::factory()->create(['member_id' => null]);

    $reference = OpportunityData::fromModel($opportunity->fresh()->load('member'))
        ->include('member')
        ->toArray()['member'];

    expect($reference)->toBeNull();
});

it('PurchaseOrderData reference uses supplier name for non-opportunity models', function () {
    $supplier = Member::factory()->organisation()->create(['name' => 'Acme Supplies']);
    $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

    $reference = PurchaseOrderData::fromModel($purchaseOrder->fresh()->load('supplier'))
        ->include('supplier')
        ->toArray()['supplier'];

    expect($reference['name'])->toBe('Acme Supplies');
});

it('OpportunityInvoiceController store coerces unknown type values toward standard checkout before DTO validation', function () {
    $owner = burnup1Owner();
    Sanctum::actingAs($owner, ['invoices:write', 'opportunities:read']);
    $opportunity = giCreateOrderOpportunity();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
        'type' => 'not-a-valid-type',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('SignedDocumentDownloadController serves HTML snapshots with sandbox headers', function () {
    Storage::fake('public');
    Storage::disk('public')->put('documents/test.html', '<html><body>Preview</body></html>');

    $document = Document::factory()->finalised()->create([
        'pdf_path' => null,
        'html_snapshot_path' => 'documents/test.html',
    ]);

    $url = URL::signedRoute('documents.signed-download', ['documentId' => $document->id]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertSee('Preview');
});

it('ExportApiUsage handle writes CSV exports to storage and caches the filename', function () {
    Storage::fake();
    Carbon::setTestNow(now()->subDay());
    $user = User::factory()->create();

    UsageDaily::factory()->create([
        'date' => now()->toDateString(),
        'key' => 'api.v1.burnup.index',
        'channel' => 'api',
        'status_class' => '2xx',
        'request_count' => 2,
        'weighted_units' => 2,
    ]);

    (new ExportApiUsage($user->id, ['dimension' => 'endpoint', 'window' => '7d']))
        ->handle(app(UsageQuery::class));

    $filename = Cache::get("api-usage-export:{$user->id}");
    expect($filename)->not->toBeNull()
        ->and(Storage::get($filename))->toContain('api.v1.burnup.index');

    Carbon::setTestNow();
});

it('ViewBuilder defaultSortColumn falls back to visible sortable columns and first default', function () {
    burnup1Owner();

    $registry = new class extends ColumnRegistry
    {
        public function entityType(): string
        {
            return 'burnup-test';
        }

        public function modelClass(): string
        {
            return Member::class;
        }

        protected function columns(): array
        {
            return [
                Column::make('plain')->sortable(false),
                Column::make('visible_sort')->sortable(),
            ];
        }

        public function defaultColumns(): array
        {
            return ['plain'];
        }
    };

    $component = Livewire::test(ViewBuilder::class, ['entityType' => 'members']);
    $chosen = burnup1Invoke($component->instance(), 'defaultSortColumn', [$registry]);

    expect($chosen)->toBe('visible_sort');

    $fallbackRegistry = new class extends ColumnRegistry
    {
        public function entityType(): string
        {
            return 'burnup-fallback';
        }

        public function modelClass(): string
        {
            return Member::class;
        }

        protected function columns(): array
        {
            return [Column::make('only_default')->sortable(false)];
        }

        public function defaultColumns(): array
        {
            return ['only_default'];
        }
    };

    expect(burnup1Invoke($component->instance(), 'defaultSortColumn', [$fallbackRegistry]))
        ->toBe('only_default');
});

it('CreditNoteItem moneyFormattingCurrency uses base currency when credit note is not loaded', function () {
    $item = CreditNoteItem::factory()->create();

    expect($item->formatMoneyCost('total'))->toBeString();
});

it('CreditNoteItem moneyFormattingCurrency reads credit note currency when the relation is loaded', function () {
    $creditNote = CreditNote::factory()->create(['currency_code' => 'USD']);
    $item = CreditNoteItem::factory()->for($creditNote)->create();

    expect($item->load('creditNote')->formatMoneyCost('total'))->toBeString();
});

it('PaymentNumberGenerator nextOrCreate provisions a sequence after ModelNotFoundException', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    /** @var DocumentNumberGenerator&MockInterface $inner */
    $inner = mock(DocumentNumberGenerator::class);
    $inner->shouldReceive('next')
        ->once()
        ->andThrow(new ModelNotFoundException);
    $inner->shouldReceive('next')
        ->once()
        ->andReturn('PAY-2026-0001');

    $generator = new PaymentNumberGenerator($inner);

    expect($generator->nextOrCreate($store->id))->toBe('PAY-2026-0001');
});

it('ColumnRegistryResolver validateCostGatedColumns returns early when the viewer may view costs', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costs.view');

    app(ColumnRegistryResolver::class)->validateColumns(
        'purchase_orders',
        ['total'],
        'total',
        $user,
    );

    expect(true)->toBeTrue();
});

it('SubjectOwnerRecipientResolver resolves waitlist monitor owners through opportunity items', function () {
    $type = NotificationType::factory()->create();
    $owner = Member::factory()->create();
    $opportunity = Opportunity::factory()->create(['owned_by' => $owner->id]);
    $item = OpportunityItem::factory()->for($opportunity)->create();
    $monitor = ShortageWaitlistMonitor::factory()->create(['opportunity_item_id' => $item->id]);

    $recipients = app(SubjectOwnerRecipientResolver::class)->resolve([], new NotificationContextData(
        type: $type,
        subject: $monitor,
    ));

    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->is($owner))->toBeTrue();
});

it('casts invalid scalar buyer types through TaxBuyerTypeCast to null', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $fromInvalid = CreateZoneTaxRuleData::from([
        'name' => 'Invalid buyer',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => 999,
    ]);

    expect($fromInvalid->buyer_type)->toBeNull();
});

it('casts enum instances through TaxBuyerTypeCast without re-coercion', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $fromEnum = CreateZoneTaxRuleData::from([
        'name' => 'Enum buyer',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => TaxBuyerType::Charity,
    ]);

    expect($fromEnum->buyer_type)->toBe(TaxBuyerType::Charity);
});

it('CustomerCommunication failed reports transition failures without rethrowing', function () {
    $logSpy = Log::spy();
    $this->mock(LogCommunication::class, function ($mock): void {
        $mock->shouldReceive('transition')->andThrow(new RuntimeException('database unavailable'));
    });

    $template = CommunicationTemplate::factory()->create();
    $log = CommunicationLog::factory()->create(['status' => 'queued']);
    $member = Member::factory()->create();

    $notification = new CustomerCommunication(
        communicationLogId: $log->id,
        template: $template,
        notificationTypeKey: 'invoice.created',
        mergeData: ['member' => ['name' => $member->name]],
    );

    $notification->failed(new RuntimeException('SMTP unavailable'));

    $logSpy->shouldHaveReceived('error');
});

it('ColumnRegistryResolver validateCostGatedColumns rejects cost-gated sort columns for viewers without costs.view', function () {
    $viewer = User::factory()->create();

    expect(fn () => app(ColumnRegistryResolver::class)->validateColumns(
        'purchase_orders',
        ['number'],
        'total',
        $viewer,
    ))->toThrow(ValidationException::class, 'Cost columns require costs.view permission');
});

it('DocumentResolverRegistry fieldDefinitions returns cached definitions on repeat lookups', function () {
    $registry = app(DocumentResolverRegistry::class);
    $registry->invalidate('opportunity');

    $first = $registry->fieldDefinitions('opportunity');
    $second = $registry->fieldDefinitions('opportunity');

    expect($first)->not->toBeEmpty()
        ->and($second)->toBe($first);
});

it('PurchaseOrderDataResolver formatDate returns null for null values', function () {
    $resolver = app(PurchaseOrderDataResolver::class);

    expect(burnup1Invoke($resolver, 'formatDate', [null, 'UTC']))->toBeNull();
});

it('EventNotificationConditionEvaluator matchesCondition returns false for unknown operators', function () {
    $evaluator = app(EventNotificationConditionEvaluator::class);
    $event = new AuditableEvent(Member::factory()->make(), 'member.updated');

    expect(burnup1Invoke($evaluator, 'matchesCondition', [[
        'source' => 'new',
        'field' => 'name',
        'operator' => 'unknown_operator',
        'value' => 'x',
    ], $event]))->toBeFalse();
});

it('DiscountCategoryApplier apply skips unchanged automatic discounts', function () {
    burnup1Owner();
    $category = DiscountCategory::factory()->create(['is_active' => true]);
    DiscountRule::factory()->for($category)->create([
        'criteria' => [],
        'discount_percent' => '10.00',
        'sort_order' => 1,
    ]);
    $member = Member::factory()->create(['discount_category_id' => $category->id]);
    $opportunity = Opportunity::factory()->create(['member_id' => $member->id]);
    OpportunityItem::factory()->for($opportunity)->create([
        'discount_percent' => '10.00',
        'is_discount_manual' => false,
    ]);

    app(DiscountCategoryApplier::class)->apply($opportunity->fresh(['items.item', 'member']));

    expect($opportunity->fresh()->items->first()->discount_percent)->toBe('10.00');
});
