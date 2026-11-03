<?php

use App\Domain\Pricing\Models\BundleRule;
use App\Domain\Pricing\Presentation\Livewire\BundleRuleForm;
use App\Domain\Pricing\Presentation\Livewire\BundleRules;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the bundle rules admin index', function () {
    BundleRule::factory()->create(['name' => 'AV bundle']);

    Livewire::test(BundleRules::class)
        ->assertSee('AV bundle')
        ->assertSee('Bundle Rules');
});

it('creates a bundle rule from the admin form', function () {
    Livewire::test(BundleRuleForm::class)
        ->set('name', 'Stage 1.5 bundle')
        ->set('pricingMode', 'fixed')
        ->set('bundleFixedMinor', '15000')
        ->call('save')
        ->assertHasNoErrors();

    expect(BundleRule::query()->where('name', 'Stage 1.5 bundle')->exists())->toBeTrue();
});
