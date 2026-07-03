<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('invoices.due_days', 30);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('reconstructs generate issue void lifecycle byte-identically after replay', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
        'reference' => 'LIFE-001',
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueInvoice)($invoice);
    (new VoidInvoice)($invoice->refresh());

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $itemsBefore = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    $taxesBefore = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $itemsAfter = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    $taxesAfter = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($itemsAfter)->toBe($itemsBefore)
        ->and($taxesAfter)->toBe($taxesBefore);
});

it('reconstructs opportunity-linked issue with lock side effects without appending events on replay', function () {
    settings()->set('invoices.lock_opportunity_on_issue', true);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();
    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'member_id' => $member->id,
    ]);

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
        'type' => InvoiceType::Standard->value,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueInvoice)($invoice);
    (new VoidInvoice)($invoice->refresh());

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $opportunitiesBefore = Opportunity::query()->orderBy('id')->get()->map(
        fn (Opportunity $row): array => $row->getAttributes(),
    )->all();

    expect($opportunitiesBefore[0]['exchange_rate_locked'] ?? null)->toBeTruthy()
        ->and($opportunitiesBefore[0]['tax_locked'] ?? null)->toBeTruthy();

    $eventCountBeforeReplay = verbEventCount();

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $opportunitiesAfter = Opportunity::query()->orderBy('id')->get()->map(
        fn (Opportunity $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($opportunitiesAfter)->toBe($opportunitiesBefore);
});
