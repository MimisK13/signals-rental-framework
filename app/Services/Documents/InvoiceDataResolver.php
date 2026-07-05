<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Enums\InvoiceType;
use App\Enums\MembershipType;
use App\Enums\TaxResultType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use App\Support\Timezone;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Issued invoice and pro-forma/deposit invoice documents render against Invoice rows.
 */
class InvoiceDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentRenderContext $renderContext,
    ) {}

    public function type(): string
    {
        return 'invoice';
    }

    public function label(): string
    {
        return 'Invoice';
    }

    public function description(): string
    {
        return 'Issued invoice with line items, grouped tax summary, payments, and balance.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('invoice.number'))->label('Invoice Number')->group('Invoice');
        $this->documentField($builder->string('invoice.type_label'))->label('Type')->group('Invoice');
        $this->documentField($builder->string('invoice.status_label'))->label('Status')->group('Invoice');
        $this->documentField($builder->date('invoice.issued_at'))->label('Issued Date')->group('Invoice');
        $this->documentField($builder->date('invoice.due_at'))->label('Due Date')->group('Invoice');
        $this->documentField($builder->string('invoice.reference'))->label('Reference')->group('Invoice');
        $this->documentField($builder->text('invoice.notes'))->label('Notes')->group('Invoice');
        $this->documentField($builder->currency('invoice.subtotal'))->label('Subtotal')->group('Invoice');
        $this->documentField($builder->currency('invoice.tax_total'))->label('Tax Total')->group('Invoice');
        $this->documentField($builder->currency('invoice.total'))->label('Total')->group('Invoice');
        $this->documentField($builder->currency('invoice.amount_paid'))->label('Amount Paid')->group('Invoice');
        $this->documentField($builder->currency('invoice.amount_outstanding'))->label('Outstanding Balance')->group('Invoice');
        $this->documentField($builder->currency('invoice.credited_total'))->label('Credited Total')->group('Invoice');

        $this->documentField($builder->string('opportunity.number'))->label('Opportunity Number')->group('Opportunity');
        $this->documentField($builder->string('opportunity.subject'))->label('Opportunity Subject')->group('Opportunity');

        $this->documentField($builder->collection('lines'))->label('Line Items (loop)')->group('Line Items');
        $this->documentField($builder->string('line.description'))->label('Description')->group('Line Items');
        $this->documentField($builder->decimal('line.quantity'))->label('Quantity')->group('Line Items');
        $this->documentField($builder->currency('line.unit_price'))->label('Unit Price')->group('Line Items');
        $this->documentField($builder->currency('line.discount'))->label('Discount')->group('Line Items');
        $this->documentField($builder->currency('line.tax'))->label('Line Tax')->group('Line Items');
        $this->documentField($builder->currency('line.total'))->label('Line Total')->group('Line Items');
        $this->documentField($builder->string('line.tax_treatment_label'))->label('Tax Treatment')->group('Line Items');

        $this->documentField($builder->currency('tax_summary.total_net'))->label('Total Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.total_tax'))->label('Total Tax')->group('Tax Summary');
        $this->documentField($builder->collection('tax_summary.groups'))->label('Tax Groups (loop)')->group('Tax Summary');
        $this->documentField($builder->enum('tax_summary.group.result_type'))->label('Result Type')->group('Tax Summary');
        $this->documentField($builder->text('tax_summary.group.legal_text'))->label('Legal Text')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.net_total'))->label('Group Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.tax_total'))->label('Group Tax')->group('Tax Summary');
        $this->documentField($builder->decimal('tax_summary.group.rate_percentage'))->label('Rate %')->group('Tax Summary');
        $this->documentField($builder->string('tax_summary.group.tax_rate_name'))->label('Tax Rate Name')->group('Tax Summary');

        $this->documentField($builder->collection('payments'))->label('Payments (loop)')->group('Payments');
        $this->documentField($builder->date('payment.received_at'))->label('Received Date')->group('Payments');
        $this->documentField($builder->currency('payment.amount'))->label('Amount')->group('Payments');
        $this->documentField($builder->string('payment.method_label'))->label('Method')->group('Payments');
        $this->documentField($builder->string('payment.reference'))->label('Reference')->group('Payments');

        $this->documentField($builder->string('member.name'))->label('Name')->group('Customer');
        $this->documentField($builder->string('member.company_name'))->label('Company Name')->group('Customer');
        $this->documentField($builder->string('member.account_number'))->label('Account Number')->group('Customer');
        $this->documentField($builder->string('member.email'))->label('Email')->group('Customer');
        $this->documentField($builder->string('member.phone'))->label('Phone')->group('Customer');
        $this->documentField($builder->string('member.vat_number'))->label('VAT Number')->group('Customer');

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
        return Invoice::query()
            ->with([
                'member.emails' => fn ($query) => $query->where('is_primary', true),
                'member.phones' => fn ($query) => $query->where('is_primary', true),
                'store',
                'opportunity',
                'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'taxes' => fn ($query) => $query->orderBy('id'),
                'taxes.taxRate',
                'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof Invoice) {
            throw new \InvalidArgumentException('InvoiceDataResolver expects an Invoice model.');
        }

        /** @var Invoice $invoice */
        $invoice = $source;
        $member = $invoice->member;
        $store = $this->resolveContextStore($invoice->store);
        $currencyCode = $invoice->currency_code ?: (string) settings('company.base_currency', 'GBP');
        $timezone = $store?->timezone ?: 'UTC';

        return [
            'invoice' => $this->resolveInvoice($invoice, $currencyCode, $timezone),
            'opportunity' => $this->resolveOpportunity($invoice),
            'lines' => $this->resolveLines($invoice, $currencyCode),
            'tax_summary' => $this->resolveTaxSummary($invoice, $currencyCode),
            'payments' => $this->resolvePayments($invoice, $currencyCode, $timezone),
            'member' => $this->resolveMember($member),
            'store' => $this->resolveStore($store),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveInvoice(Invoice $invoice, string $currencyCode, string $timezone): array
    {
        return [
            'number' => $invoice->number,
            'type_label' => $invoice->typeEnum()->label(),
            'status_label' => config("invoices.statuses.{$invoice->effectiveStatus()}.label", $invoice->effectiveStatus()),
            'issued_at' => $this->formatDate($invoice->issued_at, $timezone),
            'due_at' => $this->formatDate($invoice->due_at, $timezone),
            'reference' => $invoice->reference,
            'notes' => $invoice->notes,
            'subtotal' => $this->formatMoney($invoice->subtotal, $currencyCode),
            'tax_total' => $this->formatMoney($invoice->tax_total, $currencyCode),
            'total' => $this->formatMoney($invoice->total, $currencyCode),
            'amount_paid' => $this->formatMoney($invoice->amount_paid, $currencyCode),
            'amount_outstanding' => $this->formatMoney($invoice->amount_outstanding, $currencyCode),
            'credited_total' => $this->formatMoney($invoice->credited_total, $currencyCode),
            'is_deposit' => $invoice->typeEnum() === InvoiceType::Deposit,
            'is_proforma' => $invoice->typeEnum() === InvoiceType::Proforma,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveOpportunity(Invoice $invoice): array
    {
        $opportunity = $invoice->opportunity;

        if ($opportunity === null) {
            return [
                'number' => null,
                'subject' => null,
            ];
        }

        return [
            'number' => $opportunity->number,
            'subject' => $opportunity->subject,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveLines(Invoice $invoice, string $currencyCode): array
    {
        return $invoice->items
            ->map(fn (InvoiceItem $line): array => [
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => $this->formatMoney($line->unit_price, $currencyCode),
                'discount' => $this->formatMoney($line->discount, $currencyCode),
                'tax' => $this->formatMoney($line->tax, $currencyCode),
                'total' => $this->formatMoney($line->total, $currencyCode),
                'tax_treatment_label' => $line->taxTreatmentEnum()->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTaxSummary(Invoice $invoice, string $currencyCode): array
    {
        $groups = $invoice->taxes
            ->map(fn (InvoiceTax $row): array => $this->resolveTaxGroup($row, $currencyCode))
            ->values()
            ->all();

        return [
            'total_net' => $this->formatMoney($invoice->subtotal, $currencyCode),
            'total_tax' => $this->formatMoney($invoice->tax_total, $currencyCode),
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTaxGroup(InvoiceTax $row, string $currencyCode): array
    {
        $resultType = $row->result_type !== null
            ? TaxResultType::tryFrom($row->result_type)
            : null;

        $rateName = $row->tax_rate_name ?? $row->taxRate?->name;

        return [
            'tax_rate_name' => $rateName,
            'rate_percentage' => $row->rate_percentage ?? $row->taxRate?->rate,
            'net_total' => $this->formatMoney($row->taxable_amount, $currencyCode),
            'tax_total' => $this->formatMoney($row->tax_amount, $currencyCode),
            'result_type' => $resultType?->label(),
            'legal_text' => $row->legal_text,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePayments(Invoice $invoice, string $currencyCode, string $timezone): array
    {
        return $invoice->payments
            ->map(fn (InvoicePayment $payment): array => [
                'received_at' => $this->formatDate($payment->paid_at, $timezone),
                'amount' => $this->formatMoney($payment->amount, $currencyCode),
                'method_label' => $payment->paymentMethodEnum()->label(),
                'reference' => $payment->reference,
            ])
            ->values()
            ->all();
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
            'vat_number' => $member->tax_number,
        ];
    }

    private function resolveContextStore(?Store $invoiceStore): ?Store
    {
        if ($this->renderContext->storeId !== null) {
            return Store::query()->find($this->renderContext->storeId) ?? $invoiceStore;
        }

        return $invoiceStore;
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

    private function formatDate(mixed $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = settings('company.date_format_php') ?? 'd/m/Y';

        return $this->timezone->toZone($value, $timezone)->format($format);
    }

    private function formatMoney(int $minorUnits, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minorUnits, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
