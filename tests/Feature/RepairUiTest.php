<?php

use App\Models\Product;
use App\Models\Repair;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['stock.view', 'stock.adjust']);
    $this->actingAs($user);
});

it('shows the repair list and detail pages to stock users', function (): void {
    $repair = Repair::factory()->forBulk(
        Product::factory()->bulk()->create(),
        Store::factory()->create(),
        3,
    )->create();

    $this->get(route('repairs.index'))
        ->assertSuccessful()
        ->assertSee('Repairs');

    $this->get(route('repairs.show', $repair))
        ->assertSuccessful()
        ->assertSee('Repair')
        ->assertSee($repair->product->name);
});

it('protects repair pages using stock access', function (): void {
    $this->actingAs(User::factory()->create());
    $repair = Repair::factory()->forBulk(
        Product::factory()->bulk()->create(),
        Store::factory()->create(),
        1,
    )->create();

    $this->get(route('repairs.index'))->assertForbidden();
    $this->get(route('repairs.show', $repair))->assertForbidden();
});
