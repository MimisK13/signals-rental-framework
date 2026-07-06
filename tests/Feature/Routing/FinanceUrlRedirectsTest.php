<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

it('redirects legacy finance credit notes URL to bare credit notes index with 301', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['invoices.access', 'invoices.view']);

    $this->actingAs($user)
        ->get('/finance/credit-notes')
        ->assertRedirect(route('credit-notes.index'))
        ->assertStatus(301);
});

it('redirects legacy finance payments URL to bare payments index with 301', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['invoices.access', 'invoices.view']);

    $this->actingAs($user)
        ->get('/finance/payments')
        ->assertRedirect(route('payments.index'))
        ->assertStatus(301);
});

it('serves credit notes and payments at bare top-level URLs', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['invoices.access', 'invoices.view']);

    $this->actingAs($user)
        ->get('/credit-notes')
        ->assertOk()
        ->assertSee('Credit Notes');

    $this->actingAs($user)
        ->get('/payments')
        ->assertOk()
        ->assertSee('Payments');
});
