<?php

use App\Enums\InvoiceType;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxCategorySeeder;
use Database\Seeders\TaxClassSeeder;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TaxRuleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Database\Seeders\TaxZoneSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Queue::fake();

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(TaxZoneSeeder::class);
    $this->seed(TaxClassSeeder::class);
    $this->seed(TaxRateSeeder::class);
    $this->seed(TaxCategorySeeder::class);
    $this->seed(TaxRuleSeeder::class);
    $this->seed(TaxZoneRuleSeeder::class);
    $this->seed(ProductGroupSeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(3)
        ->create(['tag_list' => ['demo-data']]);

    seedDemoProducts();
    seedDemoOpportunities();
});

it('seeds demo invoices tagged demo-data via the event-sourced path', function () {
    $eventsBefore = DB::table('verb_events')->count();

    seedDemoInvoices();

    $invoices = Invoice::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get();

    expect($invoices)->not->toBeEmpty()
        ->and(DB::table('verb_events')->count())->toBeGreaterThan($eventsBefore);

    $invoices->each(function (Invoice $invoice): void {
        expect($invoice->tag_list)->toContain('demo-data')
            ->and($invoice->state_id)->toBeGreaterThan(0);
    });
});

it('creates the expected invoice lifecycle references', function () {
    seedDemoInvoices();

    $references = Invoice::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->pluck('reference')
        ->all();

    expect($references)->toContain('DEMO-INV-DRAFT')
        ->and($references)->toContain('DEMO-INV-PAID')
        ->and($references)->toContain('DEMO-INV-PARTIAL')
        ->and($references)->toContain('DEMO-INV-OVERDUE')
        ->and($references)->toContain('DEMO-INV-DEPOSIT')
        ->and($references)->toContain('DEMO-INV-PROFORMA');
});

it('marks the paid demo invoice fully settled', function () {
    seedDemoInvoices();

    $paid = Invoice::query()->where('reference', 'DEMO-INV-PAID')->firstOrFail();

    expect($paid->status)->toBe('paid')
        ->and($paid->amount_outstanding)->toBe(0);
});

it('creates deposit and proforma invoice types', function () {
    seedDemoInvoices();

    $deposit = Invoice::query()->where('reference', 'DEMO-INV-DEPOSIT')->firstOrFail();
    $proforma = Invoice::query()->where('reference', 'DEMO-INV-PROFORMA')->firstOrFail();

    expect($deposit->typeEnum())->toBe(InvoiceType::Deposit)
        ->and($proforma->typeEnum())->toBe(InvoiceType::Proforma);
});

it('matches the paid invoice total to its source opportunity charge total', function () {
    seedDemoInvoices();

    $opportunity = Opportunity::query()->where('reference', 'DEMO-OPP-0002')->firstOrFail();
    $invoice = Invoice::query()->where('reference', 'DEMO-INV-PAID')->firstOrFail();

    expect($invoice->total)->toBe((int) $opportunity->charge_total);
});

it('records partial payment, refund, and credit note on the partial demo invoice', function () {
    seedDemoInvoices();

    $invoice = Invoice::query()->where('reference', 'DEMO-INV-PARTIAL')->firstOrFail();

    expect($invoice->amount_paid)->toBeGreaterThan(0)
        ->and($invoice->amount_outstanding)->toBeGreaterThan(0)
        ->and(CreditNote::query()->where('invoice_id', $invoice->id)->exists())->toBeTrue();
});

it('is idempotent on re-run', function () {
    seedDemoInvoices();
    $firstCount = Invoice::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoInvoices();

    expect(Invoice::query()->whereJsonContains('tag_list', 'demo-data')->count())->toBe($firstCount);
});
