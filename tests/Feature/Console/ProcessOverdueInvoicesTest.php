<?php

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('processes newly overdue invoices and marks them notified', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 12_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 newly overdue');

    expect($invoice->fresh()->overdue_notified_at)->not->toBeNull();
});

it('is idempotent across daily runs', function () {
    Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 12_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')->assertSuccessful();
    $this->artisan('invoices:process-overdue')
        ->assertSuccessful()
        ->expectsOutputToContain('No newly overdue invoices found.');
});

it('does not write overdue as a stored status', function () {
    Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 12_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')->assertSuccessful();

    expect(Invoice::query()->where('status', 'overdue')->exists())->toBeFalse();
});

it('skips paid and draft invoices', function () {
    Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 0,
        'amount_paid' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    Invoice::factory()->create([
        'status' => 'draft',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')
        ->assertSuccessful()
        ->expectsOutputToContain('No newly overdue invoices found.');
});
