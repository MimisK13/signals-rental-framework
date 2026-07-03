<?php

use App\Models\BundleRule;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Bundle Rules API', function () {
    it('lists bundle rules', function () {
        BundleRule::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/bundle_rules')
            ->assertOk()
            ->assertJsonStructure([
                'bundle_rules' => [
                    '*' => ['id', 'name', 'bundle_percent', 'bundle_fixed_price_minor', 'criteria', 'is_active', 'sort_order', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates a percent bundle rule with product criteria', function () {
        $products = Product::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/bundle_rules', [
                'name' => 'Lighting bundle',
                'bundle_percent' => '12.50',
                'criteria' => ['product_ids' => $products->pluck('id')->all()],
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('bundle_rule.bundle_percent', '12.50')
            ->assertJsonPath('bundle_rule.criteria.product_ids.0', $products->first()->id);
    });

    it('creates a fixed-price bundle rule', function () {
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/bundle_rules', [
                'name' => 'Fixed bundle',
                'bundle_fixed_price_minor' => 25_000,
            ])
            ->assertCreated()
            ->assertJsonPath('bundle_rule.bundle_fixed_price_minor', 25000)
            ->assertJsonPath('bundle_rule.bundle_percent', null);
    });

    it('updates a bundle rule', function () {
        $rule = BundleRule::factory()->percent('5.00')->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/bundle_rules/{$rule->id}", [
                'bundle_percent' => '8.00',
                'name' => 'Updated bundle',
            ])
            ->assertOk()
            ->assertJsonPath('bundle_rule.bundle_percent', '8.00')
            ->assertJsonPath('bundle_rule.name', 'Updated bundle');
    });

    it('deletes a bundle rule', function () {
        $rule = BundleRule::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/bundle_rules/{$rule->id}")
            ->assertNoContent();

        expect(BundleRule::query()->find($rule->id))->toBeNull();
    });
});
