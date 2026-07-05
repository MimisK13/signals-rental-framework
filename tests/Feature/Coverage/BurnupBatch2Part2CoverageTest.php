<?php

/**
 * Coverage burn-up batch 2 (part 2) — services, data, policies, middleware, models.
 */

use App\Actions\Documents\ImportDocumentTemplate;
use App\Contracts\Notifications\ChannelDriver;
use App\Data\Documents\GenerateDocumentData;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Enums\OpportunityItemType;
use App\Http\Middleware\LogApiRequest;
use App\Models\ChannelProvider;
use App\Models\Container;
use App\Models\CreditNote;
use App\Models\CreditNoteTax;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use App\Services\Containers\ContainerBarcodeResolver;
use App\Services\Documents\DocumentRenderErrorSanitizer;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\TemplateLineDiff;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Notifications\NotificationLinkResolver;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Pricing\DiscountRuleEvaluator;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;
use App\Services\VirtualStock\VirtualStockNumberGenerator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function burnup2Part2Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function burnup2Part2PolicyUser(array $permissions): User
{
    $user = User::factory()->create(['is_owner' => false]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('GenerateDocumentData resolvedDocumentableClass resolves all supported aliases', function () {
    expect(GenerateDocumentData::from([
        'documentable_type' => 'opportunity',
        'documentable_id' => 1,
    ])->resolvedDocumentableClass())->toBe(Opportunity::class);

    expect(GenerateDocumentData::from([
        'documentable_type' => Member::class,
        'documentable_id' => 1,
    ])->resolvedDocumentableClass())->toBe(Member::class);

    expect(GenerateDocumentData::from([
        'documentable_type' => 'payment',
        'documentable_id' => 1,
    ])->resolvedDocumentableClass())->toBe(InvoicePayment::class);

    expect(fn () => GenerateDocumentData::from([
        'documentable_type' => 'unknown_entity',
        'documentable_id' => 1,
    ])->resolvedDocumentableClass())->toThrow(InvalidArgumentException::class);
});

it('OpportunityItemAssetData reference prefers asset_number and id fallbacks', function () {
    $method = new ReflectionMethod(OpportunityItemAssetData::class, 'reference');
    $method->setAccessible(true);

    expect($method->invoke(null, null))->toBeNull();

    $named = StockLevel::factory()->serialised()->create([
        'item_name' => 'Moving head',
        'asset_number' => 'ASSET-1',
    ]);
    expect($method->invoke(null, $named)?->name)->toBe('Moving head');

    $numbered = StockLevel::factory()->serialised()->make([
        'item_name' => null,
        'asset_number' => 'ASSET-2',
    ]);
    $numbered->id = 7;

    expect($method->invoke(null, $numbered)?->name)->toBe('ASSET-2');

    $fallback = new StockLevel([
        'item_name' => null,
        'asset_number' => null,
    ]);
    $fallback->id = 42;

    expect($method->invoke(null, $fallback)?->name)->toBe('42');
});

it('LogApiRequest resolveDurationMs uses request start float when present', function () {
    $middleware = app(LogApiRequest::class);
    $request = Request::create('/api/v1/products', 'GET');
    $request->attributes->set('api_metering.start', microtime(true) - 0.01);

    $duration = burnup2Part2Invoke($middleware, 'resolveDurationMs', [$request]);

    expect($duration)->toBeGreaterThan(0);
});

it('CreditNoteTax moneyFormattingCurrency falls back without loaded credit note relation', function () {
    settings()->set('company.base_currency', 'GBP');

    $creditNote = CreditNote::factory()->create(['currency_code' => 'USD']);
    $creditTax = CreditNoteTax::factory()->create(['credit_note_id' => $creditNote->id]);

    expect($creditTax->formatMoneyCost('tax_amount'))->toBeString();
});

it('PurchaseOrderPolicy view denies users without purchase order view permission', function () {
    $policy = new PurchaseOrderPolicy;
    $user = User::factory()->create();

    expect($policy->view($user))->toBeFalse();
});

it('ContainerBarcodeResolver resolve matches container uuid barcodes', function () {
    $store = Store::factory()->create();
    $container = Container::factory()->kit()->create([
        'store_id' => $store->id,
        'uuid' => 'uuid-barcode-target',
        'barcode' => 'OTHER-CODE',
    ]);

    $result = app(ContainerBarcodeResolver::class)->resolve('uuid-barcode-target');

    expect($result)->toMatchArray(['type' => 'container'])
        ->and($result['container']->is($container))->toBeTrue();
});

it('DocumentRenderErrorSanitizer sanitize returns fallback when stripping removes all text', function () {
    $message = DocumentRenderErrorSanitizer::sanitize(new Exception('   '));

    expect($message)->toBe('Unable to render document template.');
});

it('DocumentSequenceDefaults prefixFor returns configured prefixes for known types', function () {
    settings()->set('virtual_stock.fallback_number_prefix', 'VS-CUSTOM-');
    settings()->set('purchase_orders.fallback_number_prefix', 'PO-CUSTOM-');

    expect(DocumentSequenceDefaults::prefixFor('quote'))->toBe('QUO-')
        ->and(DocumentSequenceDefaults::prefixFor(VirtualStockNumberGenerator::DOCUMENT_TYPE))->toBe('VS-CUSTOM-')
        ->and(DocumentSequenceDefaults::prefixFor(PurchaseOrderNumberGenerator::DOCUMENT_TYPE))->toBe('PO-CUSTOM-');
});

it('TemplateLineDiff computeOps handles delete-only tail in the LCS backtrack loop', function () {
    $diff = new TemplateLineDiff;
    $method = new ReflectionMethod(TemplateLineDiff::class, 'computeOps');
    $method->setAccessible(true);

    $ops = $method->invoke($diff, ['alpha', 'beta'], ['alpha']);

    expect($ops)->toBe([
        ['type' => 'equal', 'lines' => ['alpha']],
        ['type' => 'delete', 'lines' => ['beta']],
    ]);
});

it('ChannelProviderConfigService persist updates non-email provider configs', function () {
    $provider = ChannelProvider::factory()->create(['config' => ['token' => 'old']]);
    $driver = new class implements ChannelDriver
    {
        public function key(): string
        {
            return 'noop';
        }

        public function channel(): string
        {
            return 'noop';
        }

        public function label(): string
        {
            return 'Noop';
        }

        public function configSchema(): array
        {
            return [new ChannelConfigFieldData('token', 'Token', 'string')];
        }

        public function resolveConfig(): array
        {
            return [];
        }

        public function send(ChannelSendData $payload): void {}

        public function test(TestChannelProviderData $data): ChannelTestResultData
        {
            return ChannelTestResultData::passed($this, 'ok');
        }
    };

    app(ChannelProviderConfigService::class)->persist($driver, ['token' => 'new'], $provider);

    expect($provider->fresh()->config)->toBe(['token' => 'new']);
});

it('NotificationLinkResolver resolve returns null for missing classes and deleted subjects', function () {
    $resolver = app(NotificationLinkResolver::class);

    $notification = new DatabaseNotification;
    $notification->data = ['payload' => [
        'subject_type' => 'App\\Models\\DefinitelyMissingModel',
        'subject_id' => 1,
    ]];

    expect($resolver->resolve($notification))->toBeNull();

    $member = Member::factory()->create();
    $notification->data = ['payload' => [
        'subject_type' => Member::class,
        'subject_id' => $member->id,
    ]];
    $member->delete();

    expect($resolver->resolve($notification))->toBeNull();
});

it('OpportunityEditorTreeService parentPathFromParentGroupKey resolves nested group paths', function () {
    $service = app(OpportunityEditorTreeService::class);
    $method = new ReflectionMethod(OpportunityEditorTreeService::class, 'parentPathFromParentGroupKey');
    $method->setAccessible(true);

    $opportunity = Opportunity::factory()->create();
    $parent = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => OpportunityItemType::Group,
        'path' => '0001',
    ]);
    $items = collect([$parent]);

    expect($method->invoke($service, $items, 'group-parent:root'))->toBeNull()
        ->and($method->invoke($service, $items, 'group-parent:'.$parent->id))->toBe('0001');

    expect(fn () => $method->invoke($service, $items, 'group-parent:999'))
        ->toThrow(ValidationException::class);

    expect($method->invoke($service, $items, 'custom-parent-key'))->toBeNull();
});

it('DiscountRuleEvaluator isDiscountable respects non-discountable products', function () {
    $evaluator = app(DiscountRuleEvaluator::class);
    $method = new ReflectionMethod(DiscountRuleEvaluator::class, 'isDiscountable');
    $method->setAccessible(true);

    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->rental()->create(['discountable' => false]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
    ]);

    expect($method->invoke($evaluator, $item->load('item')))->toBeFalse();
});

it('EmailDriver persist path in ChannelProviderConfigService updates mail settings', function () {
    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'old.example.com',
    ]);

    app(ChannelProviderConfigService::class)->persist(app(EmailDriver::class), [
        'mailer' => 'smtp',
        'smtp_host' => 'new.example.com',
    ]);

    expect(settings('email.smtp_host'))->toBe('new.example.com');
});

it('NotificationLinkResolver resolve prefers opportunity payload links', function () {
    $resolver = app(NotificationLinkResolver::class);
    $opportunity = Opportunity::factory()->create();

    $notification = new DatabaseNotification;
    $notification->data = ['payload' => ['opportunity' => ['id' => $opportunity->id]]];

    expect($resolver->resolve($notification))->toBe(route('opportunities.show', $opportunity));
});

it('PurchaseOrderPolicy view allows store-restricted users with access', function () {
    $policy = new PurchaseOrderPolicy;
    $store = Store::factory()->create();
    $po = PurchaseOrder::factory()->create(['store_id' => $store->id]);
    $viewer = burnup2Part2PolicyUser(['purchase_orders.view']);
    MemberStore::query()->create([
        'member_id' => $viewer->member_id,
        'store_id' => $store->id,
    ]);

    expect($policy->view($viewer, $po))->toBeTrue();
});

it('OpportunityItemAssetData fromModel maps loaded stock level references', function () {
    $asset = OpportunityItemAsset::factory()->create();
    $asset->load('stockLevel');

    $dto = OpportunityItemAssetData::fromModel($asset);

    expect($dto->stock_level)->not->toBeNull();
});

it('DocumentRenderErrorSanitizer sanitize strips view paths from render failures', function () {
    $sanitized = DocumentRenderErrorSanitizer::sanitize(new ErrorException(
        'Undefined variable $foo (View: /var/www/resources/views/documents/show.blade.php)',
    ));

    expect($sanitized)->toBe('Undefined variable $foo')
        ->and($sanitized)->not->toContain('/var/www');
});

it('ValidatesDocumentTemplateStoreAccess aborts for unauthenticated users', function () {
    auth()->logout();

    $method = new ReflectionMethod(ImportDocumentTemplate::class, 'assertUserCanAccessStore');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(new ImportDocumentTemplate, 1))
        ->toThrow(HttpException::class);
});

it('GenerateDocumentData resolvedDocumentableClass accepts Opportunity class suffix aliases', function () {
    expect(GenerateDocumentData::from([
        'documentable_type' => 'App\\Models\\Opportunity',
        'documentable_id' => 1,
    ])->resolvedDocumentableClass())->toBe(Opportunity::class);
});

it('Communication unsubscribe confirm route is reachable with signed URL', function () {
    $member = Member::factory()->contact()->create();
    $type = NotificationType::factory()->create(['key' => 'burnup2.confirm']);

    $url = URL::signedRoute('communications.unsubscribe.show', [
        'member' => $member->id,
        'type' => $type->key,
    ]);

    $this->get($url)->assertOk();
});
