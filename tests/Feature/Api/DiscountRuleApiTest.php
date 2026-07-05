<?php

use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Discount Rules API', function () {
    it('lists discount rules', function () {
        DiscountRule::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/discount_rules')
            ->assertOk()
            ->assertJsonStructure([
                'discount_rules' => [
                    '*' => ['id', 'discount_category_id', 'discount_percent', 'criteria', 'sort_order', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('filters discount rules by category via Ransack', function () {
        $category = DiscountCategory::factory()->create();
        DiscountRule::factory()->for($category)->create(['discount_percent' => '12.00']);
        DiscountRule::factory()->create(['discount_percent' => '5.00']);
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/discount_rules?q[discount_category_id_eq]={$category->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        expect($response->json('discount_rules.0.discount_percent'))->toBe('12.00');
    });

    it('creates a discount rule with criteria', function () {
        $category = DiscountCategory::factory()->create();
        $group = ProductGroup::factory()->create();
        $product = Product::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/discount_rules', [
                'discount_category_id' => $category->id,
                'discount_percent' => '15.00',
                'criteria' => [
                    'product_group_ids' => [$group->id],
                    'product_ids' => [$product->id],
                    'tag_names' => ['lighting'],
                    'transaction_types' => ['rental'],
                ],
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('discount_rule.discount_percent', '15.00')
            ->assertJsonPath('discount_rule.criteria.product_group_ids.0', $group->id);
    });

    it('updates a discount rule', function () {
        $rule = DiscountRule::factory()->create(['discount_percent' => '5.00']);
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/discount_rules/{$rule->id}", [
                'discount_percent' => '20.00',
            ])
            ->assertOk()
            ->assertJsonPath('discount_rule.discount_percent', '20.00');
    });

    it('deletes a discount rule', function () {
        $rule = DiscountRule::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/discount_rules/{$rule->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('discount_rules', ['id' => $rule->id]);
    });

    it('shows a single discount rule', function () {
        $rule = DiscountRule::factory()->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/discount_rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('discount_rule.id', $rule->id);
    });

    it('requires pricing:write ability for mutations', function () {
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/discount_rules', [
                'discount_category_id' => DiscountCategory::factory()->create()->id,
                'discount_percent' => '10.00',
            ])
            ->assertForbidden();
    });
});

describe('Discount category rules include', function () {
    it('embeds rules when include=rules is requested', function () {
        $category = DiscountCategory::factory()->create();
        DiscountRule::factory()->for($category)->create(['discount_percent' => '10.00']);
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/discount_categories/{$category->id}?include=rules")
            ->assertOk()
            ->assertJsonCount(1, 'discount_category.rules')
            ->assertJsonPath('discount_category.rules.0.discount_percent', '10.00');
    });

    it('omits rules when include is not requested', function () {
        $category = DiscountCategory::factory()->create();
        DiscountRule::factory()->for($category)->create();
        $token = $this->owner->createToken('test', ['pricing:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/discount_categories/{$category->id}")
            ->assertOk();

        expect($response->json('discount_category.rules'))->toBeNull();
    });

    it('syncs nested rules on category update', function () {
        $category = DiscountCategory::factory()->create();
        DiscountRule::factory()->for($category)->create(['discount_percent' => '5.00']);
        $token = $this->owner->createToken('test', ['pricing:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/discount_categories/{$category->id}", [
                'rules' => [
                    [
                        'discount_percent' => '12.50',
                        'criteria' => ['transaction_types' => ['sale']],
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'discount_category.rules')
            ->assertJsonPath('discount_category.rules.0.discount_percent', '12.50');

        expect($category->rules()->count())->toBe(1);
    });
});
