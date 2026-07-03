<?php

use App\Models\BundleRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the bundle rules admin index', function () {
    BundleRule::factory()->create(['name' => 'AV bundle']);

    Volt::test('admin.settings.pricing.bundle-rules')
        ->assertSee('AV bundle')
        ->assertSee('Bundle Rules');
});

it('creates a bundle rule from the admin form', function () {
    Volt::test('admin.settings.pricing.bundle-rule-form')
        ->set('name', 'Stage 1.5 bundle')
        ->set('pricingMode', 'fixed')
        ->set('bundleFixedMinor', '15000')
        ->call('save')
        ->assertHasNoErrors();

    expect(BundleRule::query()->where('name', 'Stage 1.5 bundle')->exists())->toBeTrue();
});
