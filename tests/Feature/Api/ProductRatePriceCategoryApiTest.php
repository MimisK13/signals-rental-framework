<?php

use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->definition = RateDefinition::factory()->create();
    $this->trade = PriceCategory::factory()->create(['name' => 'Trade']);
});

function productRateWriteToken(User $user): string
{
    return $user->createToken('test', ['rates:write'])->plainTextToken;
}

function productRateReadToken(User $user): string
{
    return $user->createToken('test', ['rates:read'])->plainTextToken;
}

it('creates a product rate scoped to a price category', function () {
    $token = productRateWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/products/{$this->product->id}/rates", [
            'rate_definition_id' => $this->definition->id,
            'transaction_type' => 'rental',
            'price' => 8800,
            'currency' => 'GBP',
            'price_category_id' => $this->trade->id,
            'priority' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('product_rate.price_category_id', $this->trade->id)
        ->assertJsonPath('product_rate.price_category_name', 'Trade');

    $this->assertDatabaseHas('product_rates', [
        'product_id' => $this->product->id,
        'price_category_id' => $this->trade->id,
        'price' => 8800,
    ]);
});

it('lists and filters product rates by price category', function () {
    ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price_category_id' => null,
    ]);
    ProductRate::factory()->forPriceCategory($this->trade->id)->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
    ]);

    $token = productRateReadToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/products/{$this->product->id}/rates?q[price_category_id_eq]={$this->trade->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('product_rates.0.price_category_id', $this->trade->id);
});

it('updates a product rate price category', function () {
    $rate = ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price_category_id' => null,
    ]);
    $token = productRateWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}", [
            'price_category_id' => $this->trade->id,
        ])
        ->assertOk()
        ->assertJsonPath('product_rate.price_category_id', $this->trade->id);

    expect($rate->fresh()->price_category_id)->toBe($this->trade->id);
});

it('allows clearing the price category on update', function () {
    $rate = ProductRate::factory()->forPriceCategory($this->trade->id)->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
    ]);
    $token = productRateWriteToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}", [
            'price_category_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('product_rate.price_category_id', null);

    expect($rate->fresh()->price_category_id)->toBeNull();
});
