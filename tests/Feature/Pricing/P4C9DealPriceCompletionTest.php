<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\ClearSectionDealPrice;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\DealPriceMode;
use App\Enums\PricingRoundingDirection;
use App\Enums\RateTransactionType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\User;
use App\Services\Pricing\OpportunityItemSurchargeAmountFilterRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/** @param array<string, mixed> $overrides */
function p4c9Opportunity(array $overrides = []): Opportunity
{
    $member = Member::factory()->organisation()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
        'subject' => 'P4C9 deal price completion',
        'member_id' => $member->id,
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ], $overrides)));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

function p4c9AddManualLine(Opportunity $opportunity, string $name, int $unitPriceMinor, string $quantity = '1'): OpportunityItem
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

function p4c9AddGroup(Opportunity $opportunity, string $name = 'Lighting'): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $name,
        'item_type' => 'group',
    ]));

    return $opportunity->fresh('items')->items->first(fn (OpportunityItem $item): bool => $item->item_type->value === 'group')
        ?? $opportunity->items()->where('item_type', 'group')->latest('id')->firstOrFail();
}

function p4c9NestLineUnderGroup(Opportunity $opportunity, OpportunityItem $group, string $name, int $unitPriceMinor): OpportunityItem
{
    $line = p4c9AddManualLine($opportunity->fresh(), $name, $unitPriceMinor);
    $line->forceFill(['path' => $group->path.'0001'])->saveQuietly();

    return $line->fresh();
}

function p4c9FlatProduct(int $priceMinor = 10_000): Product
{
    $product = Product::factory()->rental()->create();
    $definition = RateDefinition::factory()->create([
        'calculation_strategy' => CalculationStrategyType::Fixed,
        'base_period' => BasePeriod::Daily,
        'enabled_modifiers' => [],
        'strategy_config' => [],
        'modifier_configs' => [],
    ]);

    ProductRate::factory()->for($product)->create([
        'rate_definition_id' => $definition->id,
        'transaction_type' => RateTransactionType::Rental,
        'price' => $priceMinor,
    ]);

    return $product;
}

it('clears all active group deal prices when setting an opportunity-level deal price', function () {
    $opportunity = p4c9Opportunity();
    $group = p4c9AddGroup($opportunity);
    p4c9NestLineUnderGroup($opportunity->fresh(), $group, 'Inside', 10_000);
    p4c9AddManualLine($opportunity->fresh(), 'Outside', 20_000);

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '50.00',
    ]));

    expect($group->fresh()->deal_price)->toBe(5_000);

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '200.00',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    $opportunity->refresh();
    $group->refresh();

    expect($opportunity->deal_total)->toBe(20_000)
        ->and($group->deal_price)->toBeNull()
        ->and($group->deal_price_original_total)->toBeNull()
        ->and(OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->whereNotNull('deal_price')
            ->count())->toBe(0);
});

it('replays opportunity-level deal price set after clearing group deal prices with byte-identical totals', function () {
    $opportunity = p4c9Opportunity();
    $group = p4c9AddGroup($opportunity);
    p4c9NestLineUnderGroup($opportunity->fresh(), $group, 'Inside', 10_000);
    p4c9AddManualLine($opportunity->fresh(), 'Outside', 10_000);

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '40.00',
    ]));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '150.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    $opportunity->refresh();

    $snapshot = [
        'opportunity' => [
            'deal_total' => $opportunity->deal_total,
            'deal_price_mode' => $opportunity->deal_price_mode,
            'charge_total' => $opportunity->charge_total,
        ],
        'groups' => $opportunity->items()
            ->where('item_type', 'group')
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price' => $item->deal_price,
                'deal_price_original_total' => $item->deal_price_original_total,
            ])
            ->all(),
        'items' => $opportunity->items()
            ->where('item_type', '!=', 'group')
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price_discount_percent' => $item->deal_price_discount_percent,
                'total' => $item->total,
            ])
            ->all(),
    ];

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $opportunity = Opportunity::query()->findOrFail($opportunity->id);

    expect([
        'deal_total' => $opportunity->deal_total,
        'deal_price_mode' => $opportunity->deal_price_mode,
        'charge_total' => $opportunity->charge_total,
    ])->toBe($snapshot['opportunity'])
        ->and($opportunity->items()
            ->where('item_type', 'group')
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price' => $item->deal_price,
                'deal_price_original_total' => $item->deal_price_original_total,
            ])
            ->all())->toBe($snapshot['groups'])
        ->and($opportunity->items()
            ->where('item_type', '!=', 'group')
            ->orderBy('id')
            ->get()
            ->map(fn (OpportunityItem $item): array => [
                'deal_price_discount_percent' => $item->deal_price_discount_percent,
                'total' => $item->total,
            ])
            ->all())->toBe($snapshot['items']);
});

it('clears a section deal price and restores scoped line totals', function () {
    $opportunity = p4c9Opportunity();
    $group = p4c9AddGroup($opportunity);
    $inside = p4c9NestLineUnderGroup($opportunity->fresh(), $group, 'Inside', 10_000);
    p4c9AddManualLine($opportunity->fresh(), 'Outside', 20_000);

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '50.00',
    ]));

    $chargeWithDeal = (int) $opportunity->fresh()->charge_total;

    (new ClearSectionDealPrice)($opportunity->fresh(), $group->fresh());

    $group->refresh();
    $inside->refresh();
    $opportunity->refresh();

    expect($group->deal_price)->toBeNull()
        ->and($inside->deal_price_discount_percent)->toBeNull()
        ->and($opportunity->charge_total)->toBeGreaterThan($chargeWithDeal);
});

describe('section deal price clear API', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test', ['opportunities:write'])->plainTextToken;
    });

    /** @return array{0: Opportunity, 1: OpportunityItem} */
    function p4c9ApiGroupWithDeal(): array
    {
        Auth::login(test()->owner);

        try {
            $opportunity = p4c9Opportunity();
            $group = p4c9AddGroup($opportunity);
            p4c9NestLineUnderGroup($opportunity->fresh(), $group, 'Fixture', 10_000);

            (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
                'deal_price' => '75.00',
            ]));

            return [$opportunity->fresh(), $group->fresh()];
        } finally {
            Auth::logout();
        }
    }

    it('clears section deal price via DELETE /sections/{id}/deal_price', function () {
        [$opportunity, $group] = p4c9ApiGroupWithDeal();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/sections/{$group->id}/deal_price")
            ->assertOk()
            ->assertJsonPath('opportunity.deal_total', null);

        expect($group->fresh()->deal_price)->toBeNull();
    });

    it('forbids clearing section deal price without write ability', function () {
        [$opportunity, $group] = p4c9ApiGroupWithDeal();
        $readToken = $this->owner->createToken('read', ['opportunities:read'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$readToken)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/sections/{$group->id}/deal_price")
            ->assertForbidden();
    });

    it('returns not found when the section does not exist', function () {
        [$opportunity] = p4c9ApiGroupWithDeal();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/sections/999999/deal_price")
            ->assertNotFound();
    });

    it('returns validation error when the section has no active deal price', function () {
        $opportunity = p4c9Opportunity();
        $group = p4c9AddGroup($opportunity);

        Auth::login($this->owner);
        try {
            // no deal price set
        } finally {
            Auth::logout();
        }

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/sections/{$group->id}/deal_price")
            ->assertUnprocessable();
    });
});

describe('opportunity deal price clear API', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->owner = User::factory()->owner()->create();
        $this->token = $this->owner->createToken('test', ['opportunities:write'])->plainTextToken;
    });

    it('clears opportunity deal price via DELETE /deal_price', function () {
        Auth::login($this->owner);
        try {
            $opportunity = p4c9Opportunity();
            p4c9AddManualLine($opportunity, 'Line', 10_000);
            (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
                'deal_total' => '80.00',
                'deal_price_mode' => DealPriceMode::Proportional->value,
            ]));
        } finally {
            Auth::logout();
        }

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/deal_price")
            ->assertOk();

        expect($opportunity->fresh()->deal_total)->toBeNull();
    });

    it('forbids clearing opportunity deal price without write ability', function () {
        Auth::login($this->owner);
        try {
            $opportunity = p4c9Opportunity();
            p4c9AddManualLine($opportunity, 'Line', 10_000);
            (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
                'deal_total' => '80.00',
            ]));
        } finally {
            Auth::logout();
        }

        $readToken = $this->owner->createToken('read-only', ['opportunities:read'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$readToken)
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/deal_price")
            ->assertForbidden();
    });
});

it('applies a surcharge amount filter hook before per-line rounding', function () {
    app(OpportunityItemSurchargeAmountFilterRegistry::class)->register(
        'test-halve',
        fn (OpportunityItem $item, string $rawAmountMinor, int $surchargeId): string => bcdiv($rawAmountMinor, '2', 10),
    );

    $product = p4c9FlatProduct(10_000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $opportunity = p4c9Opportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    expect($item->surcharge_total)->toBe(500);
});

it('allows a surcharge amount filter to veto a line surcharge', function () {
    app(OpportunityItemSurchargeAmountFilterRegistry::class)->register(
        'test-veto',
        fn (OpportunityItem $item, string $rawAmountMinor, int $surchargeId): ?string => null,
    );

    $product = p4c9FlatProduct(10_000);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $opportunity = p4c9Opportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    expect($opportunity->fresh('items')->items->firstOrFail()->surcharge_total)->toBe(0);
});

it('respects rounding after a surcharge amount filter adjusts the raw amount', function () {
    app(OpportunityItemSurchargeAmountFilterRegistry::class)->register(
        'test-bump',
        fn (OpportunityItem $item, string $rawAmountMinor, int $surchargeId): string => bcadd($rawAmountMinor, '0.4', 10),
    );

    $product = p4c9FlatProduct(10_003);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $opportunity = p4c9Opportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $item = $opportunity->fresh('items')->items->firstOrFail();

    expect($item->total)->toBe(10_003)
        ->and($item->surcharge_total)->toBe(1_001);
});

it('rejects clearing a section deal price on a non-group line', function () {
    $opportunity = p4c9Opportunity();
    $line = p4c9AddManualLine($opportunity, 'Not a group', 5_000);

    expect(fn () => (new ClearSectionDealPrice)($opportunity->fresh(), $line))
        ->toThrow(ValidationException::class);
});

it('clears opportunity deal price via action and recalculates totals', function () {
    $opportunity = p4c9Opportunity();
    p4c9AddManualLine($opportunity, 'Line A', 10_000);
    p4c9AddManualLine($opportunity->fresh(), 'Line B', 10_000);

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '150.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    $discountedTotal = (int) $opportunity->fresh()->charge_total;

    (new ClearDealPrice)($opportunity->fresh());

    expect($opportunity->fresh()->deal_total)->toBeNull()
        ->and($opportunity->fresh()->charge_total)->toBeGreaterThan($discountedTotal);
});
