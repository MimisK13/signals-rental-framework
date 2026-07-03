<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RecalculateOpportunity;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Contracts\Pricing\DealPriceValidator;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RecalculateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Enums\DealPriceMode;
use App\Models\ActionLog;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Pricing\DealPriceValidatorRegistry;
use App\Services\Pricing\OpportunityItemDiscountPercentFilterRegistry;
use App\Services\Pricing\OpportunityPricingCalculateDecorator;
use App\Verbs\Events\Opportunities\DealPriceSet;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function p11Opportunity(array $overrides = []): Opportunity
{
    $member = Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
        'subject' => 'P11 deal price',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ], $overrides)));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

function p11AddManualLine(Opportunity $opportunity, string $name, int $unitPriceMinor, string $quantity = '1'): OpportunityItem
{
    $beforeIds = $opportunity->items()->pluck('id')->all();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $name,
        'quantity' => $quantity,
        'unit_price' => $unitPriceMinor,
    ]));

    return $opportunity->fresh('items')->items
        ->first(fn (OpportunityItem $item): bool => ! in_array($item->id, $beforeIds, true))
        ?? $opportunity->items()->latest('id')->firstOrFail();
}

function p11AddProductLine(Opportunity $opportunity, Product $product, array $overrides = []): OpportunityItem
{
    $beforeIds = $opportunity->items()->pluck('id')->all();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(array_merge([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 5000,
    ], $overrides)));

    return $opportunity->fresh('items')->items
        ->first(fn (OpportunityItem $item): bool => ! in_array($item->id, $beforeIds, true))
        ?? $opportunity->items()->latest('id')->firstOrFail();
}

function p11LineNetSum(Opportunity $opportunity): int
{
    return (int) $opportunity->fresh('items')->items
        ->filter(fn (OpportunityItem $item): bool => ! $item->is_optional && $item->item_type->isPriceable())
        ->sum(fn (OpportunityItem $item): int => (int) $item->total + (int) $item->surcharge_total);
}

it('applies flat headline deal price penny-exactly on the opportunity headline', function () {
    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'Line A', 10_000);
    p11AddManualLine($opportunity->fresh(), 'Line B', 20_000);

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '150.00',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    $opportunity->refresh();

    expect($opportunity->deal_price_mode)->toBe(DealPriceMode::FlatHeadline->value)
        ->and($opportunity->deal_total)->toBe(15_000)
        ->and($opportunity->charge_total)->toBe(15_000)
        ->and(p11LineNetSum($opportunity))->toBe(30_000);
});

it('distributes proportional deal price penny-exactly across adjustable lines', function () {
    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'Line A', 10_000);
    p11AddManualLine($opportunity->fresh(), 'Line B', 10_000);
    p11AddManualLine($opportunity->fresh(), 'Line C', 10_000);

    $originalTotal = (int) $opportunity->fresh()->charge_total;

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '100.01',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    $opportunity->refresh();

    expect($opportunity->deal_price_mode)->toBe(DealPriceMode::Proportional->value)
        ->and($opportunity->deal_total)->toBe(10_001)
        ->and($opportunity->deal_price_original_total)->toBe($originalTotal)
        ->and($opportunity->charge_total)->toBe(10_001)
        ->and(p11LineNetSum($opportunity))->toBe(10_001);

    $discountableLines = $opportunity->items->filter(
        fn (OpportunityItem $item): bool => $item->deal_price_discount_percent !== null,
    );

    expect($discountableLines)->toHaveCount(3);
});

it('replays legacy DealPriceSet payloads as flat headline mode', function () {
    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'Legacy line', 12_500);

    DealPriceSet::fire(
        opportunity_id: $opportunity->state_id,
        deal_total: 9_999,
    );

    $opportunity->refresh();

    $snapshot = [
        'deal_total' => $opportunity->deal_total,
        'deal_price_mode' => $opportunity->deal_price_mode,
        'charge_total' => $opportunity->charge_total,
    ];

    Verbs::replay();

    $opportunity = Opportunity::query()->findOrFail($opportunity->id);

    expect([
        'deal_total' => $opportunity->deal_total,
        'deal_price_mode' => $opportunity->deal_price_mode,
        'charge_total' => $opportunity->charge_total,
    ])->toBe($snapshot)
        ->and($opportunity->deal_price_mode)->toBe(DealPriceMode::FlatHeadline->value);
});

it('blocks proportional deal prices below the non-discountable total', function () {
    $opportunity = p11Opportunity();

    $discountable = Product::factory()->rental()->create(['discountable' => true]);
    $locked = Product::factory()->rental()->create(['discountable' => false]);

    p11AddProductLine($opportunity, $discountable, ['unit_price' => 5_000]);
    p11AddProductLine($opportunity->fresh(), $locked, ['unit_price' => 8_000]);

    expect(fn () => (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '50.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ])))->toThrow(ValidationException::class);
});

it('excludes manually discounted lines from proportional redistribution', function () {
    $opportunity = p11Opportunity();
    $manual = p11AddManualLine($opportunity, 'Manual', 10_000);
    $auto = p11AddManualLine($opportunity->fresh(), 'Auto', 10_000);

    (new SetItemDiscount)($manual, SetItemDiscountData::from(['discount_percent' => '5.00']));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '150.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    $manual->refresh();
    $auto->refresh();

    expect($manual->is_discount_manual)->toBeTrue()
        ->and($manual->deal_price_discount_percent)->toBeNull()
        ->and($auto->deal_price_discount_percent)->not->toBeNull();
});

it('respects manual discounts when recalculating without reset_discounts', function () {
    $opportunity = p11Opportunity();
    $item = p11AddManualLine($opportunity, 'Manual', 10_000);

    (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '12.50']));

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => false,
    ]));

    $item->refresh();

    expect($item->discount_percent)->toBe('12.50')
        ->and($item->is_discount_manual)->toBeTrue();
});

it('resets manual discounts when recalculating with reset_discounts', function () {
    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '10.00']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);

    $opportunity = p11Opportunity(['member_id' => $member->id]);
    $item = p11AddManualLine($opportunity, 'Manual', 10_000);

    (new SetItemDiscount)($item, SetItemDiscountData::from(['discount_percent' => '12.50']));

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => true,
    ]));

    $item->refresh();

    expect($item->is_discount_manual)->toBeFalse()
        ->and($item->discount_percent)->toBe('10.00');

    expect(ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.discount_reset')
        ->exists())->toBeTrue();
});

it('blocks deal pricing via a registered validator hook', function () {
    app(DealPriceValidatorRegistry::class)->register(new class implements DealPriceValidator
    {
        public function key(): string
        {
            return 'test-blocker';
        }

        public function validate(Opportunity $opportunity, int $dealTotalMinor, ?OpportunityItem $groupItem = null): void
        {
            if ($dealTotalMinor < 10_000) {
                throw ValidationException::withMessages([
                    'deal_total' => 'Blocked by plugin validator.',
                ]);
            }
        }
    });

    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'Line', 20_000);

    expect(fn () => (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '50.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ])))->toThrow(ValidationException::class, 'Blocked by plugin validator.');
});

it('runs pricing through the opportunity.pricing.calculate decorator seam', function () {
    $decorator = app(OpportunityPricingCalculateDecorator::class);
    $calls = [];

    $decorator->registerBefore('test-before', function (Opportunity $opportunity, $next) use (&$calls): void {
        $calls[] = 'before';
        $next();
    });

    $decorator->registerAfter('test-after', function (Opportunity $opportunity, $next) use (&$calls): void {
        $calls[] = 'after';
    });

    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'Line', 10_000);

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from());

    expect($calls)->toBe(['before', 'after']);
});

it('applies a discount percent filter hook before deal distribution baselines', function () {
    app(OpportunityItemDiscountPercentFilterRegistry::class)->register(
        'test-cap',
        fn (OpportunityItem $item, ?string $percent): ?string => $percent !== null && bccomp($percent, '25', 2) > 0 ? '25.00' : $percent,
    );

    $category = DiscountCategory::factory()->create();
    DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '40.00']);

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $category->id,
    ]);

    $opportunity = p11Opportunity(['member_id' => $member->id]);
    p11AddManualLine($opportunity, 'Discounted', 10_000);

    expect($opportunity->fresh()->charge_total)->toBe(7_500);

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '60.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    expect($opportunity->fresh()->charge_total)->toBe(6_000);
});

it('sets a section deal price and distributes within the group scope', function () {
    $opportunity = p11Opportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Lighting',
        'item_type' => 'group',
    ]));
    $group = $opportunity->items()->where('item_type', 'group')->firstOrFail();

    p11AddManualLine($opportunity->fresh(), 'Inside', 10_000);
    $inside = $opportunity->fresh('items')->items->last();
    $inside->forceFill(['path' => $group->path.'0001'])->saveQuietly();

    p11AddManualLine($opportunity->fresh(), 'Outside', 20_000, '1');

    (new SetSectionDealPrice)($opportunity->fresh(), $group, SetSectionDealPriceData::from([
        'deal_price' => '50.00',
    ]));

    $group->refresh();
    $opportunity->refresh();

    expect($group->deal_price)->toBe(5_000)
        ->and($group->deal_price_original_total)->toBeGreaterThan(0)
        ->and($opportunity->deal_total)->toBeNull();

    $inside = OpportunityItem::query()->where('path', $group->path.'0001')->firstOrFail();
    $outside = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->name === 'Outside');

    expect($inside->deal_price_discount_percent)->not->toBeNull()
        ->and($outside->deal_price_discount_percent)->toBeNull();
});

it('rebuilds identical proportional deal pricing after full replay', function () {
    $opportunity = p11Opportunity();
    p11AddManualLine($opportunity, 'A', 10_000);
    p11AddManualLine($opportunity->fresh(), 'B', 15_000);
    p11AddManualLine($opportunity->fresh(), 'C', 20_000);

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '333.33',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => false,
    ]));

    $opportunity->refresh();

    $snapshot = [
        'opportunity' => [
            'deal_total' => $opportunity->deal_total,
            'deal_price_mode' => $opportunity->deal_price_mode,
            'deal_price_original_total' => $opportunity->deal_price_original_total,
            'charge_total' => $opportunity->charge_total,
        ],
        'items' => $opportunity->items()
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price_discount_percent' => $item->deal_price_discount_percent,
                'total' => $item->total,
            ])
            ->all(),
    ];

    Verbs::replay();

    $opportunity = Opportunity::query()->findOrFail($opportunity->id);

    expect([
        'deal_total' => $opportunity->deal_total,
        'deal_price_mode' => $opportunity->deal_price_mode,
        'deal_price_original_total' => $opportunity->deal_price_original_total,
        'charge_total' => $opportunity->charge_total,
    ])->toBe($snapshot['opportunity'])
        ->and($opportunity->items()
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price_discount_percent' => $item->deal_price_discount_percent,
                'total' => $item->total,
            ])
            ->all())->toBe($snapshot['items']);
});

describe('API endpoints', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test', ['opportunities:write'])->plainTextToken;
    });

    function p11ApiOpportunity(): Opportunity
    {
        Auth::login(test()->owner);

        try {
            return p11Opportunity();
        } finally {
            Auth::logout();
        }
    }

    function p11ApiAddLine(Opportunity $opportunity): void
    {
        Auth::login(test()->owner);

        try {
            p11AddManualLine($opportunity, 'Line', 10_000);
        } finally {
            Auth::logout();
        }
    }

    it('recalculates pricing via POST /recalculate', function () {
        $opportunity = p11ApiOpportunity();
        p11ApiAddLine($opportunity);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/recalculate", [
                'reset_prices' => false,
                'reset_discounts' => false,
            ])
            ->assertOk();

        expect($response->json('opportunity.charge_total'))->toBe('100.00');

        expect(ActionLog::query()
            ->where('auditable_type', Opportunity::class)
            ->where('auditable_id', $opportunity->id)
            ->where('action', 'opportunity.pricing_recalculated')
            ->exists())->toBeTrue();
    });

    it('sets section deal price via PUT /sections/{id}/deal_price', function () {
        $opportunity = p11ApiOpportunity();

        Auth::login($this->owner);
        try {
            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
                'name' => 'Stage',
                'item_type' => 'group',
            ]));
            p11AddManualLine($opportunity->fresh(), 'Fixture', 10_000);
        } finally {
            Auth::logout();
        }

        $group = $opportunity->fresh('items')->items()->where('item_type', 'group')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/opportunities/{$opportunity->id}/sections/{$group->id}/deal_price", [
                'deal_price' => '75.00',
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.deal_total', null);

        expect($group->fresh()->deal_price)->toBe(7_500);
    });

    it('accepts proportional deal_price_mode on POST /deal_price', function () {
        $opportunity = p11ApiOpportunity();
        Auth::login($this->owner);
        try {
            p11AddManualLine($opportunity, 'A', 10_000);
            p11AddManualLine($opportunity->fresh(), 'B', 10_000);
        } finally {
            Auth::logout();
        }

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/deal_price", [
                'deal_total' => '100.00',
                'deal_price_mode' => 'proportional',
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.charge_total', '100.00');

        expect($opportunity->fresh()->deal_price_mode)->toBe('proportional');
    });
});
