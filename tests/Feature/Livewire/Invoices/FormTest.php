<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Presentation\Livewire\Form;
use App\Domain\Members\Models\Member;
use App\Domain\Stores\Models\Store;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('creates a draft invoice from the form', function () {
    $member = Member::factory()->organisation()->create();
    $store = Store::factory()->create();

    Livewire::test(Form::class)
        ->set('memberId', $member->id)
        ->set('storeId', $store->id)
        ->set('notes', 'Test draft')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Invoice::query()->where('notes', 'Test draft')->exists())->toBeTrue();
});
