<?php

use App\Domain\Administration\Models\User;
use App\Domain\Lists\Database\Seeders\ListOfValuesSeeder;
use App\Domain\Members\Models\Address;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Livewire\Addresses;
use App\Domain\Members\Presentation\Livewire\AddressForm;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the addresses page', function () {
    $this->get("/members/{$this->member->id}/addresses")
        ->assertOk()
        ->assertSee('Addresses');
});

it('lists addresses for a member', function () {
    Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
        'city' => 'London',
    ]);
    Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
        'city' => 'Manchester',
    ]);

    Livewire::test(Addresses::class, ['member' => $this->member])
        ->assertSee('London')
        ->assertSee('Manchester');
});

it('shows empty state when no addresses exist', function () {
    Livewire::test(Addresses::class, ['member' => $this->member])
        ->assertSee('No addresses found.');
});

it('shows primary badge on primary address', function () {
    Address::factory()->primary()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
        'city' => 'Primary City',
    ]);

    Livewire::test(Addresses::class, ['member' => $this->member])
        ->assertSee('Primary');
});

it('can delete an address', function () {
    $address = Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
    ]);

    Livewire::test(Addresses::class, ['member' => $this->member])
        ->call('deleteAddress', $address->id);

    expect(Address::find($address->id))->toBeNull();
});

it('renders the create address form', function () {
    $this->get("/members/{$this->member->id}/addresses/create")
        ->assertOk()
        ->assertSee('Add Address');
});

it('populates the type dropdown with the seeded Address Type list values', function () {
    // Regression: the form queried 'AddressType' (no space) but the seeded list
    // name is 'Address Type', so the Type select rendered blank.
    $this->seed(ListOfValuesSeeder::class);

    Livewire::test(AddressForm::class, ['member' => $this->member])
        ->assertViewHas('addressTypes', fn ($types) => $types->isNotEmpty())
        ->assertSee('Billing')
        ->assertSee('Shipping')
        ->assertSee('Primary')
        ->assertSee('Registered');
});

it('can create an address', function () {
    Livewire::test(AddressForm::class, ['member' => $this->member])
        ->set('name', 'Head Office')
        ->set('street', '123 Main St')
        ->set('city', 'London')
        ->set('postcode', 'SW1A 1AA')
        ->call('save')
        ->assertRedirect();

    expect($this->member->addresses()->where('city', 'London')->exists())->toBeTrue();
});

it('can edit an address', function () {
    $address = Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
        'city' => 'Old City',
    ]);

    Livewire::test(AddressForm::class, ['member' => $this->member, 'address' => $address])
        ->assertSet('city', 'Old City')
        ->set('city', 'New City')
        ->call('save')
        ->assertRedirect();

    expect($address->fresh()->city)->toBe('New City');
});

it('can set primary address', function () {
    $existing = Address::factory()->primary()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $this->member->id,
    ]);

    Livewire::test(AddressForm::class, ['member' => $this->member])
        ->set('city', 'New Primary City')
        ->set('isPrimary', true)
        ->call('save')
        ->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
    expect($this->member->addresses()->where('city', 'New Primary City')->first()->is_primary)->toBeTrue();
});

it('cannot delete another member\'s address', function () {
    $otherMember = Member::factory()->create();
    $otherAddress = Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $otherMember->id,
    ]);

    Livewire::test(Addresses::class, ['member' => $this->member])
        ->call('deleteAddress', $otherAddress->id)
        ->assertNotFound();

    expect(Address::find($otherAddress->id))->not->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/addresses")
        ->assertRedirect();
});
