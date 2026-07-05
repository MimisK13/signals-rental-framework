<?php

use App\Models\ProductSupplierCost;
use App\Models\User;
use App\Policies\ProductSupplierCostPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new ProductSupplierCostPolicy;
});

it('allows view when user has costs.view and products.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.view']);

    expect($this->policy->viewAny($user))->toBeTrue();
});

it('denies view without costs.view even with products.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['products.view']);

    expect($this->policy->viewAny($user))->toBeFalse();
});

it('denies view without products.view even with costs.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view']);

    expect($this->policy->viewAny($user))->toBeFalse();
});

it('allows manage when user has costs.view and products.supplier_costs', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.supplier_costs']);

    expect($this->policy->create($user))->toBeTrue()
        ->and($this->policy->update($user))->toBeTrue()
        ->and($this->policy->delete($user))->toBeTrue();
});

it('denies manage without products.supplier_costs', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.view']);

    expect($this->policy->create($user))->toBeFalse();
});

it('allows setPreferred with manage permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.supplier_costs']);
    $cost = ProductSupplierCost::factory()->create();

    expect($this->policy->setPreferred($user, $cost))->toBeTrue();
});

it('allows view for a specific supplier cost when list access is granted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.view']);
    $cost = ProductSupplierCost::factory()->create();

    expect($this->policy->view($user, $cost))->toBeTrue();
});
