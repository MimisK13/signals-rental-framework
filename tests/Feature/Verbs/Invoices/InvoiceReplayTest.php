<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('reconstructs invoice projections byte-identically after replay with interleaved types', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();

    $standard = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
        'reference' => 'PO-100',
    ]));

    $deposit = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Deposit->value,
    ]));

    $proforma = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Proforma->value,
    ]));

    $secondStandard = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
    ]));

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $invoice): array => $invoice->getAttributes(),
    )->all();

    $itemsBefore = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $item): array => $item->getAttributes(),
    )->all();

    $taxesBefore = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $tax): array => $tax->getAttributes(),
    )->all();

    expect($invoicesBefore)->toHaveCount(4)
        ->and(collect($invoicesBefore)->pluck('id')->all())->toBe([
            $standard->id,
            $deposit->id,
            $proforma->id,
            $secondStandard->id,
        ])
        ->and(collect($invoicesBefore)->pluck('type')->all())->toBe([
            'standard',
            'deposit',
            'proforma',
            'standard',
        ]);

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $invoice): array => $invoice->getAttributes(),
    )->all();

    $itemsAfter = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $item): array => $item->getAttributes(),
    )->all();

    $taxesAfter = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $tax): array => $tax->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($itemsAfter)->toBe($itemsBefore)
        ->and($taxesAfter)->toBe($taxesBefore);
});

it('allocates separate numbers per invoice type within a store', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();

    $standard = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
    ]));

    $deposit = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Deposit->value,
    ]));

    expect(Invoice::query()->whereKey($standard->id)->value('number'))->toBe('INV-2026-0001')
        ->and(Invoice::query()->whereKey($deposit->id)->value('number'))->toBe('DEP-2026-0001');
});

it('forbids users without invoices.create permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('invoices.view');

    $this->actingAs($viewer);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
})->throws(AuthorizationException::class);
