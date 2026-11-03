<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Livewire\Invoices;
use Livewire\Livewire;

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

    Livewire::test(Invoices::class, ['member' => $member])
        ->assertSee('Outstanding Balance')
        ->assertSee('50.00');
});
