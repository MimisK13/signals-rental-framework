<?php

use App\Actions\Pricing\CreateSurchargeGroup;
use App\Data\Pricing\CreateSurchargeGroupData;
use App\Models\ProductTaxClass;
use App\Models\SurchargeGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('shows the surcharge groups admin index', function () {
    SurchargeGroup::factory()->create(['name' => 'Cleaning']);

    Volt::test('admin.settings.pricing.surcharge-groups')
        ->assertSee('Cleaning');
});

it('creates a surcharge group with nested surcharges from the admin form', function () {
    $taxClass = ProductTaxClass::factory()->create(['name' => 'Reduced']);

    Volt::test('admin.settings.pricing.surcharge-group-form')
        ->set('name', 'Insurance')
        ->set('isDefault', true)
        ->set('surcharges', [[
            'name' => 'Equipment cover',
            'percent_value' => '5.00',
            'fixed_value_minor' => '',
            'product_group_ids' => [],
            'tag_names' => '',
            'product_ids' => [],
            'transaction_types' => [],
            'product_tax_class_id' => (string) $taxClass->id,
            'is_default' => true,
            'valid_from' => '',
            'valid_to' => '',
            'sort_order' => 0,
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $group = SurchargeGroup::query()->where('name', 'Insurance')->firstOrFail();

    expect($group->is_default)->toBeTrue()
        ->and($group->surcharges)->toHaveCount(1)
        ->and((string) $group->surcharges->first()->percent_value)->toBe('5.00')
        ->and($group->surcharges->first()->product_tax_class_id)->toBe($taxClass->id);
});

it('creates a surcharge group via the action with fixed per-unit value', function () {
    $data = CreateSurchargeGroupData::from([
        'name' => 'Prep fees',
        'surcharges' => [[
            'name' => 'Prep',
            'fixed_value_minor' => 250,
            'criteria' => [],
            'is_default' => true,
        ]],
    ]);

    $result = (new CreateSurchargeGroup)($data);

    expect($result->surcharges)->toHaveCount(1)
        ->and($result->surcharges[0]->fixed_value_minor)->toBe(250)
        ->and($result->surcharges[0]->percent_value)->toBeNull();
});
