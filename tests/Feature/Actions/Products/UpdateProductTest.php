<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\Products\Actions\UpdateProduct;
use App\Domain\Products\Data\UpdateProductData;
use App\Domain\Products\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates a product', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create(['name' => 'Old Name']);

    $data = UpdateProductData::from([
        'name' => 'New Name',
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result->name)->toBe('New Name');
    expect($product->fresh()->name)->toBe('New Name');

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a product with custom fields', function () {
    Event::fake([AuditableEvent::class]);

    $customField = CustomField::factory()->create([
        'name' => 'product_ref',
        'module_type' => 'Product',
        'field_type' => CustomFieldType::String,
    ]);

    $product = Product::factory()->create(['name' => 'Test Product']);

    $data = UpdateProductData::from([
        'name' => 'Updated Product',
        'custom_fields' => ['product_ref' => 'REF-999'],
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result->name)->toBe('Updated Product');

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $customField->id)
        ->where('entity_type', Product::class)
        ->where('entity_id', $product->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('REF-999');
});

it('records an action_logs row when a product is updated', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $product = Product::factory()->create(['name' => 'Before']);

    (new UpdateProduct)($product, UpdateProductData::from(['name' => 'After']));

    assertActionLogged('product.updated', Product::class, $product->id, $user->id);
});

it('requires products.edit permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();

    $data = UpdateProductData::from(['name' => 'Hack']);

    (new UpdateProduct)($product, $data);
})->throws(AuthorizationException::class);

it('clears optional field to null when empty string is passed', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create(['description' => 'Original description']);

    $data = UpdateProductData::from([
        'description' => '',
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($product->fresh()->description)->toBeNull();
});

it('leaves field unchanged when null is passed via DTO', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create(['description' => 'Keep this']);

    $data = UpdateProductData::from([
        'name' => 'Updated Name',
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($product->fresh()->description)->toBe('Keep this')
        ->and($product->fresh()->name)->toBe('Updated Name');
});

it('allows partial custom field updates without enforcing required', function () {
    Event::fake([AuditableEvent::class]);

    CustomField::factory()->string()->required()->create([
        'name' => 'mandatory_ref',
        'module_type' => 'Product',
    ]);
    CustomField::factory()->string()->create([
        'name' => 'optional_note',
        'module_type' => 'Product',
    ]);

    $product = Product::factory()->create();
    $product->syncCustomFields(['mandatory_ref' => 'REF-001']);

    $data = UpdateProductData::from([
        'custom_fields' => ['optional_note' => 'Updated note'],
    ]);

    $result = (new UpdateProduct)($product, $data);

    expect($result)->not->toBeNull();
});
