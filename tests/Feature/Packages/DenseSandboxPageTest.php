<?php

use App\Models\User;

it('renders the dense foundation sandbox for authenticated users', function (): void {
    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('dense.sandbox'));

    $response->assertSuccessful()
        ->assertSee('Dense UI foundation')
        ->assertSee('Primary')
        ->assertSee('Notification preferences')
        ->assertSee('Awaiting inspection')
        ->assertSee('Table card')
        ->assertSee('Table variants')
        ->assertSee('Date and store controls')
        ->assertSee('Store picker')
        ->assertSee('Calendar')
        ->assertSee('No reservations yet')
        ->assertSee('dense::');
});
