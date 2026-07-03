<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Enums\MembershipType;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use App\Support\Timezone;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Receipt documents render against an invoice payment row.
 */
class ReceiptDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentRenderContext $renderContext,
    ) {}

    public function type(): string
    {
        return 'receipt';
    }

    public function label(): string
    {
        return 'Payment Receipt';
    }

    public function description(): string
    {
        return 'Receipt for a payment recorded against an invoice.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('payment.reference'))->label('Payment Reference')->group('Payment');
        $this->documentField($builder->date('payment.received_at'))->label('Received Date')->group('Payment');
        $this->documentField($builder->currency('payment.amount'))->label('Payment Amount')->group('Payment');
        $this->documentField($builder->string('payment.method_label'))->label('Payment Method')->group('Payment');
        $this->documentField($builder->string('payment.notes'))->label('Notes')->group('Payment');

        $this->documentField($builder->string('invoice.number'))->label('Invoice Number')->group('Invoice');
        $this->documentField($builder->date('invoice.issued_at'))->label('Invoice Date')->group('Invoice');
        $this->documentField($builder->currency('invoice.total'))->label('Invoice Total')->group('Invoice');
        $this->documentField($builder->currency('invoice.amount_paid'))->label('Amount Paid')->group('Invoice');
        $this->documentField($builder->currency('invoice.amount_outstanding'))->label('Outstanding Balance')->group('Invoice');
        $this->documentField($builder->string('invoice.status_label'))->label('Invoice Status')->group('Invoice');

        $this->documentField($builder->string('member.name'))->label('Name')->group('Customer');
        $this->documentField($builder->string('member.company_name'))->label('Company Name')->group('Customer');
        $this->documentField($builder->string('member.account_number'))->label('Account Number')->group('Customer');
        $this->documentField($builder->string('member.email'))->label('Email')->group('Customer');
        $this->documentField($builder->string('member.phone'))->label('Phone')->group('Customer');

        $this->documentField($builder->string('store.name'))->label('Store Name')->group('Store');
        $this->documentField($builder->string('store.address'))->label('Address')->group('Store');
        $this->documentField($builder->string('store.phone'))->label('Phone')->group('Store');
        $this->documentField($builder->string('store.email'))->label('Email')->group('Store');
    }

    private function documentField(FieldBuilder $field): FieldBuilder
    {
        return $field
            ->source('document')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
    }

    public function loadSource(int $sourceId): Model
    {
        return InvoicePayment::query()
            ->with([
                'invoice.member.emails' => fn ($query) => $query->where('is_primary', true),
                'invoice.member.phones' => fn ($query) => $query->where('is_primary', true),
                'invoice.store',
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof InvoicePayment) {
            throw new \InvalidArgumentException('ReceiptDataResolver expects an InvoicePayment model.');
        }

        /** @var InvoicePayment $payment */
        $payment = $source;
        $invoice = $payment->invoice;
        $member = $invoice->member;
        $store = $this->resolveContextStore($invoice->store);
        $currencyCode = $invoice->currency_code ?: (string) settings('company.base_currency', 'GBP');
        $timezone = $store?->timezone ?: 'UTC';

        return [
            'payment' => [
                'reference' => $payment->reference,
                'received_at' => $this->formatDate($payment->paid_at, $timezone),
                'amount' => $this->formatMoney($payment->amount, $currencyCode),
                'method_label' => $payment->paymentMethodEnum()->label(),
                'notes' => $payment->notes,
            ],
            'invoice' => [
                'number' => $invoice->number,
                'issued_at' => $this->formatDate($invoice->issued_at, $timezone),
                'total' => $this->formatMoney($invoice->total, $currencyCode),
                'amount_paid' => $this->formatMoney($invoice->amount_paid, $currencyCode),
                'amount_outstanding' => $this->formatMoney($invoice->amount_outstanding, $currencyCode),
                'status_label' => config("invoices.statuses.{$invoice->status}.label", $invoice->status),
            ],
            'member' => $this->resolveMember($member),
            'store' => $this->resolveStore($store),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMember(Member $member): array
    {
        return [
            'name' => $member->name,
            'company_name' => $member->membership_type === MembershipType::Organisation ? $member->name : null,
            'account_number' => $member->account_number,
            'email' => $member->emails->first()?->address,
            'phone' => $member->phones->first()?->number,
        ];
    }

    private function resolveContextStore(?Store $invoiceStore): ?Store
    {
        if ($this->renderContext->storeId !== null) {
            return Store::query()->find($this->renderContext->storeId) ?? $invoiceStore;
        }

        return $invoiceStore;
    }

    private function formatDate(mixed $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = settings('company.date_format_php') ?? 'd/m/Y';

        return $this->timezone->toZone($value, $timezone)->format($format);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStore(?Store $store): array
    {
        if ($store === null) {
            return [
                'name' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
            ];
        }

        return [
            'name' => $store->name,
            'address' => $this->formatStoreAddress($store),
            'phone' => $store->phone,
            'email' => $store->email,
        ];
    }

    private function formatStoreAddress(Store $store): ?string
    {
        $parts = array_values(array_filter([
            $store->street,
            $store->city,
            $store->county,
            $store->postcode,
        ], fn (?string $part): bool => is_string($part) && $part !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function formatMoney(int $minorUnits, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minorUnits, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
