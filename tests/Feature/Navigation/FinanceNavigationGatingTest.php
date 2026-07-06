<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

$creditNotesNav = 'Refunds &amp; adjustments';
$paymentsNav = 'Receipts &amp; reconciliation';

describe('Finance nav gating on invoices.access', function () use ($creditNotesNav, $paymentsNav) {
    it('shows credit notes and payments links to a user with invoices.access', function () use ($creditNotesNav, $paymentsNav) {
        $user = User::factory()->create();
        $user->givePermissionTo(['invoices.access', 'invoices.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('credit-notes.index'), false)
            ->assertSee(route('payments.index'), false)
            ->assertSee($creditNotesNav, false)
            ->assertSee($paymentsNav, false);
    });

    it('hides credit notes and payments links from a user without invoices.access', function () use ($creditNotesNav, $paymentsNav) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('credit-notes.index'), false)
            ->assertDontSee(route('payments.index'), false)
            ->assertDontSee($creditNotesNav, false)
            ->assertDontSee($paymentsNav, false);
    });
});

describe('Finance nav excludes virtual stock', function () {
    it('does not show virtual stock links to finance-only users', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['invoices.access', 'invoices.view', 'purchase_orders.access', 'purchase_orders.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('virtual-stock.index'), false)
            ->assertDontSee('Sub-hire supply windows', false);
    });
});
