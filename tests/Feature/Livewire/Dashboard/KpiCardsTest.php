<?php

use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityStatus;
use App\Livewire\Dashboard\KpiCards;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Store;
use App\Models\User;
use App\Services\Dashboard\DashboardKpiService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('renders KPI values derived from the database', function () {
    Opportunity::factory()->count(2)->quotation()->create();
    Opportunity::factory()->count(3)->order()->create();

    Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 50000,
        'amount_outstanding' => 50000,
        'due_at' => Carbon::now()->subDays(10),
    ]);

    Livewire::actingAs($this->owner)
        ->test(KpiCards::class)
        ->assertOk()
        ->assertSee('Active Opportunities')
        ->assertSee('5')
        ->assertSee('2 quotations')
        ->assertSee('3 orders');

    $metrics = Livewire::actingAs($this->owner)
        ->test(KpiCards::class)
        ->viewData('metrics');

    expect($metrics['revenue']['current_minor'])->toBe(50000)
        ->and($metrics['overdue']['invoice_count'])->toBe(1);
});

it('counts on-hire units from dispatched assets and bulk quantities', function () {
    $opportunity = Opportunity::factory()->order()->create();

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'dispatched_quantity' => '3.00',
        'returned_quantity' => '1.00',
    ]);

    OpportunityItemAsset::factory()
        ->for($item, 'item')
        ->create(['status' => AssetAssignmentStatus::Dispatched->value]);

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['on_hire']['units'])->toBe(3)
        ->and($metrics['on_hire']['active_jobs'])->toBe(1);
});

it('scopes KPI queries to stores the user can access', function () {
    $allowedStore = Store::factory()->create();
    $blockedStore = Store::factory()->create();

    $member = Member::factory()->create();
    MemberStore::query()->create([
        'member_id' => $member->id,
        'store_id' => $allowedStore->id,
    ]);

    $scopedUser = User::factory()->create([
        'member_id' => $member->id,
        'is_owner' => false,
        'is_admin' => false,
    ]);

    Opportunity::factory()->quotation()->create(['store_id' => $allowedStore->id]);
    Opportunity::factory()->quotation()->create(['store_id' => $blockedStore->id]);

    $metrics = app(DashboardKpiService::class)->metrics($scopedUser);

    expect($metrics['active_opportunities']['total'])->toBe(1);
});

it('caches KPI metrics for five minutes per user', function () {
    Cache::flush();

    Opportunity::factory()->quotation()->create();

    Livewire::actingAs($this->owner)->test(KpiCards::class);

    Opportunity::factory()->count(4)->quotation()->create();

    $component = Livewire::actingAs($this->owner)->test(KpiCards::class);

    expect($component->viewData('metrics')['active_opportunities']['total'])->toBe(1);
});

it('excludes closed quotations and completed orders from active opportunities', function () {
    Opportunity::factory()->quotation()->create([
        'status' => OpportunityStatus::QuotationLost->statusValue(),
    ]);
    Opportunity::factory()->order()->create([
        'status' => OpportunityStatus::OrderComplete->statusValue(),
    ]);
    Opportunity::factory()->quotation()->create();
    Opportunity::factory()->order()->create();

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['active_opportunities']['total'])->toBe(2)
        ->and($metrics['active_opportunities']['quotations'])->toBe(1)
        ->and($metrics['active_opportunities']['orders'])->toBe(1);
});

it('aggregates overdue invoice totals and counts', function () {
    Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->subDays(40),
        'due_at' => Carbon::now()->subDays(14),
        'total' => 10000,
        'amount_outstanding' => 10000,
        'amount_paid' => 0,
    ]);

    Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->subDays(50),
        'due_at' => Carbon::now()->subDays(7),
        'total' => 5000,
        'amount_outstanding' => 5000,
        'amount_paid' => 0,
    ]);

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['overdue']['invoice_count'])->toBe(2)
        ->and($metrics['overdue']['total_outstanding_minor'])->toBe(15000);
});

it('excludes proforma invoices from revenue', function () {
    Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 10000,
    ]);

    Invoice::factory()->proforma()->issued()->create([
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 50000,
    ]);

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['revenue']['current_minor'])->toBe(10000);
});

it('excludes void invoices from revenue', function () {
    Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 10000,
    ]);

    Invoice::factory()->issued()->create([
        'status' => 'void',
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 50000,
    ]);

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['revenue']['current_minor'])->toBe(10000);
});

it('subtracts credit notes issued in the same period from revenue', function () {
    $invoice = Invoice::factory()->issued()->create([
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => 10000,
    ]);

    CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        'total' => -1200,
    ]);

    $metrics = app(DashboardKpiService::class)->metrics($this->owner);

    expect($metrics['revenue']['current_minor'])->toBe(8800);
});
