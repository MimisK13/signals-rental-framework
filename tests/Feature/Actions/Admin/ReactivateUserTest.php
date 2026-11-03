<?php

use App\Domain\Administration\Actions\ReactivateUser;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('reactivates a deactivated user', function () {
    $user = User::factory()->deactivated()->create();

    $result = (new ReactivateUser)($user);

    expect($result->isActive())->toBeTrue();
    expect($result->deactivated_at)->toBeNull();
});

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $user = User::factory()->deactivated()->create();

    (new ReactivateUser)($user);
})->throws(AuthorizationException::class);
