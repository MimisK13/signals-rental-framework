<?php

use App\Enums\ContainerAvailabilityMode;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Services\Containers\ContainerTemplateValidator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->validator = app(ContainerTemplateValidator::class);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function containerProduct(array $attributes = []): Product
{
    return Product::factory()->serialised()->create($attributes);
}

/**
 * @return array{slots: list<array{product_id: int, quantity: int, binding?: string}>, max_items: int}
 */
function validTemplate(Product $component, int $quantity = 1, ?string $binding = null): array
{
    $slot = [
        'product_id' => $component->id,
        'quantity' => $quantity,
    ];

    if ($binding !== null) {
        $slot['binding'] = $binding;
    }

    return [
        'slots' => [$slot],
        'max_items' => $quantity,
    ];
}

it('accepts a valid kit template', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    $this->validator->validate(
        $housing,
        validTemplate($component, 2, 'fixed'),
        ContainerAvailabilityMode::Kit,
    );

    expect(true)->toBeTrue();
});

it('rejects a missing product reference', function () {
    $housing = containerProduct(['is_containerable' => true]);

    expect(fn () => $this->validator->validate($housing, [
        'slots' => [['quantity' => 1]],
    ], ContainerAvailabilityMode::Kit))
        ->toThrow(ValidationException::class, 'product is required');
});

it('rejects a soft-deleted product reference', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();
    $component->delete();

    try {
        $this->validator->validate($housing, validTemplate($component), ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.product_id');
    }
});

it('rejects zero, negative, and fractional quantities', function (mixed $quantity) {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    try {
        $this->validator->validate($housing, [
            'slots' => [['product_id' => $component->id, 'quantity' => $quantity, 'binding' => 'fixed']],
        ], ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.quantity');
    }
})->with([
    'zero' => [0],
    'negative' => [-1],
    'fractional' => [1.5],
]);

it('rejects non-serialised referenced products', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $bulk = Product::factory()->create(['stock_method' => StockMethod::Bulk]);

    try {
        $this->validator->validate($housing, validTemplate($bulk), ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.product_id');
    }
});

it('rejects self-reference in the template', function () {
    $housing = containerProduct(['is_containerable' => true]);

    try {
        $this->validator->validate($housing, validTemplate($housing), ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.product_id');
    }
});

it('rejects pool binding in kit mode', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    try {
        $this->validator->validate($housing, validTemplate($component, 1, 'pool'), ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.binding');
    }
});

it('rejects fixed binding in transport mode', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    try {
        $this->validator->validate($housing, validTemplate($component, 1, 'fixed'), ContainerAvailabilityMode::Transport);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.binding');
    }
});

it('requires explicit binding in hybrid mode', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    try {
        $this->validator->validate($housing, [
            'slots' => [['product_id' => $component->id, 'quantity' => 1]],
        ], ContainerAvailabilityMode::Hybrid);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.slots.0.binding');
    }
});

it('rejects max_items that do not match slot totals', function () {
    $housing = containerProduct(['is_containerable' => true]);
    $component = containerProduct();

    try {
        $this->validator->validate($housing, [
            'slots' => [['product_id' => $component->id, 'quantity' => 2, 'binding' => 'fixed']],
            'max_items' => 3,
        ], ContainerAvailabilityMode::Kit);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('container_template.max_items');
    }
});
