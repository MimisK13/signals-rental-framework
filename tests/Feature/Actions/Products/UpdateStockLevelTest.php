<?php

use App\Actions\Products\UpdateStockLevel;
use App\Data\Products\UpdateStockLevelData;
use App\Enums\CustomFieldType;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('updates stock metadata without changing physical quantity', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);

    $data = UpdateStockLevelData::from(['location' => 'Warehouse B']);

    $result = (new UpdateStockLevel)($stockLevel, $data);

    expect($result->product_id)->toBe($stockLevel->product_id);
    expect($result->location)->toBe('Warehouse B');
    expect((float) $stockLevel->fresh()->quantity_held)->toBe(5.0);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'stock_level.updated';
    });
});

it('rejects direct physical quantity changes', function () {
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);

    (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from([
        'quantity_held' => '20',
    ]));
})->throws(ValidationException::class);

it('clears optional field to null when empty string is passed', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['location' => 'Warehouse A']);

    $data = UpdateStockLevelData::from(['location' => '']);
    (new UpdateStockLevel)($stockLevel, $data);

    expect($stockLevel->refresh()->location)->toBeNull();
});

it('leaves field unchanged when null is passed via DTO', function () {
    Event::fake([AuditableEvent::class]);

    $stockLevel = StockLevel::factory()->create(['location' => 'Warehouse A']);

    $data = UpdateStockLevelData::from(['item_name' => 'New Name']);
    (new UpdateStockLevel)($stockLevel, $data);

    expect($stockLevel->refresh()->location)->toBe('Warehouse A');
});

it('syncs custom fields when provided', function () {
    Event::fake([AuditableEvent::class]);

    $customField = CustomField::factory()->create([
        'name' => 'stock_ref',
        'module_type' => 'StockLevel',
        'field_type' => CustomFieldType::String,
    ]);

    $stockLevel = StockLevel::factory()->create();

    $data = UpdateStockLevelData::from([
        'location' => 'Warehouse B',
        'custom_fields' => ['stock_ref' => 'SL-555'],
    ]);

    (new UpdateStockLevel)($stockLevel, $data);

    $cfv = CustomFieldValue::query()
        ->where('custom_field_id', $customField->id)
        ->where('entity_type', StockLevel::class)
        ->where('entity_id', $stockLevel->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('SL-555');
});

it('requires stock.adjust permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $stockLevel = StockLevel::factory()->create();

    $data = UpdateStockLevelData::from(['quantity_held' => 99]);

    (new UpdateStockLevel)($stockLevel, $data);
})->throws(AuthorizationException::class);
