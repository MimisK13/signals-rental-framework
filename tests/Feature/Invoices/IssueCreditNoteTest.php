<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteTax;
use App\Models\Invoice;
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
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{invoice: Invoice, total: int}
 */
function issuedCreditNoteFixture(): array
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->with(['items', 'taxes'])->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $invoice->refresh()->load(['items', 'taxes']);

    return [
        'invoice' => $invoice,
        'total' => $invoice->total,
    ];
}

it('issues a full credit note with negated mirror matching invoice tax exactly', function () {
    ['invoice' => $invoice] = issuedCreditNoteFixture();

    $originalTaxRows = $invoice->taxes->map(fn ($row): array => $row->only(['taxable_amount', 'tax_amount']))->all();
    $originalItemRows = $invoice->items->map(fn ($row): array => $row->only(['total', 'tax', 'discount']))->all();

    $result = (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );

    $invoice->refresh();
    $creditNote = CreditNote::query()->with(['items', 'taxes'])->findOrFail($result->id);

    expect($creditNote->total)->toBe(-$invoice->total)
        ->and($creditNote->subtotal)->toBe(-$invoice->subtotal)
        ->and($creditNote->tax_total)->toBe(-$invoice->tax_total)
        ->and($creditNote->store_id)->toBe($invoice->store_id)
        ->and($invoice->credited_total)->toBe($invoice->total)
        ->and($invoice->amount_outstanding)->toBe(0)
        ->and($invoice->status)->toBe('credited');

    $cnTaxRows = $creditNote->taxes->map(fn ($row): array => [
        'taxable_amount' => $row->taxable_amount,
        'tax_amount' => $row->tax_amount,
    ])->all();

    foreach ($originalTaxRows as $index => $original) {
        expect($cnTaxRows[$index]['taxable_amount'])->toBe(-$original['taxable_amount'])
            ->and($cnTaxRows[$index]['tax_amount'])->toBe(-$original['tax_amount']);
    }

    $cnItemRows = $creditNote->items->map(fn ($row): array => [
        'total' => $row->total,
        'tax' => $row->tax,
        'discount' => $row->discount,
    ])->all();

    foreach ($originalItemRows as $index => $original) {
        expect($cnItemRows[$index]['total'])->toBe(-$original['total'])
            ->and($cnItemRows[$index]['tax'])->toBe(-$original['tax'])
            ->and($cnItemRows[$index]['discount'])->toBe(-$original['discount']);
    }

    expect($creditNote->items->sum('tax'))->toBe($creditNote->taxes->sum('tax_amount'));
});

it('issues a partial quantity credit with penny-exact tax reconciliation', function () {
    ['invoice' => $invoice] = issuedCreditNoteFixture();

    $item = $invoice->items->first();
    expect($item)->not->toBeNull();

    $halfQty = bcdiv((string) $item->quantity, '2', 2);

    $result = (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => $halfQty],
            ],
        ]),
    );

    $invoice->refresh();
    $creditNote = CreditNote::query()->with(['items', 'taxes'])->findOrFail($result->id);

    expect($creditNote->items)->toHaveCount(1)
        ->and($creditNote->items->first()->invoice_item_id)->toBe($item->id)
        ->and(abs($creditNote->items->first()->total))->toBeLessThan($item->total)
        ->and($creditNote->taxes->sum('tax_amount'))->toBe($creditNote->items->sum('tax'))
        ->and($invoice->credited_total)->toBe(abs($creditNote->total))
        ->and($invoice->status)->toBe('issued');
});

it('reduces effective balance on a paid invoice', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedCreditNoteFixture();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->amount_outstanding)->toBe(0);

    $partialCredit = intdiv($total, 2);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $invoice->items->first()->id, 'quantity' => '0.50'],
            ],
        ]),
    );

    $invoice->refresh();

    expect($invoice->credited_total)->toBeGreaterThan(0)
        ->and($invoice->amount_outstanding)->toBe($invoice->total - $invoice->amount_paid - $invoice->credited_total)
        ->and($invoice->amount_outstanding)->toBeLessThan(0);
});

it('rejects over-crediting with 422', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedCreditNoteFixture();

    $item = $invoice->items->first();
    $halfQty = bcdiv((string) $item->quantity, '2', 2);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => $halfQty],
            ],
        ]),
    );

    (new IssueCreditNote)(
        $invoice->refresh(),
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id],
            ],
        ]),
    );
})->throws(ValidationException::class, 'cannot exceed');

it('rejects credit notes against draft invoices with 422', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );
})->throws(AuthorizationException::class);

it('rejects credit notes against void invoices with 422', function () {
    ['invoice' => $invoice] = issuedCreditNoteFixture();

    $invoice->update(['status' => 'void']);

    (new IssueCreditNote)(
        $invoice->refresh(),
        IssueCreditNoteData::from(['lines' => null]),
    );
})->throws(AuthorizationException::class);

it('maintains link integrity between credit note rows and invoice rows', function () {
    ['invoice' => $invoice] = issuedCreditNoteFixture();

    $result = (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );

    $creditNote = CreditNote::query()->with(['items', 'taxes'])->findOrFail($result->id);

    expect($creditNote->invoice_id)->toBe($invoice->id);

    foreach ($creditNote->items as $cnItem) {
        expect($cnItem->invoice_item_id)->toBeIn($invoice->items->pluck('id')->all())
            ->and(CreditNoteItem::query()->find($cnItem->id))->not->toBeNull();
    }

    foreach ($creditNote->taxes as $cnTax) {
        expect($cnTax->invoice_tax_id)->toBeIn($invoice->taxes->pluck('id')->all())
            ->and(CreditNoteTax::query()->find($cnTax->id))->not->toBeNull();
    }
});

it('accumulates partial credits until fully credited', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedCreditNoteFixture();

    $item = $invoice->items->first();
    $halfQty = bcdiv((string) $item->quantity, '2', 2);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => $halfQty],
            ],
        ]),
    );

    $invoice->refresh();
    $firstCredited = $invoice->credited_total;

    expect($firstCredited)->toBeLessThan($total)
        ->and($invoice->status)->toBe('issued');

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => $halfQty],
            ],
        ]),
    );

    $invoice->refresh();

    expect($invoice->credited_total)->toBe($total)
        ->and($invoice->status)->toBe('credited')
        ->and($invoice->amount_outstanding)->toBe(-$invoice->amount_paid);
});

it('forbids users without invoices.credit permission', function () {
    ['invoice' => $invoice] = issuedCreditNoteFixture();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('invoices.view');

    $this->actingAs($viewer);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );
})->throws(AuthorizationException::class);
