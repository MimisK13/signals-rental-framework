<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\UpdateDraftInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Opportunities\LockOpportunity;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\UpdateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('invoices.due_days', 14);
    settings()->set('invoices.lock_opportunity_on_issue', true);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function invoiceLifecycleFixture(): array
{
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();

    return compact('store', 'member');
}

it('issues a draft invoice with due date from settings and locks it', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
    ]));

    $issued = (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));

    expect($issued->status)->toBe('issued')
        ->and($issued->issued_at)->toBe('2026-07-03T12:00:00.000Z')
        ->and($issued->due_at)->toBe('2026-07-17T12:00:00.000Z');

    $row = Invoice::query()->findOrFail($draft->id);

    expect($row->is_locked)->toBeTrue()
        ->and($row->status)->toBe('issued');
});

it('locks the source opportunity when configured at fire-time', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'member_id' => $member->id,
    ]);

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
    ]));

    (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();
});

it('skips opportunity locking when setting is disabled at fire-time', function () {
    settings()->set('invoices.lock_opportunity_on_issue', false);

    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'member_id' => $member->id,
    ]);

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
    ]));

    (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();
});

it('skips opportunity locking when the opportunity is already locked at fire-time', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'member_id' => $member->id,
    ]);

    (new LockOpportunity)($opportunity);

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
    ]));

    $eventsBeforeIssue = verbEventCount();

    (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue()
        ->and(verbEventCount())->toBe($eventsBeforeIssue + 1);
});

it('rejects draft updates once issued with 422', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueInvoice)($invoice);

    (new UpdateDraftInvoice)(
        $invoice->refresh(),
        UpdateDraftInvoiceData::fromNotesAndReference('Updated notes', 'REF-2'),
    );
})->throws(AuthorizationException::class);

it('allows draft updates before issue', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'notes' => 'Original',
    ]));

    $updated = (new UpdateDraftInvoice)(
        Invoice::query()->findOrFail($draft->id),
        UpdateDraftInvoiceData::fromNotesAndReference('Revised', 'PO-9'),
    );

    expect($updated->notes)->toBe('Revised')
        ->and($updated->reference)->toBe('PO-9');
});

it('voids a draft invoice', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $voided = (new VoidInvoice)(Invoice::query()->findOrFail($draft->id));

    expect($voided->status)->toBe('void');
});

it('voids an issued invoice with no payments', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $voided = (new VoidInvoice)($invoice->refresh());

    expect($voided->status)->toBe('void');
});

it('rejects voiding when payments exist with 422', function () {
    ['store' => $store, 'member' => $member] = invoiceLifecycleFixture();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $invoice->refresh()->update([
        'amount_paid' => 1000,
        'amount_outstanding' => 0,
    ]);

    (new VoidInvoice)($invoice->refresh());
})->throws(ValidationException::class, 'credit note');

it('forbids users without invoices.issue permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('invoices.view', 'invoices.create', 'invoices.edit');

    $this->actingAs($viewer);

    $invoice = Invoice::factory()->create();

    (new IssueInvoice)($invoice);
})->throws(AuthorizationException::class);

it('forbids users without invoices.void permission', function () {
    $issuer = User::factory()->create();
    $issuer->givePermissionTo('invoices.view', 'invoices.create', 'invoices.issue');

    $this->actingAs($issuer);

    $invoice = Invoice::factory()->issued()->create();

    (new VoidInvoice)($invoice);
})->throws(AuthorizationException::class);
