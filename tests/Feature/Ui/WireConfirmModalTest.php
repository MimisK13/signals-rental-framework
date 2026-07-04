<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the global confirm modal shell on app pages that use wire:confirm', function () {
    $activity = Activity::factory()->create();

    $response = $this->get(route('activities.show', $activity));

    $response->assertOk();
    $response->assertSee('data-testid="signals-confirm-modal"', escape: false);
    $response->assertSee('wire:confirm="Are you sure you want to delete this activity?"', escape: false);
    $response->assertSee('data-testid="signals-confirm-submit"', escape: false);
});
