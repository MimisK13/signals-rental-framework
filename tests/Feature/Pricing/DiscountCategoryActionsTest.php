<?php

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Pricing\Actions\CreateDiscountCategory;
use App\Domain\Pricing\Actions\DeleteDiscountCategory;
use App\Domain\Pricing\Actions\UpdateDiscountCategory;
use App\Domain\Pricing\Data\CreateDiscountCategoryData;
use App\Domain\Pricing\Data\UpdateDiscountCategoryData;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a discount category', function () {
    Event::fake([AuditableEvent::class]);

    $result = (new CreateDiscountCategory)(CreateDiscountCategoryData::from([
        'name' => 'Trade',
        'description' => 'Trade discount band',
        'is_active' => true,
    ]));

    expect($result->name)->toBe('Trade');
    expect(DiscountCategory::where('name', 'Trade')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a discount category', function () {
    Event::fake([AuditableEvent::class]);

    $category = DiscountCategory::factory()->create(['name' => 'Old']);

    $result = (new UpdateDiscountCategory)(
        $category,
        UpdateDiscountCategoryData::from(['name' => 'Updated']),
    );

    expect($result->name)->toBe('Updated');

    Event::assertDispatched(AuditableEvent::class);
});

it('soft deletes a discount category', function () {
    Event::fake([AuditableEvent::class]);

    $category = DiscountCategory::factory()->create();

    (new DeleteDiscountCategory)($category);

    expect(DiscountCategory::find($category->id))->toBeNull();
    expect(DiscountCategory::withTrashed()->find($category->id))->not->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized discount category creation', function () {
    $this->actingAs(User::factory()->create());

    (new CreateDiscountCategory)(CreateDiscountCategoryData::from(['name' => 'Unauthorized']));
})->throws(AuthorizationException::class);
