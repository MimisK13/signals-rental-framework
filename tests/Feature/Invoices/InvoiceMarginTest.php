<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Enums\TaxBuyerType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoices\InvoiceMarginCalculator;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

function imActAsOwner(): User
{
    $owner = User::factory()->owner()->create();
    \Pest\Laravel\actingAs($owner);

    return $owner;
}

/**
 * @return array{opportunity: Opportunity, product: Product}
 */
function imMarginedOrderOpportunity(int $supplierUnitCost = 6_000, int $deliveryCost = 2_000): array
{
    imActAsOwner();
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create([
        'tax_zone_id' => $zones['uk']->id,
        'country_code' => 'GB',
    ]);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
        'buyer_type' => TaxBuyerType::Individual->value,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Invoiced order',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new AddOpportunityCost)(Opportunity::findOrFail($created->id), AddOpportunityCostData::from([
        'description' => 'Delivery',
        'amount' => $deliveryCost,
        'quantity' => '1',
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost' => $supplierUnitCost,
        'cost_basis' => 'per_unit',
        'currency_code' => 'GBP',
    ]);

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp(
        $opportunity->fresh(['items.itemSurcharges', 'costs', 'member']),
    );

    return [
        'opportunity' => $opportunity->fresh(['items', 'costs', 'store']),
        'product' => $product,
    ];
}

/**
 * @return array{opportunity: Opportunity, item: OpportunityItem}
 */
function imTenUnitMarginedOpportunity(int $supplierUnitCost = 600, int $deliveryCost = 2_000): array
{
    imActAsOwner();
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost' => $supplierUnitCost,
        'cost_basis' => 'per_unit',
        'currency_code' => 'GBP',
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Margin partial qty',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '10',
        'unit_price' => 1_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Delivery',
        'amount' => $deliveryCost,
        'quantity' => '1',
    ]));

    app(OpportunityTotalsCalculator::class)->rollUp(
        $opportunity->fresh(['items.itemSurcharges', 'costs', 'member']),
    );

    $opportunity->refresh();

    return [
        'opportunity' => $opportunity->fresh(['items', 'costs', 'store']),
        'item' => $opportunity->items->first(),
    ];
}

function imCostsViewUser(int $storeId): User
{
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $user->givePermissionTo(['invoices.access', 'invoices.view', 'costs.view']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $storeId,
        'created_at' => now(),
    ]);

    return $user;
}

/**
 * @param  list<string>  $abilities
 */
function imInvoiceApiToken(User $user, array $abilities = ['invoices:read']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('InvoiceMarginCalculator', function () {
    it('computes full-invoice margins with supplier and matched cost lines', function () {
        Queue::fake();
        ['opportunity' => $opportunity] = imMarginedOrderOpportunity();

        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->with(['items', 'opportunity.costs', 'store'])->findOrFail($draft->id);

        $margin = app(InvoiceMarginCalculator::class)->calculate($invoice);
        $productLine = $invoice->items->first(fn ($item) => $item->product_id !== null);
        $costLine = $invoice->items->first(fn ($item) => $item->product_id === null);

        expect($margin->net_revenue_minor)->toBe(12_000)
            ->and($margin->attributable_cost_minor)->toBe(8_000)
            ->and($margin->margin_minor)->toBe(4_000)
            ->and($margin->margin_percent)->toBe('33.3%')
            ->and($margin->line((int) $productLine->id)?->margin_minor)->toBe(4_000)
            ->and($margin->line((int) $productLine->id)?->margin_percent)->toBe('40%')
            ->and($margin->line((int) $costLine->id)?->margin_minor)->toBe(0)
            ->and($margin->line((int) $costLine->id)?->margin_percent)->toBe('0%');
    });

    it('proportionally scales unattributed opportunity costs on partial invoices', function () {
        Queue::fake();
        ['opportunity' => $opportunity, 'item' => $item] = imTenUnitMarginedOpportunity();

        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
            'lines' => [
                ['opportunity_item_id' => $item->id, 'quantity' => '5'],
            ],
        ]));
        $invoice = Invoice::query()->with(['items', 'opportunity.costs', 'store'])->findOrFail($draft->id);

        $margin = app(InvoiceMarginCalculator::class)->calculate($invoice);

        expect($invoice->subtotal)->toBe(5_000)
            ->and($margin->net_revenue_minor)->toBe(5_000)
            ->and($margin->attributable_cost_minor)->toBe(3_833)
            ->and($margin->margin_minor)->toBe(1_167)
            ->and($margin->margin_percent)->toBe('23.3%');
    });

    it('returns no basis for lines without supplier cost data', function () {
        Queue::fake();
        imActAsOwner();
        $opportunity = giCreateOrderOpportunity();

        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->with(['items', 'opportunity.costs', 'store'])->findOrFail($draft->id);

        $margin = app(InvoiceMarginCalculator::class)->calculate($invoice);
        $line = $invoice->items->first();

        expect($margin->line((int) $line->id)?->has_basis)->toBeFalse();
    });
});

describe('invoice show margin UI', function () {
    it('shows computed margin figures for costs.view users', function () {
        Queue::fake();
        ['opportunity' => $opportunity] = imMarginedOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);

        $this->actingAs(imCostsViewUser((int) $invoice->store_id));

        Volt::test('invoices.show', ['invoice' => $invoice])
            ->assertSee('Margin')
            ->assertSee('40%')
            ->assertSee('33.3%');
    });

    it('hides margin column without costs.view permission', function () {
        Queue::fake();
        ['opportunity' => $opportunity] = imMarginedOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);

        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
        $user->givePermissionTo(['invoices.access', 'invoices.view']);
        MemberStore::create([
            'member_id' => $member->id,
            'store_id' => $invoice->store_id,
            'created_at' => now(),
        ]);
        $this->actingAs($user);

        Volt::test('invoices.show', ['invoice' => $invoice])
            ->assertDontSee('Margin')
            ->assertDontSee('40%');
    });

    it('shows an em dash for lines without resolvable cost basis', function () {
        Queue::fake();
        imActAsOwner();
        $opportunity = giCreateOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);

        $this->actingAs(imCostsViewUser((int) $invoice->store_id));

        Volt::test('invoices.show', ['invoice' => $invoice])
            ->assertSee('title="No cost data"', false);
    });
});

describe('invoice API margin fields', function () {
    it('exposes margin fields when the caller has costs.view', function () {
        Queue::fake();
        ['opportunity' => $opportunity] = imMarginedOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);

        $user = imCostsViewUser((int) $invoice->store_id);
        $user->givePermissionTo(['invoices.view', 'costs.view']);
        $token = imInvoiceApiToken($user);

        auth()->logout();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices/{$invoice->id}?include=items")
            ->assertOk();

        expect($response->json('invoice.margin'))->toBe('40.00')
            ->and($response->json('invoice.margin_percent'))->toBe('33.3%')
            ->and($response->json('invoice.attributable_cost_total'))->toBe('80.00')
            ->and($response->json('invoice.items.0.margin'))->toBe('40.00')
            ->and($response->json('invoice.items.0.margin_percent'))->toBe('40%');
    });

    it('strips margin fields without costs.view', function () {
        Queue::fake();
        ['opportunity' => $opportunity] = imMarginedOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);

        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
        $user->givePermissionTo(['invoices.view']);
        MemberStore::create([
            'member_id' => $member->id,
            'store_id' => $invoice->store_id,
            'created_at' => now(),
        ]);
        $token = imInvoiceApiToken($user);

        auth()->logout();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices/{$invoice->id}?include=items")
            ->assertOk();

        expect($response->json('invoice'))->not->toHaveKeys(['margin', 'margin_percent', 'attributable_cost_total'])
            ->and($response->json('invoice.items.0'))->not->toHaveKeys(['margin', 'margin_percent']);
    });
});
