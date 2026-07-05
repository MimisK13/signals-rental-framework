<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Policies\VirtualStockIntakePolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new VirtualStockIntakePolicy;
});

it('allows view with virtual_stock.view without costs.view', function () {
    $intake = VirtualStockIntake::factory()->create();

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $viewer->givePermissionTo(['virtual_stock.view', 'virtual_stock.access']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $intake->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $intake))->toBeTrue();
});

it('allows view with costs.view and virtual_stock.view', function () {
    $intake = VirtualStockIntake::factory()->create();

    $member = Member::factory()->create();
    $viewer = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $viewer->givePermissionTo(['virtual_stock.view', 'virtual_stock.access', 'costs.view']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $intake->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->view($viewer, $intake))->toBeTrue();
});

it('allows draft-only update and delete', function () {
    $draft = VirtualStockIntake::factory()->create(['status' => 'draft']);
    $confirmed = VirtualStockIntake::factory()->confirmed()->create();

    $member = Member::factory()->create();
    $editor = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $editor->givePermissionTo(['costs.view', 'virtual_stock.edit', 'virtual_stock.delete']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $draft->store_id,
        'created_at' => now(),
    ]);

    expect($this->policy->update($editor, $draft))->toBeTrue()
        ->and($this->policy->update($editor, $confirmed))->toBeFalse()
        ->and($this->policy->delete($editor, $draft))->toBeTrue()
        ->and($this->policy->delete($editor, $confirmed))->toBeFalse();
});
