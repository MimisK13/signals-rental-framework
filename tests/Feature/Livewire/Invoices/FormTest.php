<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a draft invoice from the form', function () {
    $member = Member::factory()->organisation()->create();
    $store = Store::factory()->create();

    Volt::test('invoices.form')
        ->set('memberId', $member->id)
        ->set('storeId', $store->id)
        ->set('notes', 'Test draft')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Invoice::query()->where('notes', 'Test draft')->exists())->toBeTrue();
});
