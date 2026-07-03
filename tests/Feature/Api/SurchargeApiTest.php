<?php

use App\Models\ProductTaxClass;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Sanctum::actingAs(User::factory()->owner()->create(), ['pricing:read', 'pricing:write']);
});

it('creates and lists surcharge groups with nested surcharges', function () {
    $response = $this->postJson('/api/v1/surcharge_groups', [
        'name' => 'Cleaning',
        'is_default' => true,
        'surcharges' => [[
            'name' => 'Deep clean',
            'percent_value' => '7.50',
            'is_default' => true,
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('surcharge_group.name', 'Cleaning');

    $groupId = $response->json('surcharge_group.id');

    $this->getJson('/api/v1/surcharge_groups/'.$groupId)
        ->assertSuccessful()
        ->assertJsonPath('surcharge_group.surcharges.0.name', 'Deep clean');
});

it('creates a standalone fixed surcharge', function () {
    $group = SurchargeGroup::factory()->create();
    $taxClass = ProductTaxClass::factory()->create();

    $this->postJson('/api/v1/surcharges', [
        'surcharge_group_id' => $group->id,
        'name' => 'Prep fee',
        'fixed_value_minor' => 350,
        'product_tax_class_id' => $taxClass->id,
        'is_default' => true,
    ])->assertCreated()
        ->assertJsonPath('surcharge.fixed_value_minor', 350)
        ->assertJsonPath('surcharge.product_tax_class_id', $taxClass->id);
});

it('filters surcharges by surcharge group', function () {
    $group = SurchargeGroup::factory()->create();
    Surcharge::factory()->for($group, 'surchargeGroup')->create(['name' => 'In group']);
    Surcharge::factory()->create(['name' => 'Other']);

    $this->getJson('/api/v1/surcharges?q[surcharge_group_id_eq]='.$group->id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'surcharges');
});

it('updates a surcharge percent value', function () {
    $surcharge = Surcharge::factory()->percent('5.00')->create();

    $this->putJson('/api/v1/surcharges/'.$surcharge->id, [
        'percent_value' => '12.50',
    ])->assertSuccessful()
        ->assertJsonPath('surcharge.percent_value', '12.50');
});
