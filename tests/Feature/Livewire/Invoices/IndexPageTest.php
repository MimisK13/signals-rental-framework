<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Presentation\Livewire\Index;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('renders the invoices index page', function () {
    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('Invoices');
});

it('lists invoices on the index page', function () {
    $invoice = Invoice::factory()->create(['number' => 'INV-LIST-1']);

    Livewire::test(Index::class)
        ->assertSee('INV-LIST-1');
});

it('filters invoices by stored status chip', function () {
    Invoice::factory()->create(['status' => 'draft', 'number' => 'INV-DRAFT']);
    Invoice::factory()->issued()->create(['number' => 'INV-ISSUED']);

    Livewire::test(Index::class)
        ->call('setStatusFilter', 'draft')
        ->assertSee('INV-DRAFT')
        ->assertDontSee('INV-ISSUED');
});

it('counts overdue invoices from the overdue scope rather than stored status', function () {
    Invoice::factory()->issued()->create([
        'number' => 'INV-OVERDUE',
        'amount_outstanding' => 10_000,
        'total' => 10_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    Invoice::factory()->issued()->create([
        'number' => 'INV-CURRENT',
        'amount_outstanding' => 10_000,
        'total' => 10_000,
        'due_at' => CarbonImmutable::parse('2026-07-20T12:00:00Z'),
    ]);

    Livewire::test(Index::class)
        ->assertSet('statusCounts.overdue', 1);
});

it('filters invoices by the overdue scope when the overdue tab is selected', function () {
    Invoice::factory()->issued()->create([
        'number' => 'INV-OVERDUE',
        'amount_outstanding' => 10_000,
        'total' => 10_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    Invoice::factory()->issued()->create([
        'number' => 'INV-CURRENT',
        'amount_outstanding' => 10_000,
        'total' => 10_000,
        'due_at' => CarbonImmutable::parse('2026-07-20T12:00:00Z'),
    ]);

    Livewire::test(Index::class)
        ->call('setStatusFilter', 'overdue')
        ->assertSee('INV-OVERDUE')
        ->assertDontSee('INV-CURRENT');
});

it('shows the derived overdue badge on index rows', function () {
    Invoice::factory()->issued()->create([
        'number' => 'INV-OVERDUE-BADGE',
        'amount_outstanding' => 5_000,
        'total' => 5_000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    Livewire::test(Index::class)
        ->assertSee('INV-OVERDUE-BADGE')
        ->assertSee('Overdue');
});
