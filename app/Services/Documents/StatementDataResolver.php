<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Enums\MembershipType;
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
 * Statement documents render against a member (organisation or contact).
 *
 * Outstanding invoice lines are populated from issued invoice projections (P38).
 */
class StatementDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentRenderContext $renderContext,
    ) {}

    public function type(): string
    {
        return 'statement';
    }

    public function label(): string
    {
        return 'Member Statement';
    }

    public function description(): string
    {
        return 'Customer account summary with outstanding invoice and payment lines.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('member.name'))->label('Name')->group('Customer');
        $this->documentField($builder->string('member.company_name'))->label('Company Name')->group('Customer');
        $this->documentField($builder->string('member.account_number'))->label('Account Number')->group('Customer');
        $this->documentField($builder->string('member.email'))->label('Email')->group('Customer');
        $this->documentField($builder->string('member.phone'))->label('Phone')->group('Customer');
        $this->documentField($builder->string('member.vat_number'))->label('VAT Number')->group('Customer');
        $this->documentField($builder->string('statement.period_start'))->label('Period Start')->group('Statement');
        $this->documentField($builder->string('statement.period_end'))->label('Period End')->group('Statement');
        $this->documentField($builder->currency('statement.opening_balance'))->label('Opening Balance')->group('Statement');
        $this->documentField($builder->currency('statement.closing_balance'))->label('Closing Balance')->group('Statement');
        $this->documentField($builder->collection('invoices'))->label('Invoices (loop)')->group('Invoices');
        $this->documentField($builder->string('invoice.number'))->label('Invoice Number')->group('Invoices');
        $this->documentField($builder->date('invoice.issued_at'))->label('Issued Date')->group('Invoices');
        $this->documentField($builder->currency('invoice.total'))->label('Invoice Total')->group('Invoices');
        $this->documentField($builder->currency('invoice.balance'))->label('Outstanding Balance')->group('Invoices');
        $this->documentField($builder->collection('payments'))->label('Payments (loop)')->group('Payments');
        $this->documentField($builder->string('payment.reference'))->label('Payment Reference')->group('Payments');
        $this->documentField($builder->date('payment.received_at'))->label('Received Date')->group('Payments');
        $this->documentField($builder->currency('payment.amount'))->label('Payment Amount')->group('Payments');

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
        return Member::query()
            ->with([
                'emails' => fn ($query) => $query->where('is_primary', true),
                'phones' => fn ($query) => $query->where('is_primary', true),
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof Member) {
            throw new \InvalidArgumentException('StatementDataResolver expects a Member model.');
        }

        /** @var Member $member */
        $member = $source;
        $store = $this->resolveContextStore();
        $currencyCode = $member->default_currency_code
            ?: (string) settings('company.base_currency', 'GBP');
        $timezone = $store?->timezone ?: 'UTC';
        $periodEnd = now($timezone);
        $periodStart = $periodEnd->copy()->subMonth();
        $invoices = $this->resolveOutstandingInvoices($member, $store?->id);
        $closingBalance = array_reduce(
            $invoices,
            static fn (int $carry, array $row): int => $carry + $row['balance_minor'],
            0,
        );

        return [
            'member' => $this->resolveMember($member),
            'store' => $this->resolveStore($store),
            'statement' => [
                'period_start' => $this->formatDate($periodStart, $timezone),
                'period_end' => $this->formatDate($periodEnd, $timezone),
                'opening_balance' => $this->formatMoney($closingBalance, $currencyCode),
                'closing_balance' => $this->formatMoney($closingBalance, $currencyCode),
            ],
            'invoices' => array_map(
                fn (array $row): array => [
                    'number' => $row['number'],
                    'issued_at' => $row['issued_at'],
                    'total' => $row['total'],
                    'balance' => $row['balance'],
                ],
                $invoices,
            ),
            'payments' => [],
        ];
    }

    /**
     * @return list<array{
     *     number: string,
     *     issued_at: string|null,
     *     total: string,
     *     balance: string,
     *     balance_minor: int
     * }>
     */
    private function resolveOutstandingInvoices(Member $member, ?int $storeId): array
    {
        $query = Invoice::query()
            ->with('store')
            ->where('member_id', $member->id)
            ->where('amount_outstanding', '>', 0)
            ->whereNotIn('status', ['draft', 'void', 'credited'])
            ->orderBy('issued_at');

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->get()->map(function (Invoice $invoice) use ($member): array {
            $currencyCode = $invoice->currency_code !== ''
                ? $invoice->currency_code
                : ($member->default_currency_code ?: (string) settings('company.base_currency', 'GBP'));
            $timezone = $invoice->store?->timezone ?: 'UTC';

            return [
                'number' => $invoice->number,
                'issued_at' => $this->formatDate($invoice->issued_at, $timezone),
                'total' => $this->formatMoney($invoice->total, $currencyCode),
                'balance' => $this->formatMoney($invoice->amount_outstanding, $currencyCode),
                'balance_minor' => $invoice->amount_outstanding,
            ];
        })->all();
    }

    private function formatMoney(int $minorUnits, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minorUnits, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
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

    private function resolveContextStore(): ?Store
    {
        if ($this->renderContext->storeId === null) {
            return null;
        }

        return Store::query()->find($this->renderContext->storeId);
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
}
