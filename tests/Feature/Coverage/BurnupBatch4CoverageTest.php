<?php

/**
 * Coverage burn-up batch 4 — part 1 (predicates, services, policies, models, jobs).
 */

use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Enums\DiscountRuleScope;
use App\Enums\RateTransactionType;
use App\Enums\StockMethod;
use App\Exceptions\Documents\DocumentRenderException;
use App\Jobs\ExportApiRequestLog;
use App\Models\DocumentSequence;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\OpportunitySection;
use App\Models\OpportunitySurchargeGroup;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\RequestLog;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Policies\DocumentSequencePolicy;
use App\Policies\VirtualStockIntakePolicy;
use App\Services\Documents\SandboxedBladeCompiler;
use App\Services\Notifications\CommunicationPreferences;
use App\Services\Pricing\OpportunitySurchargeApplicator;
use App\Services\Pricing\Predicates\QuantityMaxPredicate;
use App\Services\Pricing\Predicates\TagNamesPredicate;
use App\Services\Pricing\Predicates\TransactionTypesPredicate;
use App\Services\PurchaseOrders\PurchaseOrderTaxCalculator;
use App\Services\Shortages\MoneyApportionment;
use App\Services\Shortages\ShortageCrossOpportunityScanner;
use App\Services\Shortages\ShortageDetector;
use App\Services\TaxCalculator;
use App\Services\VirtualStock\VirtualAssetStatusRegistry;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function burnup4Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function burnup4PolicyUser(array $permissions = []): User
{
    $user = User::factory()->create();
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function burnup4GrantStore(User $user, Store $store): void
{
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
}

it('QuantityMaxPredicate matches every quantity branch', function () {
    $predicate = new QuantityMaxPredicate;

    $perLine = new DiscountCriteriaContext(line_quantity: 3, rule_scope: DiscountRuleScope::PerLine);
    expect($predicate->matches(null, $perLine))->toBeTrue()
        ->and($predicate->matches('', $perLine))->toBeTrue()
        ->and($predicate->matches(5, $perLine))->toBeTrue()
        ->and($predicate->matches(2, $perLine))->toBeFalse()
        ->and($predicate->matches(5, new DiscountCriteriaContext(
            line_quantity: null,
            rule_scope: DiscountRuleScope::PerLine,
        )))->toBeFalse();

    $aggregateProduct = new DiscountCriteriaContext(
        line_quantity: 1,
        aggregate_product_quantity: 4,
        rule_scope: DiscountRuleScope::AggregateProduct,
    );
    expect($predicate->matches(10, $aggregateProduct))->toBeTrue();

    $aggregateGroup = new DiscountCriteriaContext(
        line_quantity: 1,
        aggregate_group_quantity: 6,
        rule_scope: DiscountRuleScope::AggregateGroup,
    );
    expect($predicate->matches(5, $aggregateGroup))->toBeFalse()
        ->and($predicate->matches(10, $aggregateGroup))->toBeTrue();
});

it('TagNamesPredicate matches empty criteria and required tag intersections', function () {
    $predicate = new TagNamesPredicate;

    expect($predicate->matches([], new DiscountCriteriaContext(tag_names: ['vip'])))->toBeTrue()
        ->and($predicate->matches(['', 0, false], new DiscountCriteriaContext(tag_names: ['vip'])))->toBeTrue()
        ->and($predicate->matches(['vip'], new DiscountCriteriaContext(tag_names: ['VIP'])))->toBeTrue()
        ->and($predicate->matches(['vip', 'rush'], new DiscountCriteriaContext(tag_names: ['vip'])))->toBeFalse();
});

it('TransactionTypesPredicate matches coerced types and rejects missing context', function () {
    $predicate = new TransactionTypesPredicate;

    expect($predicate->matches([], new DiscountCriteriaContext(transaction_type: RateTransactionType::Rental->value)))->toBeTrue()
        ->and($predicate->matches(['rental'], new DiscountCriteriaContext(transaction_type: null)))->toBeFalse()
        ->and($predicate->matches(['rental', new stdClass, null], new DiscountCriteriaContext(transaction_type: RateTransactionType::Rental->value)))->toBeTrue()
        ->and($predicate->matches(['sale'], new DiscountCriteriaContext(transaction_type: RateTransactionType::Rental->value)))->toBeFalse();
});

it('MoneyApportionment apportion distributes remainders and breaks ties on ascending keys', function () {
    $service = new MoneyApportionment;

    expect($service->apportion([], 500))->toBe([])
        ->and($service->apportion([1 => 0, 2 => 0], 500))->toBe([1 => 0, 2 => 0])
        ->and($service->apportion([1 => 1, 2 => 1], 100))->toBe([1 => 50, 2 => 50]);

    $tieBreak = $service->apportion([2 => 1, 1 => 1], 3);
    expect(array_sum($tieBreak))->toBe(3)
        ->and($tieBreak[1])->toBe(2)
        ->and($tieBreak[2])->toBe(1);
});

it('TaxCalculator resolveRule returns direct matches and default fallbacks', function () {
    $orgClass = OrganisationTaxClass::factory()->create(['is_default' => false]);
    $productClass = ProductTaxClass::factory()->create(['is_default' => false]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
    $rule = TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $calculator = app(TaxCalculator::class);
    expect($calculator->resolveRule($orgClass->id, $productClass->id, now())?->is($rule))->toBeTrue()
        ->and($calculator->resolveRule(null, $productClass->id))->toBeNull()
        ->and($calculator->resolveRule($orgClass->id, null))->toBeNull();

    $defaultOrg = OrganisationTaxClass::factory()->default()->create();
    $defaultProduct = ProductTaxClass::factory()->default()->create();

    expect($calculator->resolveRule($defaultOrg->id, $defaultProduct->id))->toBeNull();

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $defaultOrg->id,
        'product_tax_class_id' => $defaultProduct->id,
        'tax_rate_id' => TaxRate::factory()->create(['rate' => '5.0000'])->id,
    ]);

    expect($calculator->resolveRule($orgClass->id, $productClass->id)?->is($rule))->toBeTrue();
});

it('VirtualAssetStatusRegistry assertCanTransitionTo allows identical statuses', function () {
    $registry = app(VirtualAssetStatusRegistry::class);
    $status = $registry->defaultStatus();

    $registry->assertCanTransitionTo($status, $status);

    expect(fn () => $registry->assertCanTransitionTo('retired', 'available'))
        ->toThrow(InvalidArgumentException::class);
});

it('ShortageCrossOpportunityScanner withPeerShortages covers early exits and peer merges', function () {
    $scanner = app(ShortageCrossOpportunityScanner::class);
    $shortage = Shortage::make(
        opportunityItemId: 1,
        opportunityId: 1,
        productId: 1,
        productName: 'Widget',
        storeId: 1,
        requestedQuantity: 2,
        availableQuantity: 0,
        trackingType: StockMethod::Bulk,
        startsAt: IlluminateCarbon::now(),
        endsAt: IlluminateCarbon::now()->addDay(),
        isCritical: false,
    );
    $collection = new ShortageCollection([$shortage]);

    $noStore = Opportunity::factory()->create(['store_id' => null]);
    expect($scanner->withPeerShortages($noStore, $collection))->toBe($collection);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $localOpp = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(5),
    ]);
    expect($scanner->withPeerShortages($localOpp, new ShortageCollection))->toEqual(new ShortageCollection);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 0,
    ]);

    $peerOpp = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(5),
    ]);

    OpportunityItem::factory()->for($localOpp)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '3',
    ]);
    $peerItem = OpportunityItem::factory()->for($peerOpp)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '2',
    ]);

    $localShortage = app(ShortageDetector::class)->forItem(
        $localOpp->items()->first(),
        $localOpp,
    );
    $peerShortage = app(ShortageDetector::class)->forItem($peerItem, $peerOpp);
    $merged = $scanner->withPeerShortages($localOpp, new ShortageCollection([$localShortage]));

    expect($merged->contains(fn (Shortage $s): bool => $s->opportunityItemId === $peerItem->id))->toBeTrue()
        ->and($scanner->withPeerShortages($localOpp, new ShortageCollection([$localShortage, $peerShortage])))->toHaveCount(2);
});

it('PurchaseOrderTaxCalculator summarizeLines covers supplier guards and line resolution', function () {
    $calculator = app(PurchaseOrderTaxCalculator::class);
    $store = Store::factory()->create();

    $missingSupplierSummary = burnup4Invoke($calculator, 'summarizeLines', [
        999999,
        $store->id,
        'GBP',
        [],
        fn (): string => '1',
        null,
    ]);
    expect($missingSupplierSummary->totalTax)->toBe(0);

    $supplier = Member::factory()->organisation()->create(['purchase_tax_class_id' => null]);
    expect(burnup4Invoke($calculator, 'summarizeLines', [
        $supplier->id,
        $store->id,
        'GBP',
        [['unit_cost' => 1000, 'quantity' => '1', 'product_tax_class_id' => 1]],
        fn (): string => '1',
        null,
    ])->totalTax)->toBe(0);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $supplierWithTax = Member::factory()->organisation()->create(['purchase_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->create([
        'purchase_tax_class_id' => $productClass->id,
        'purchase_tax_category_id' => null,
    ]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => TaxRate::factory()->create(['rate' => '20.0000'])->id,
    ]);

    $summary = burnup4Invoke($calculator, 'summarizeLines', [
        $supplierWithTax->id,
        $store->id,
        'GBP',
        [
            ['product_id' => $product->id, 'unit_cost' => 1000, 'quantity' => '0'],
            ['product_id' => $product->id, 'unit_cost' => 1000, 'purchase_order_item_id' => 42],
            ['unit_cost' => 500, 'product_tax_class_id' => null, 'quantity' => '1'],
        ],
        fn (array $line): string => (string) ($line['quantity'] ?? '1'),
        null,
    ]);

    expect($summary->totalNet)->toBeGreaterThan(0);
});

it('OpportunitySection parent and children relationships are defined on the model', function () {
    $section = new OpportunitySection([
        'opportunity_id' => 1,
        'parent_id' => 2,
    ]);

    expect($section->parent())->toBeInstanceOf(BelongsTo::class)
        ->and($section->children())->toBeInstanceOf(HasMany::class);
});

it('DocumentSequencePolicy view and update enforce permissions and store access', function () {
    $policy = new DocumentSequencePolicy;
    $store = Store::factory()->create();
    $sequence = DocumentSequence::factory()->for($store)->create();

    $viewer = burnup4PolicyUser(['document_templates.view']);
    burnup4GrantStore($viewer, $store);
    expect($policy->view($viewer, $sequence))->toBeTrue()
        ->and($policy->view(burnup4PolicyUser(), $sequence))->toBeFalse()
        ->and($policy->view($viewer))->toBeTrue();

    $editor = burnup4PolicyUser(['document_templates.edit']);
    burnup4GrantStore($editor, $store);
    expect($policy->update($editor, $sequence))->toBeTrue()
        ->and($policy->update(burnup4PolicyUser(), $sequence))->toBeFalse()
        ->and($policy->update($editor))->toBeTrue();
});

it('VirtualStockIntakePolicy view and manageCostOverrides enforce status and permissions', function () {
    $this->actingAs(User::factory()->owner()->create());

    $policy = new VirtualStockIntakePolicy;
    $store = Store::factory()->create();
    $intake = createTestVirtualStockIntake(['store_id' => $store->id]);
    app(ConfirmVirtualStockIntake::class)($intake);
    $intake = $intake->fresh();

    $viewer = burnup4PolicyUser(['virtual_stock.view']);
    burnup4GrantStore($viewer, $store);
    expect($policy->view($viewer, $intake))->toBeTrue()
        ->and($policy->view(burnup4PolicyUser(['virtual_stock.view']), $intake))->toBeFalse()
        ->and($policy->view(burnup4PolicyUser(), $intake))->toBeFalse();

    $editor = burnup4PolicyUser(['costs.view', 'virtual_stock.edit']);
    burnup4GrantStore($editor, $store);
    expect($policy->manageCostOverrides($editor, $intake))->toBeTrue()
        ->and($policy->manageCostOverrides($editor, $intake->fresh(['store'])->forceFill(['status' => 'draft'])))->toBeFalse()
        ->and($policy->manageCostOverrides(burnup4PolicyUser(['costs.view', 'virtual_stock.edit']), $intake))->toBeFalse();
});

it('SandboxedBladeCompiler compileForelse and compileEmpty cover malformed and expression branches', function () {
    $compiler = app(SandboxedBladeCompiler::class);

    expect(burnup4Invoke($compiler, 'compileForelse', ['($items as $item)']))->toContain('__empty_')
        ->and(burnup4Invoke($compiler, 'compileEmpty', [null]))->toContain('__empty_')
        ->and(burnup4Invoke($compiler, 'compileEmpty', ['($items)']))->toBe('<?php if(empty($items)): ?>');

    expect(fn () => burnup4Invoke($compiler, 'compileForelse', ['($bad']))
        ->toThrow(DocumentRenderException::class, 'Malformed @forelse');
});

it('CommunicationPreferences optedOutTypes and maskedEmail handle invalid preference shapes', function () {
    $service = app(CommunicationPreferences::class);
    $member = Member::factory()->contact()->create(['communication_preferences' => null]);

    expect($service->optedOutTypes($member))->toBe([]);

    $member->update(['communication_preferences' => ['opt_out' => 'not-an-array']]);
    expect($service->optedOutTypes($member->fresh()))->toBe([]);

    $member->emails()->create(['address' => '@example.com', 'is_primary' => true]);
    expect($service->maskedEmail($member->fresh()))->toBe('*@example.com');

    $member->emails()->where('is_primary', true)->update(['address' => 'user@example.com']);
    expect($service->maskedEmail($member->fresh()))->toBe('u***@example.com');
});

it('OpportunitySurchargeApplicator applyToItem and syncSnapshotRows cover override and snapshot paths', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $opportunity = Opportunity::factory()->for($store)->create();
    $product = Product::factory()->rental()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    $group = SurchargeGroup::factory()->defaultGroup()->create();
    OpportunitySurchargeGroup::factory()->create([
        'opportunity_id' => $opportunity->id,
        'surcharge_group_id' => $group->id,
        'is_active' => true,
    ]);
    $surcharge = Surcharge::factory()->fixed()->create(['surcharge_group_id' => $group->id]);

    $manual = OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $item->id,
        'surcharge_id' => $surcharge->id,
        'is_manual_override' => true,
        'amount' => 500,
        'name' => null,
    ]);

    $disabled = Surcharge::factory()->fixed()->create(['surcharge_group_id' => $group->id]);
    OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $item->id,
        'surcharge_id' => $disabled->id,
        'is_disabled' => true,
        'is_manual_override' => false,
    ]);

    $result = app(OpportunitySurchargeApplicator::class)->applyToItem(
        $item->fresh(),
        $opportunity->fresh(),
        10_000,
        'GBP',
        $product,
    );

    expect($result['surcharge_total'])->toBeGreaterThan(0)
        ->and($manual->fresh()->amount)->toBe(500)
        ->and(OpportunityItemSurcharge::query()->where('opportunity_item_id', $item->id)->count())->toBeGreaterThanOrEqual(1);

    SurchargeGroup::query()->update(['is_default' => false, 'is_active' => false]);
    OpportunitySurchargeGroup::query()->update(['is_active' => false]);
    OpportunityItemSurcharge::factory()->create([
        'opportunity_item_id' => $item->id,
        'is_manual_override' => false,
    ]);

    $cleared = app(OpportunitySurchargeApplicator::class)->applyToItem(
        $item->fresh(),
        $opportunity->fresh(),
        10_000,
        'GBP',
        null,
    );

    expect($cleared['surcharge_total'])->toBe(0)
        ->and(OpportunityItemSurcharge::query()->where('opportunity_item_id', $item->id)->where('is_manual_override', false)->count())->toBe(0);
});

it('ExportApiRequestLog handle exports CSV and applyStatusClassFilter filters status classes', function () {
    Storage::fake();
    $user = User::factory()->create();

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.ok',
        'method' => 'GET',
        'status_code' => 200,
        'created_at' => now(),
    ]);
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.bad',
        'method' => 'GET',
        'status_code' => 404,
        'created_at' => now(),
    ]);
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.fail',
        'method' => 'GET',
        'status_code' => 500,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'api', ['window' => '7d', 'status_class' => '2xx']))->handle();
    $twoXx = Storage::get(Cache::get("api-logs-export:{$user->id}"));
    expect($twoXx)->toContain('api.v1.ok')->not->toContain('api.v1.bad');

    (new ExportApiRequestLog($user->id, 'api', ['window' => '7d', 'status_class' => '4xx']))->handle();
    $fourXx = Storage::get(Cache::get("api-logs-export:{$user->id}"));
    expect($fourXx)->toContain('api.v1.bad')->not->toContain('api.v1.ok');

    (new ExportApiRequestLog($user->id, 'api', ['window' => '7d', 'status_class' => '5xx']))->handle();
    expect(Storage::get(Cache::get("api-logs-export:{$user->id}")))->toContain('api.v1.fail');

    (new ExportApiRequestLog($user->id, 'api', ['window' => '7d', 'status_class' => 'unknown']))->handle();
    expect(Storage::get(Cache::get("api-logs-export:{$user->id}")))->toContain('api.v1.ok');
});
