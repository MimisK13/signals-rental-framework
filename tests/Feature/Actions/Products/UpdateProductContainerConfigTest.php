<?php

use App\Actions\Products\UpdateProductContainerConfig;
use App\Data\Products\UpdateProductContainerConfigData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates container configuration for a serialised product', function () {
    $housing = Product::factory()->serialised()->create();
    $component = Product::factory()->serialised()->create();

    $result = (new UpdateProductContainerConfig)($housing, UpdateProductContainerConfigData::from([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Kit,
        'container_max_nesting_depth' => 2,
        'container_repack_on_return' => true,
        'is_warehouse_addable' => false,
        'container_template' => [
            'slots' => [
                ['product_id' => $component->id, 'quantity' => 2, 'binding' => 'fixed'],
            ],
        ],
    ]));

    expect($result->is_containerable)->toBeTrue()
        ->and($result->container_availability_mode)->toBe('kit')
        ->and($result->container_template['slots'][0]['product_id'])->toBe($component->id)
        ->and($result->container_template['max_items'])->toBe(2);
});

it('rejects container config for bulk products', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk]);

    expect(fn () => (new UpdateProductContainerConfig)($product, UpdateProductContainerConfigData::from([
        'is_containerable' => true,
        'container_max_nesting_depth' => 2,
        'container_repack_on_return' => false,
        'is_warehouse_addable' => false,
    ])))->toThrow(ValidationException::class);
});

it('requires manage_templates permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['products.edit', 'products.view']);
    $this->actingAs($user);

    $product = Product::factory()->serialised()->create();

    expect(fn () => (new UpdateProductContainerConfig)($product, UpdateProductContainerConfigData::from([
        'is_containerable' => false,
        'container_max_nesting_depth' => 2,
        'container_repack_on_return' => false,
        'is_warehouse_addable' => false,
    ])))->toThrow(AuthorizationException::class);
});

it('clears template when containerable is disabled', function () {
    $housing = Product::factory()->containerable()->create([
        'container_template' => ['slots' => [], 'max_items' => 0],
    ]);

    (new UpdateProductContainerConfig)($housing, UpdateProductContainerConfigData::from([
        'is_containerable' => false,
        'container_max_nesting_depth' => 2,
        'container_repack_on_return' => false,
        'is_warehouse_addable' => false,
    ]));

    $housing->refresh();

    expect($housing->is_containerable)->toBeFalse()
        ->and($housing->container_template)->toBeNull();
});
