<?php

use App\Enums\BasePeriod;
use App\Enums\CalculationStrategyType;
use App\Enums\LineItemTransactionType;
use App\Enums\RateTransactionType;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Sanctum::actingAs(User::factory()->owner()->create(), ['opportunities:read', 'opportunities:write']);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * @return array{0: Opportunity, 1: int}
 */
function wireOpportunityWithLine(TestCase $test, Store $store): array
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
        'price' => 5000,
    ]);

    $opportunity = Opportunity::factory()->for($store)->create([
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ]);

    $response = $test->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ])->assertCreated();

    $itemId = collect($response->json('opportunity.items'))->first()['id'];

    return [$opportunity->fresh(), $itemId];
}

it('toggles a surcharge group on an opportunity via the API', function () {
    [$opportunity] = wireOpportunityWithLine($this, $this->store);
    $group = SurchargeGroup::factory()->create(['is_default' => false]);
    Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/toggle_surcharge_group", [
        'surcharge_group_id' => $group->id,
        'is_active' => true,
    ])->assertSuccessful();

    $item = $opportunity->fresh('items')->items->first();

    expect($item->surcharge_total)->toBe(500);
});

it('overrides a line surcharge amount via the API', function () {
    [$opportunity, $itemId] = wireOpportunityWithLine($this, $this->store);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/items/{$itemId}/override_surcharge", [
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 777,
    ])->assertSuccessful();

    $item = $opportunity->fresh('items')->items->firstWhere('id', $itemId);

    expect($item->surcharge_total)->toBe(777);
});

it('disables a line surcharge via the API', function () {
    [$opportunity, $itemId] = wireOpportunityWithLine($this, $this->store);
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $this->postJson("/api/v1/opportunities/{$opportunity->id}/items/{$itemId}/override_surcharge", [
        'surcharge_id' => $surcharge->id,
        'is_disabled' => true,
    ])->assertSuccessful();

    $item = $opportunity->fresh('items')->items->firstWhere('id', $itemId);

    expect($item->surcharge_total)->toBe(0);
});
