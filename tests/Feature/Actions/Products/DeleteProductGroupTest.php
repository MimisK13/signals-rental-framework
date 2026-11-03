<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Products\Actions\DeleteProductGroup;
use App\Domain\Products\Models\ProductGroup;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('deletes a product group', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);

    expect(ProductGroup::find($group->id))->toBeNull();

    // The AuditableEvent → LogAction listener is not faked here, so the deletion
    // is recorded end-to-end in action_logs against the acting user.
    assertActionLogged('product_group.deleted', ProductGroup::class, $group->id, $user->id);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);
})->throws(AuthorizationException::class);
