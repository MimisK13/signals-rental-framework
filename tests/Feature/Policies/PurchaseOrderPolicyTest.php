<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new PurchaseOrderPolicy;
});

it('allows view with purchase_orders.view without costs.view', function () {
    $po = PurchaseOrder::factory()->create();

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $viewer->givePermissionTo(['purchase_orders.view', 'purchase_orders.access']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $po->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $po))->toBeTrue();
});

it('allows view with costs.view and purchase_orders.view', function () {
    $po = PurchaseOrder::factory()->create();

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $viewer->givePermissionTo(['purchase_orders.view', 'purchase_orders.access', 'costs.view']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $po->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $po))->toBeTrue();
});

it('denies view for a store-restricted user without access to the purchase order store', function () {
    $po = PurchaseOrder::factory()->create();

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id]);
    $viewer->givePermissionTo(['costs.view', 'purchase_orders.view', 'purchase_orders.access']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => Store::factory()->create()->id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $po))->toBeFalse();
});

it('allows draft-only update and delete', function () {
    $draft = PurchaseOrder::factory()->create(['status' => 'draft']);
    $sent = PurchaseOrder::factory()->sent()->create();

    $member = Member::factory()->create();
    $editor = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $editor->givePermissionTo(['costs.view', 'purchase_orders.edit', 'purchase_orders.delete']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $draft->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->update($editor, $draft))->toBeTrue()
        ->and($this->policy->update($editor, $sent))->toBeFalse()
        ->and($this->policy->delete($editor, $draft))->toBeTrue()
        ->and($this->policy->delete($editor, $sent))->toBeFalse();
});

it('allows send only on draft purchase orders', function () {
    $draft = PurchaseOrder::factory()->create(['status' => 'draft']);
    $sent = PurchaseOrder::factory()->sent()->create();

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $user->givePermissionTo(['costs.view', 'purchase_orders.send']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $draft->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->send($user, $draft))->toBeTrue()
        ->and($this->policy->send($user, $sent))->toBeFalse();
});
