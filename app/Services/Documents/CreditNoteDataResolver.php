<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Enums\MembershipType;
use App\Enums\TaxResultType;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteTax;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use App\Support\Timezone;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Credit note documents render against CreditNote rows with negated mirror amounts.
 */
class CreditNoteDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentRenderContext $renderContext,
    ) {}

    public function type(): string
    {
        return 'credit_note';
    }

    public function label(): string
    {
        return 'Credit Note';
    }

    public function description(): string
    {
        return 'Credit note with negated line mirror, grouped tax, and original invoice reference.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('credit_note.number'))->label('Credit Note Number')->group('Credit Note');
        $this->documentField($builder->date('credit_note.issued_at'))->label('Issued Date')->group('Credit Note');
        $this->documentField($builder->text('credit_note.notes'))->label('Notes')->group('Credit Note');
        $this->documentField($builder->currency('credit_note.subtotal'))->label('Subtotal')->group('Credit Note');
        $this->documentField($builder->currency('credit_note.tax_total'))->label('Tax Total')->group('Credit Note');
        $this->documentField($builder->currency('credit_note.total'))->label('Total Credit')->group('Credit Note');

        $this->documentField($builder->string('invoice.number'))->label('Original Invoice Number')->group('Original Invoice');
        $this->documentField($builder->date('invoice.issued_at'))->label('Original Invoice Date')->group('Original Invoice');
        $this->documentField($builder->currency('invoice.total'))->label('Original Invoice Total')->group('Original Invoice');

        $this->documentField($builder->collection('lines'))->label('Line Items (loop)')->group('Line Items');
        $this->documentField($builder->string('line.description'))->label('Description')->group('Line Items');
        $this->documentField($builder->decimal('line.quantity'))->label('Quantity')->group('Line Items');
        $this->documentField($builder->currency('line.unit_price'))->label('Unit Price')->group('Line Items');
        $this->documentField($builder->currency('line.discount'))->label('Discount')->group('Line Items');
        $this->documentField($builder->currency('line.tax'))->label('Line Tax')->group('Line Items');
        $this->documentField($builder->currency('line.total'))->label('Line Total')->group('Line Items');

        $this->documentField($builder->currency('tax_summary.total_net'))->label('Total Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.total_tax'))->label('Total Tax')->group('Tax Summary');
        $this->documentField($builder->collection('tax_summary.groups'))->label('Tax Groups (loop)')->group('Tax Summary');
        $this->documentField($builder->enum('tax_summary.group.result_type'))->label('Result Type')->group('Tax Summary');
        $this->documentField($builder->text('tax_summary.group.legal_text'))->label('Legal Text')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.net_total'))->label('Group Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.tax_total'))->label('Group Tax')->group('Tax Summary');
        $this->documentField($builder->decimal('tax_summary.group.rate_percentage'))->label('Rate %')->group('Tax Summary');
        $this->documentField($builder->string('tax_summary.group.tax_rate_name'))->label('Tax Rate Name')->group('Tax Summary');

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
        return CreditNote::query()
            ->with([
                'invoice.member.emails' => fn ($query) => $query->where('is_primary', true),
                'invoice.member.phones' => fn ($query) => $query->where('is_primary', true),
                'invoice.store',
                'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'taxes' => fn ($query) => $query->orderBy('id'),
                'taxes.invoiceTax.taxRate',
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof CreditNote) {
            throw new \InvalidArgumentException('CreditNoteDataResolver expects a CreditNote model.');
        }

        /** @var CreditNote $creditNote */
        $creditNote = $source;
        $invoice = $creditNote->invoice;
        $member = $invoice->member;
        $store = $this->resolveContextStore($invoice->store);
        $currencyCode = $creditNote->currency_code ?: (string) settings('company.base_currency', 'GBP');
        $timezone = $store?->timezone ?: 'UTC';

        return [
            'credit_note' => $this->resolveCreditNote($creditNote, $currencyCode, $timezone),
            'invoice' => $this->resolveOriginalInvoice($invoice, $currencyCode, $timezone),
            'lines' => $this->resolveLines($creditNote, $currencyCode),
            'tax_summary' => $this->resolveTaxSummary($creditNote, $currencyCode),
            'member' => $this->resolveMember($member),
            'store' => $this->resolveStore($store),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCreditNote(CreditNote $creditNote, string $currencyCode, string $timezone): array
    {
        return [
            'number' => $creditNote->number,
            'issued_at' => $this->formatDate($creditNote->issued_at, $timezone),
            'notes' => $creditNote->notes,
            'subtotal' => $this->formatCreditMoney($creditNote->subtotal, $currencyCode),
            'tax_total' => $this->formatCreditMoney($creditNote->tax_total, $currencyCode),
            'total' => $this->formatCreditMoney($creditNote->total, $currencyCode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOriginalInvoice(Invoice $invoice, string $currencyCode, string $timezone): array
    {
        return [
            'number' => $invoice->number,
            'issued_at' => $this->formatDate($invoice->issued_at, $timezone),
            'total' => $this->formatMoney($invoice->total, $currencyCode),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveLines(CreditNote $creditNote, string $currencyCode): array
    {
        return $creditNote->items
            ->map(fn (CreditNoteItem $line): array => [
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => $this->formatCreditMoney($line->unit_price, $currencyCode),
                'discount' => $this->formatCreditMoney($line->discount, $currencyCode),
                'tax' => $this->formatCreditMoney($line->tax, $currencyCode),
                'total' => $this->formatCreditMoney($line->total, $currencyCode),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTaxSummary(CreditNote $creditNote, string $currencyCode): array
    {
        $groups = $creditNote->taxes
            ->map(fn (CreditNoteTax $row): array => $this->resolveTaxGroup($row, $currencyCode))
            ->values()
            ->all();

        return [
            'total_net' => $this->formatCreditMoney($creditNote->subtotal, $currencyCode),
            'total_tax' => $this->formatCreditMoney($creditNote->tax_total, $currencyCode),
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTaxGroup(CreditNoteTax $row, string $currencyCode): array
    {
        $invoiceTax = $row->invoiceTax;
        $resultType = $invoiceTax !== null && $invoiceTax->result_type !== null
            ? TaxResultType::tryFrom($invoiceTax->result_type)
            : null;

        $rateName = $invoiceTax !== null
            ? ($invoiceTax->tax_rate_name ?? $invoiceTax->taxRate?->name)
            : null;

        return [
            'tax_rate_name' => $rateName,
            'rate_percentage' => $invoiceTax !== null
                ? ($invoiceTax->rate_percentage ?? $invoiceTax->taxRate?->rate)
                : null,
            'net_total' => $this->formatCreditMoney($row->taxable_amount, $currencyCode),
            'tax_total' => $this->formatCreditMoney($row->tax_amount, $currencyCode),
            'result_type' => $resultType?->label(),
            'legal_text' => $invoiceTax?->legal_text,
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

    /**
     * Credit note stored amounts are negative; documents display positive credits.
     */
    private function formatCreditMoney(int $minorUnits, string $currencyCode): string
    {
        return $this->formatMoney(abs($minorUnits), $currencyCode);
    }
}
