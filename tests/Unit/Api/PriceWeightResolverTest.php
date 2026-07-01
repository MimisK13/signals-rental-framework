<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SchemaController;
use App\Services\Api\PriceWeightResolver;
use App\Support\Api\Attributes\PriceWeight;
use Tests\TestCase;

uses(TestCase::class);

it('defaults route price weight to 1 when no attribute is present', function () {
    $resolver = app(PriceWeightResolver::class);

    expect($resolver->get('api.v1.products.index'))->toBe(1);
});

it('resolves explicit zero price weight on management schema endpoints', function () {
    $resolver = app(PriceWeightResolver::class);

    expect($resolver->get('api.v1.schema.index'))->toBe(0)
        ->and($resolver->get('api.v1.schema.show'))->toBe(0);
});

it('honours explicit price weight attribute values on controller methods', function () {
    $resolver = app(PriceWeightResolver::class);

    expect($resolver->weightForMethod(SchemaController::class, 'index'))->toBe(0)
        ->and($resolver->weightForMethod(ProductController::class, 'index'))->toBe(1);

    $attribute = (new ReflectionMethod(SchemaController::class, 'index'))
        ->getAttributes(PriceWeight::class)[0]
        ->newInstance();

    expect($attribute->weight)->toBe(0);
});

it('caches resolved route weights', function () {
    $resolver = app(PriceWeightResolver::class);

    $first = $resolver->all();
    $second = $resolver->all();

    expect($first)->toBe($second)
        ->and($first)->toHaveKey('api.v1.products.index');
});
