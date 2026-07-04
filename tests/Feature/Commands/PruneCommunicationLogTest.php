<?php

use App\Models\CommunicationLog;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('prunes communication log entries beyond retention', function () {
    CommunicationLog::factory()->sent()->create(['created_at' => now()->subDays(400)]);
    CommunicationLog::factory()->sent()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('communication-log:prune --days=365')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(1);
});

it('never prunes pinned communication log rows', function () {
    CommunicationLog::factory()->sent()->pinned()->create(['created_at' => now()->subDays(400)]);
    CommunicationLog::factory()->sent()->create(['created_at' => now()->subDays(400)]);

    $this->artisan('communication-log:prune --days=365')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(1)
        ->and(CommunicationLog::first()->is_pinned)->toBeTrue();
});

it('extends retention for non-terminal statuses within the safety window', function () {
    CommunicationLog::factory()->create([
        'status' => 'queued',
        'created_at' => now()->subDays(5),
    ]);

    CommunicationLog::factory()->create([
        'status' => 'queued',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('communication-log:prune --days=1 --safety-days=7')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(1)
        ->and(CommunicationLog::first()->status)->toBe('queued');
});

it('keeps non-terminal rows between retention and retention plus safety', function () {
    CommunicationLog::factory()->create([
        'status' => 'queued',
        'created_at' => now()->subDays(35),
    ]);

    $this->artisan('communication-log:prune --days=30 --safety-days=7')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 communication log entries');

    expect(CommunicationLog::count())->toBe(1);
});

it('prunes non-terminal rows older than retention plus safety', function () {
    CommunicationLog::factory()->create([
        'status' => 'queued',
        'created_at' => now()->subDays(40),
    ]);

    $this->artisan('communication-log:prune --days=30 --safety-days=7')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(0);
});

it('prunes terminal rows once older than retention', function () {
    CommunicationLog::factory()->failed()->create(['created_at' => now()->subDays(400)]);

    $this->artisan('communication-log:prune --days=365')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(0);
});

it('honours command option overrides for retention and safety', function () {
    CommunicationLog::factory()->create([
        'status' => 'queued',
        'created_at' => now()->subDays(20),
    ]);

    $this->artisan('communication-log:prune --days=10 --safety-days=5')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(0);
});

it('uses settings retention by default', function () {
    settings()->set('notifications.communication_log_retention_days', 180);

    CommunicationLog::factory()->sent()->create(['created_at' => now()->subDays(200)]);
    CommunicationLog::factory()->sent()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('communication-log:prune')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 communication log entries');

    expect(CommunicationLog::count())->toBe(1);
});
