<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Pricing\Actions\CreatePriceCategory;
use App\Domain\Pricing\Actions\DeletePriceCategory;
use App\Domain\Pricing\Actions\UpdatePriceCategory;
use App\Domain\Pricing\Data\CreatePriceCategoryData;
use App\Domain\Pricing\Data\UpdatePriceCategoryData;
use App\Domain\Pricing\Models\PriceCategory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a price category with tiers', function () {
    Event::fake([AuditableEvent::class]);

    $result = (new CreatePriceCategory)(CreatePriceCategoryData::from([
        'name' => 'Retail',
        'description' => 'Standard retail list',
        'is_active' => true,
        'tiers' => [
            ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
            ['tier_number' => 2, 'label' => 'Day 2+', 'multiplier' => '0.8000', 'sort_order' => 1],
        ],
    ]));

    expect($result->name)->toBe('Retail');
    expect($result->tiers)->toHaveCount(2);

    $category = PriceCategory::where('name', 'Retail')->first();
    expect($category)->not->toBeNull();
    expect($category->tiers)->toHaveCount(2);

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a price category and replaces tiers', function () {
    Event::fake([AuditableEvent::class]);

    $category = PriceCategory::factory()->withDefaultTiers()->create(['name' => 'Old']);

    $result = (new UpdatePriceCategory)(
        $category,
        UpdatePriceCategoryData::from([
            'name' => 'Updated',
            'tiers' => [
                ['tier_number' => 1, 'label' => 'Single tier', 'multiplier' => '1.5000', 'sort_order' => 0],
            ],
        ]),
    );

    expect($result->name)->toBe('Updated');
    expect($category->fresh()->tiers)->toHaveCount(1);
    expect((string) $category->fresh()->tiers->first()->multiplier)->toBe('1.5000');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a price category', function () {
    Event::fake([AuditableEvent::class]);

    $category = PriceCategory::factory()->create();

    (new DeletePriceCategory)($category);

    expect(PriceCategory::find($category->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized price category creation', function () {
    $this->actingAs(User::factory()->create());

    (new CreatePriceCategory)(CreatePriceCategoryData::from(['name' => 'Unauthorized']));
})->throws(AuthorizationException::class);
