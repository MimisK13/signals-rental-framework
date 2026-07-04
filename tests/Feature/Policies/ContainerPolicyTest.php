<?php

use App\Models\Container;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Policies\ContainerPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new ContainerPolicy;
});

it('maps view to containers.view with store access', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $stock = StockLevel::factory()->serialised()->create(['store_id' => $store->id]);
    $container = Container::factory()->create([
        'store_id' => null,
        'serialised_item_id' => $stock->id,
    ]);

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id]);
    $viewer->givePermissionTo(['containers.view', 'stock.access']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $container))->toBeFalse();

    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $container))->toBeTrue();
});

it('maps elevated actions to their permissions', function () {
    $container = Container::factory()->create(['store_id' => null]);
    $user = User::factory()->owner()->create();

    expect($this->policy->create($user))->toBeTrue()
        ->and($this->policy->createTemporary($user))->toBeTrue()
        ->and($this->policy->update($user, $container))->toBeTrue()
        ->and($this->policy->pack($user, $container))->toBeTrue()
        ->and($this->policy->packOverride($user, $container))->toBeTrue()
        ->and($this->policy->transfer($user, $container))->toBeTrue()
        ->and($this->policy->nest($user, $container))->toBeTrue()
        ->and($this->policy->seal($user, $container))->toBeTrue()
        ->and($this->policy->dissolve($user, $container))->toBeTrue()
        ->and($this->policy->manageTemplates($user))->toBeTrue();
});

it('resolves store from housing serialised item when store_id is null', function () {
    $store = Store::factory()->create();
    $stock = StockLevel::factory()->serialised()->create(['store_id' => $store->id]);
    $container = Container::factory()->create([
        'store_id' => null,
        'serialised_item_id' => $stock->id,
    ]);

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id]);
    $viewer->givePermissionTo(['containers.view', 'stock.access']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);

    expect($container->store_id)->toBeNull()
        ->and($this->policy->view($viewer, $container))->toBeTrue();
});
