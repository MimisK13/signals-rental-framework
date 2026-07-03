<?php

use App\Models\Invoice;
use App\Models\Member;
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

it('lists member invoices with outstanding summary', function () {
    $member = Member::factory()->organisation()->create();
    Invoice::factory()->issued()->create([
        'member_id' => $member->id,
        'total' => 10000,
        'amount_outstanding' => 5000,
        'amount_paid' => 5000,
    ]);

    Volt::test('members.invoices', ['member' => $member])
        ->assertSee('Outstanding Balance')
        ->assertSee('50.00');
});
