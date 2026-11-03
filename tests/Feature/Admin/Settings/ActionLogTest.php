<?php

use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\AuditLog\Presentation\Livewire\Admin\Index;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the action log page', function () {
    $this->get(route('admin.settings.action-log'))
        ->assertOk()
        ->assertSee('Action Log');
});

it('displays action log entries', function () {
    ActionLog::factory()->count(3)->create();

    Livewire::test(Index::class)
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
});

it('filters by action', function () {
    ActionLog::factory()->create(['action' => 'created']);
    ActionLog::factory()->create(['action' => 'updated']);
    ActionLog::factory()->create(['action' => 'deleted']);

    Livewire::test(Index::class)
        ->set('filterAction', 'created')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
});

it('filters by entity type', function () {
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\User']);
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\Role']);

    Livewire::test(Index::class)
        ->set('filterEntityType', 'App\\Models\\User')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
});

it('filters by user name', function () {
    $alice = User::factory()->create(['name' => 'Alice Smith']);
    $bob = User::factory()->create(['name' => 'Bob Jones']);
    ActionLog::factory()->forUser($alice)->create();
    ActionLog::factory()->forUser($bob)->create();

    Livewire::test(Index::class)
        ->set('filterUser', 'Alice')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
});

it('expands a row to show details', function () {
    $log = ActionLog::factory()->withChanges(
        ['name' => 'Old Name'],
        ['name' => 'New Name'],
    )->create();

    Livewire::test(Index::class)
        ->call('toggleRow', $log->id)
        ->assertSet('expandedRow', $log->id)
        ->assertSee('Old Name')
        ->assertSee('New Name');
});

it('collapses an expanded row', function () {
    $log = ActionLog::factory()->create();

    Livewire::test(Index::class)
        ->call('toggleRow', $log->id)
        ->assertSet('expandedRow', $log->id)
        ->call('toggleRow', $log->id)
        ->assertSet('expandedRow', null);
});

it('clears all filters', function () {
    Livewire::test(Index::class)
        ->set('filterAction', 'created')
        ->set('filterUser', 'test')
        ->call('clearFilters')
        ->assertSet('filterAction', '')
        ->assertSet('filterUser', '');
});

it('shows empty state when no entries', function () {
    Livewire::test(Index::class)
        ->assertSee('No action log entries found.');
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.action-log'))
        ->assertForbidden();
});
