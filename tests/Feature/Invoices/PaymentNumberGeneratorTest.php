<?php

use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Services\DocumentSequenceDefaults;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Actions\RecordPayment;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Invoices\Services\PaymentNumberGenerator;
use App\Domain\Invoices\Verbs\Events\PaymentRecorded;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

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

describe('PaymentNumberGenerator', function () {
    it('formats numbers from document sequences with prefix and padding', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => PaymentNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'PAY-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 7,
            'is_entity_number' => true,
        ]);

        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');
        $generator = app(PaymentNumberGenerator::class);

        expect($generator->next($store->id, $at))->toBe('PAY-2026-0007')
            ->and($generator->next($store->id, $at))->toBe('PAY-2026-0008');
    });

    it('creates a default sequence on first use with entity flag and PAY- prefix', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $generator = app(PaymentNumberGenerator::class);

        expect($generator->nextOrCreate($store->id))
            ->toBe('PAY-'.now()->format('Y').'-0001');

        $sequence = DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', PaymentNumberGenerator::DOCUMENT_TYPE)
            ->first();

        expect($sequence)->not->toBeNull()
            ->and($sequence->prefix)->toBe('PAY-')
            ->and($sequence->is_entity_number)->toBeTrue();
    });

    it('increments sequentially under repeated allocation', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => PaymentNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'PAY-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 1,
            'is_entity_number' => true,
        ]);

        $generator = app(PaymentNumberGenerator::class);

        expect($generator->next($store->id))->toBe('PAY-001')
            ->and($generator->next($store->id))->toBe('PAY-002')
            ->and($generator->next($store->id))->toBe('PAY-003');
    });
});

it('provisions invoice payment sequences with is_entity_number via DocumentSequenceDefaults', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);

    $sequence = DocumentSequenceDefaults::ensureExists($store->id, PaymentNumberGenerator::DOCUMENT_TYPE);

    expect($sequence->prefix)->toBe('PAY-')
        ->and($sequence->is_entity_number)->toBeTrue();
});

it('assigns PAY- numbers when recording payments', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => intdiv($invoice->total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();

    expect($payment->number)->toStartWith('PAY-');
});

it('reconstructs payment numbers identically after replay', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $invoice->refresh();
    $total = $invoice->total;
    $partial = intdiv($total, 2);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $partial,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'RPL-001',
        ]),
    );

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $total - $partial,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    );

    $paymentsBefore = InvoicePayment::query()->orderBy('id')->get()->map(
        fn (InvoicePayment $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    InvoicePayment::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $paymentsAfter = InvoicePayment::query()->orderBy('id')->get()->map(
        fn (InvoicePayment $row): array => $row->getAttributes(),
    )->all();

    expect($paymentsAfter)->toBe($paymentsBefore)
        ->and(collect($paymentsBefore)->pluck('number')->filter()->count())->toBe(2);
});

it('replays legacy PaymentRecorded events without number and leaves projection null', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => intdiv($invoice->total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();

    VerbEvent::query()
        ->where('type', PaymentRecorded::class)
        ->get()
        ->each(function (VerbEvent $event): void {
            $data = json_decode((string) $event->getRawOriginal('data'), true);

            expect($data)->toBeArray()->toHaveKey('number');
            unset($data['number']);

            $event->newQuery()->whereKey($event->getKey())->update([
                'data' => json_encode($data),
            ]);
        });

    $paymentId = $payment->id;

    InvoicePayment::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    $replayed = InvoicePayment::query()->findOrFail($paymentId);

    expect($replayed->number)->toBeNull()
        ->and($replayed->amount)->toBe($payment->amount)
        ->and($replayed->invoice_id)->toBe($payment->invoice_id);
});
