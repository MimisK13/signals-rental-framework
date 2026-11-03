<?php

use App\Domain\Administration\Models\User;
use App\Domain\System\Presentation\Livewire\Admin\SystemHealth;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the system health page', function () {
    $this->get(route('admin.settings.system-health'))
        ->assertOk()
        ->assertSee('System Health');
});

it('displays health check results', function () {
    Livewire::test(SystemHealth::class)
        ->assertSet('checks', fn ($checks) => count($checks) === 6);
});

it('can refresh health checks', function () {
    Livewire::test(SystemHealth::class)
        ->call('refresh')
        ->assertSet('checks', fn ($checks) => count($checks) === 6);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.system-health'))
        ->assertForbidden();
});
