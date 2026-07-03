<?php

use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Documents\OpportunityDataResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

function documentResolverOpportunity(array $overrides = []): Opportunity
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create([
        'timezone' => 'Europe/London',
        'street' => '14 Warehouse Lane',
        'city' => 'Manchester',
        'postcode' => 'M1 2AB',
    ]);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
        'tax_number' => 'GB123456789',
    ]);

    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create(array_merge([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'currency_code' => 'GBP',
        'state' => OpportunityState::Quotation,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ], $overrides));

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Test Product Line',
        'quantity' => '2',
        'unit_price' => 5_000,
        'total' => 10_000,
    ]);

    OpportunityCost::factory()->create([
        'opportunity_id' => $opportunity->id,
        'amount' => 0,
        'is_optional' => true,
    ]);

    return $opportunity->refresh();
}

describe('OpportunityDataResolver output', function () {
    it('formats money columns as decimal strings', function () {
        $opportunity = documentResolverOpportunity();
        $resolver = app(OpportunityDataResolver::class);
        $data = $resolver->resolve($resolver->loadSource($opportunity->id));

        expect($data['opportunity']['charge_total'])->toBe('100.00')
            ->and($data['opportunity']['tax_total'])->toBe('20.00')
            ->and($data['opportunity']['grand_total'])->toBe('120.00')
            ->and($data['items'][0]['unit_price'])->toBe('50.00')
            ->and($data['items'][0]['total'])->toBe('100.00');
    });

    it('renders enum values as labels and dates in the store timezone', function () {
        $opportunity = documentResolverOpportunity();
        $resolver = app(OpportunityDataResolver::class);
        $data = $resolver->resolve($resolver->loadSource($opportunity->id));

        expect($data['opportunity']['state_label'])->toBe('Quotation')
            ->and($data['opportunity']['starts_at'])->toBe('01/07/2026')
            ->and($data['items'][0]['transaction_type_label'])->toBe('Sale');
    });

    it('derives line discount from fractional quantity gross without rounding quantity up', function () {
        $opportunity = documentResolverOpportunity();
        $item = $opportunity->items->first();
        $item->update([
            'quantity' => '1.5',
            'unit_price' => 10_000,
            'total' => 15_000,
        ]);

        $resolver = app(OpportunityDataResolver::class);
        $data = $resolver->resolve($resolver->loadSource($opportunity->id));

        expect($data['items'][0]['quantity'])->toBe('1.50')
            ->and($data['items'][0]['discount'])->toBe('0.00')
            ->and($data['items'][0]['total'])->toBe('150.00');
    });

    it('includes a tax summary with grouped decimal-string totals', function () {
        $opportunity = documentResolverOpportunity();
        $resolver = app(OpportunityDataResolver::class);
        $data = $resolver->resolve($resolver->loadSource($opportunity->id));

        expect($data['tax_summary']['total_net'])->toMatch('/^\d+\.\d{2}$/')
            ->and($data['tax_summary']['total_tax'])->toMatch('/^\d+\.\d{2}$/')
            ->and($data['tax_summary']['groups'])->not->toBeEmpty()
            ->and($data['tax_summary']['groups'][0]['net_total'])->toMatch('/^\d+\.\d{2}$/')
            ->and($data['tax_summary']['groups'][0])->toHaveKeys([
                'result_type',
                'legal_text',
                'net_total',
                'tax_total',
                'rate_percentage',
                'tax_rate_name',
            ])
            ->and($data['tax_summary']['groups'][0]['tax_rate_name'])->not->toBeEmpty();
    });
});

describe('OpportunityDataResolver loading', function () {
    it('does not use the DB facade', function () {
        $source = file_get_contents((new ReflectionClass(OpportunityDataResolver::class))->getFileName());

        expect($source)->not->toContain('DB::')
            ->and($source)->not->toContain('Illuminate\\Support\\Facades\\DB');
    });

    it('uses a fixed eager-loaded query count regardless of line-item volume', function () {
        $resolver = app(OpportunityDataResolver::class);

        $small = documentResolverOpportunity();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $resolver->loadSource($small->id);
        $smallCount = count(DB::getQueryLog());

        $large = documentResolverOpportunity();
        OpportunityItem::factory()->sale()->count(8)->create([
            'opportunity_id' => $large->id,
            'itemable_id' => Product::factory()->sale()->create()->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 1_000,
            'total' => 1_000,
        ]);

        DB::flushQueryLog();
        $resolver->loadSource($large->id);
        $largeCount = count(DB::getQueryLog());

        expect($largeCount)->toBe($smallCount);
    });
});
