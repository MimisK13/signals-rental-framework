<?php

declare(strict_types=1);

use App\Domain\Products\Models\Accessory;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Products\Models\SerialisedComponent;
use App\Domain\Products\Presentation\Columns\ProductColumnRegistry;
use App\Domain\Products\Presentation\Columns\ProductGroupColumnRegistry;
use App\Domain\Products\Presentation\Columns\SerialisedComponentColumnRegistry;
use App\Domain\Products\ProductsServiceProvider;
use App\Services\ColumnRegistryResolver;

it('registers Products routes, policies, and column registries', function (): void {
    $resolver = app(ColumnRegistryResolver::class);

    expect(app()->providerIsLoaded(ProductsServiceProvider::class))->toBeTrue()
        ->and(route('products.index'))->toContain('/products')
        ->and(route('api.v1.products.index'))->toContain('/api/v1/products')
        ->and(route('product-groups.index'))->toContain('/product-groups')
        ->and($resolver->resolve('products'))->toBeInstanceOf(ProductColumnRegistry::class)
        ->and($resolver->resolve('product_groups'))->toBeInstanceOf(ProductGroupColumnRegistry::class)
        ->and($resolver->resolve('serialised_components'))->toBeInstanceOf(SerialisedComponentColumnRegistry::class);
});

it('resolves Products domain factories and canonical model identities', function (): void {
    $models = [
        Product::factory()->create(),
        ProductGroup::factory()->create(),
        Accessory::factory()->create(),
        SerialisedComponent::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
        expect($model->getMorphClass())->toBe($model::class);
    }
});

it('does not expose legacy Products classes or enums', function (): void {
    expect(class_exists('App\\Models\\Product'))->toBeFalse()
        ->and(class_exists('App\\Models\\ProductGroup'))->toBeFalse()
        ->and(class_exists('App\\Models\\Accessory'))->toBeFalse()
        ->and(class_exists('App\\Models\\SerialisedComponent'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\ProductType'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\KitComponentBinding'))->toBeFalse()
        ->and(class_exists('App\\Actions\\Products\\CreateProduct'))->toBeFalse();
});

arch('Products presentation does not depend on Volt', function (): void {
    expect('App\Domain\Products\Presentation')->not->toUse('Livewire\Volt');
});
