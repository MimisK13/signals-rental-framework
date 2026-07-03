<?php

use App\Models\DiscountCategory;
use App\Models\PriceCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Discount Categories API', function () {
    it('lists discount categories', function () {
        DiscountCategory::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/discount_categories')
            ->assertOk()
            ->assertJsonStructure([
                'discount_categories' => [
                    '*' => ['id', 'name', 'is_active', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('filters discount categories by name via Ransack', function () {
        DiscountCategory::factory()->create(['name' => 'Trade']);
        DiscountCategory::factory()->create(['name' => 'Staff']);
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/discount_categories?q[name_eq]=Trade')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        expect($response->json('discount_categories.0.name'))->toBe('Trade');
    });

    it('creates a discount category', function () {
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/discount_categories', [
                'name' => 'Preferred',
            ])
            ->assertCreated()
            ->assertJsonPath('discount_category.name', 'Preferred');
    });

    it('updates a discount category', function () {
        $category = DiscountCategory::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/discount_categories/{$category->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('discount_category.name', 'Updated');
    });

    it('deletes a discount category', function () {
        $category = DiscountCategory::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/discount_categories/{$category->id}")
            ->assertNoContent();

        expect(DiscountCategory::find($category->id))->toBeNull();
    });

    it('shows a single discount category', function () {
        $category = DiscountCategory::factory()->create(['name' => 'Trade']);
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/discount_categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('discount_category.name', 'Trade');
    });

    it('requires pricing:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/discount_categories')
            ->assertForbidden();
    });
});

describe('Price Categories API', function () {
    it('lists price categories with tiers', function () {
        PriceCategory::factory()->withDefaultTiers()->count(2)->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/price_categories')
            ->assertOk()
            ->assertJsonStructure([
                'price_categories' => [
                    '*' => ['id', 'name', 'is_active', 'tiers', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('filters price categories by name via Ransack', function () {
        PriceCategory::factory()->create(['name' => 'Retail']);
        PriceCategory::factory()->create(['name' => 'Trade']);
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/price_categories?q[name_eq]=Retail')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        expect($response->json('price_categories.0.name'))->toBe('Retail');
    });

    it('creates a price category with tiers', function () {
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/price_categories', [
                'name' => 'Retail',
                'tiers' => [
                    ['tier_number' => 1, 'label' => 'Day 1', 'multiplier' => '1.0000', 'sort_order' => 0],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('price_category.name', 'Retail')
            ->assertJsonCount(1, 'price_category.tiers');
    });

    it('updates a price category', function () {
        $category = PriceCategory::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/price_categories/{$category->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('price_category.name', 'Updated');
    });

    it('deletes a price category', function () {
        $category = PriceCategory::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/price_categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('price_categories', ['id' => $category->id]);
    });

    it('requires pricing:write ability for mutations', function () {
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/price_categories', ['name' => 'Blocked'])
            ->assertForbidden();
    });
});
