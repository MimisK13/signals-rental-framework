<?php

use App\Domain\Administration\Actions\DeactivateUser;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deactivates a user', function () {
    $user = User::factory()->create();

    $result = (new DeactivateUser)($user);

    expect($result->isActive())->toBeFalse();
    expect($result->deactivated_at)->not->toBeNull();
});

it('prevents deactivating the owner', function () {
    $owner = User::factory()->owner()->create();

    (new DeactivateUser)($owner);
})->throws(ValidationException::class);

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $user = User::factory()->create();

    (new DeactivateUser)($user);
})->throws(AuthorizationException::class);
