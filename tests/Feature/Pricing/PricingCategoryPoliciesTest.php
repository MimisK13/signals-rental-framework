<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\Pricing\Models\PriceCategory;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('DiscountCategoryPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view discount categories', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', DiscountCategory::class))->toBeTrue();
        });

        it('allows user with pricing.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('pricing.view');

            expect($user->can('viewAny', DiscountCategory::class))->toBeTrue();
        });

        it('denies user without pricing.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', DiscountCategory::class))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows user with pricing.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('pricing.manage');

            expect($user->can('create', DiscountCategory::class))->toBeTrue();
        });

        it('denies user without pricing.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', DiscountCategory::class))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('denies user without pricing.manage permission', function () {
            $user = User::factory()->create();
            $category = DiscountCategory::factory()->create();

            expect($user->can('delete', $category))->toBeFalse();
        });
    });
});

describe('PriceCategoryPolicy', function () {
    describe('viewAny', function () {
        it('allows user with pricing.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('pricing.view');

            expect($user->can('viewAny', PriceCategory::class))->toBeTrue();
        });

        it('denies user without pricing.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', PriceCategory::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows user with pricing.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('pricing.manage');
            $category = PriceCategory::factory()->create();

            expect($user->can('update', $category))->toBeTrue();
        });

        it('denies user without pricing.manage permission', function () {
            $user = User::factory()->create();
            $category = PriceCategory::factory()->create();

            expect($user->can('update', $category))->toBeFalse();
        });
    });
});
