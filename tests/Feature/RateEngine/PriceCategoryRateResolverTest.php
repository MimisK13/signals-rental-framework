<?php

use App\Enums\RateTransactionType;
use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Services\RateEngine\RateResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function priceCategoryResolver(): RateResolver
{
    return app(RateResolver::class);
}

it('resolves a category-specific rate for a trade organisation price category', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);

    ProductRate::factory()->for($product)->create(['price' => 10000, 'price_category_id' => null]);
    $tradeRate = ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create(['price' => 7500]);

    $resolved = priceCategoryResolver()->resolve(
        $product,
        RateTransactionType::Rental,
        priceCategoryId: $trade->id,
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($tradeRate->id)
        ->and($resolved->price)->toBe(7500);
});

it('resolves the default rate when the organisation has no price category', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);

    $defaultRate = ProductRate::factory()->for($product)->create(['price' => 10000, 'price_category_id' => null]);
    ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create(['price' => 7500]);

    $resolved = priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: null);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($defaultRate->id)
        ->and($resolved->price)->toBe(10000);
});

it('falls back to the default rate when the category has no matching rate', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);

    $defaultRate = ProductRate::factory()->for($product)->create(['price' => 10000, 'price_category_id' => null]);

    $resolved = priceCategoryResolver()->resolve(
        $product,
        RateTransactionType::Rental,
        priceCategoryId: $trade->id,
    );

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($defaultRate->id)
        ->and($resolved->price)->toBe(10000);
});

it('does not let a default rate beat a category-specific rate at equal priority', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);

    ProductRate::factory()->for($product)->withPriority(5)->create([
        'price' => 10000,
        'price_category_id' => null,
    ]);
    $tradeRate = ProductRate::factory()->for($product)->withPriority(5)->forPriceCategory($trade->id)->create([
        'price' => 7500,
    ]);

    $resolved = priceCategoryResolver()->resolve(
        $product,
        RateTransactionType::Rental,
        priceCategoryId: $trade->id,
    );

    expect($resolved->id)->toBe($tradeRate->id);
});

it('caches resolutions separately per price category', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $retail = PriceCategory::factory()->create(['name' => 'Retail']);

    ProductRate::factory()->for($product)->create(['price' => 1000, 'price_category_id' => null]);
    $tradeRate = ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create(['price' => 2000]);
    ProductRate::factory()->for($product)->forPriceCategory($retail->id)->create(['price' => 3000]);

    expect(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: null)?->price)->toBe(1000)
        ->and(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: $trade->id)?->price)->toBe(2000)
        ->and(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: $retail->id)?->price)->toBe(3000);

    ProductRate::query()->whereKey($tradeRate->id)->delete();

    expect(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: $trade->id)?->price)->toBe(2000)
        ->and(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: null)?->price)->toBe(1000)
        ->and(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: $retail->id)?->price)->toBe(3000);
});

it('resolves directly without caching when the driver has no tag support', function () {
    Cache::partialMock()->shouldReceive('supportsTags')->andReturnFalse();

    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);
    $tradeRate = ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create(['price' => 4500]);

    expect(priceCategoryResolver()->resolve($product, RateTransactionType::Rental, priceCategoryId: $trade->id)?->id)
        ->toBe($tradeRate->id);
});

it('respects validity windows within a price category scope', function () {
    $product = Product::factory()->create();
    $trade = PriceCategory::factory()->create(['name' => 'Trade']);

    ProductRate::factory()->for($product)->forPriceCategory($trade->id)->create([
        'valid_from' => '2026-06-01',
        'valid_to' => '2026-06-30',
        'price' => 5000,
    ]);

    expect(priceCategoryResolver()->resolve(
        $product,
        RateTransactionType::Rental,
        date: Carbon::parse('2026-07-01'),
        priceCategoryId: $trade->id,
    ))->toBeNull();
});
