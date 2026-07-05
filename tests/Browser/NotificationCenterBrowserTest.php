<?php

use App\Models\User;
use Pest\Browser\Api\Webpage;

it('loads dashboard with notification bell when browser plugin is available', function () {
    if (! interface_exists(Webpage::class)) {
        $this->markTestSkipped('Install pestphp/pest-plugin-browser and Playwright to run browser smoke tests.');
    }

    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavascriptErrors();
})->group('browser');
