<?php

use App\Domain\Administration\Models\User;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\ReferenceData\Presentation\Livewire\Admin\Countries;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the countries page', function () {
    $this->get(route('admin.settings.countries'))
        ->assertOk()
        ->assertSee('Countries');
});

it('lists countries', function () {
    Country::factory()->create(['name' => 'United Kingdom']);
    Country::factory()->create(['name' => 'France']);

    Livewire::test(Countries::class)
        ->assertSee('United Kingdom')
        ->assertSee('France');
});

it('can toggle active status', function () {
    $country = Country::factory()->create(['is_active' => true]);

    Livewire::test(Countries::class)
        ->call('toggleActive', $country->id);

    expect($country->fresh()->is_active)->toBeFalse();
});

it('can search countries', function () {
    Country::factory()->create(['name' => 'United Kingdom']);
    Country::factory()->create(['name' => 'France']);

    Livewire::test(Countries::class)
        ->set('search', 'United')
        ->assertSee('United Kingdom')
        ->assertDontSee('France');
})->skip(fn () => config('database.default') === 'sqlite', 'ilike requires PostgreSQL');

it('can toggle country back to active', function () {
    $country = Country::factory()->create(['is_active' => false]);

    Livewire::test(Countries::class)
        ->call('toggleActive', $country->id);

    expect($country->fresh()->is_active)->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.countries'))
        ->assertForbidden();
});
