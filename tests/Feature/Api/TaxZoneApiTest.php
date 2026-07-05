<?php

use App\Models\TaxCategory;
use App\Models\TaxZone;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxCategorySeeder;
use Database\Seeders\TaxZoneSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Tax Zones API', function () {
    it('lists tax zones', function () {
        TaxZone::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/tax_zones')
            ->assertOk()
            ->assertJsonStructure([
                'tax_zones' => [
                    '*' => ['id', 'name', 'slug', 'priority', 'is_active', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates a tax zone', function () {
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/tax_zones', [
                'name' => 'Nordics',
                'slug' => 'nordics',
                'priority' => 50,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('tax_zone.name', 'Nordics')
            ->assertJsonPath('tax_zone.slug', 'nordics');
    });

    it('updates a tax zone', function () {
        $zone = TaxZone::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tax_zones/{$zone->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('tax_zone.name', 'Updated');
    });

    it('deletes a tax zone', function () {
        $zone = TaxZone::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/tax_zones/{$zone->id}")
            ->assertNoContent();

        expect(TaxZone::find($zone->id))->toBeNull();
    });

    it('requires tax-classes:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/tax_zones')
            ->assertForbidden();
    });

    it('shows a single tax zone', function () {
        $zone = TaxZone::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/tax_zones/{$zone->id}")
            ->assertOk()
            ->assertJsonPath('tax_zone.id', $zone->id);
    });
});

describe('Tax Categories API', function () {
    it('lists tax categories', function () {
        $this->seed(TaxCategorySeeder::class);
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/tax_categories')
            ->assertOk()
            ->assertJsonStructure([
                'tax_categories' => [
                    '*' => ['id', 'name', 'slug', 'type', 'is_active'],
                ],
                'meta',
            ]);
    });

    it('creates a tax category', function () {
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/tax_categories', [
                'name' => 'Special',
                'slug' => 'special',
                'type' => 'standard',
            ])
            ->assertCreated()
            ->assertJsonPath('tax_category.name', 'Special');
    });

    it('updates a tax category', function () {
        $category = TaxCategory::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/tax_categories/{$category->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('tax_category.name', 'Updated');
    });

    it('deletes a tax category', function () {
        $category = TaxCategory::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/tax_categories/{$category->id}")
            ->assertNoContent();

        expect(TaxCategory::find($category->id))->toBeNull();
    });

    it('shows a single tax category', function () {
        $category = TaxCategory::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/tax_categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('tax_category.id', $category->id);
    });
});

describe('seeded reference data sanity', function () {
    it('seeds four tax zones and four tax categories', function () {
        $this->seed(TaxZoneSeeder::class);
        $this->seed(TaxCategorySeeder::class);

        expect(TaxZone::query()->whereIn('slug', ['uk', 'eu_member', 'eu_other', 'world'])->count())->toBe(4)
            ->and(TaxCategory::query()->whereIn('slug', ['standard', 'reduced', 'zero', 'exempt'])->count())->toBe(4);
    });
});
