<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Enums\MembershipType;
use App\Enums\OpportunityItemType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\Pricing\QuantityMinorArithmetic;
use App\Services\SchemaBuilder;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxPointDateResolver;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Support\Timezone;
use App\ValueObjects\Tax\DocumentTaxGroup;
use App\ValueObjects\Tax\DocumentTaxSummary;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Resolves opportunity records into quote/order document merge data.
 */
class OpportunityDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentTaxBreakdown $documentTaxBreakdown,
        private readonly TaxResolutionContextHolder $taxResolutionContextHolder,
        private readonly TaxPointDateResolver $taxPointDateResolver,
        private readonly QuantityMinorArithmetic $quantityMinor,
    ) {}

    public function type(): string
    {
        return 'opportunity';
    }

    public function label(): string
    {
        return 'Opportunity';
    }

    public function description(): string
    {
        return 'Quotes and orders with customer, store, line items, costs, and tax summary.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('opportunity.number'))->label('Number')->group('Opportunity');
        $this->documentField($builder->string('opportunity.subject'))->label('Subject')->group('Opportunity');
        $this->documentField($builder->string('opportunity.reference'))->label('Reference')->group('Opportunity');
        $this->documentField($builder->string('opportunity.state_label'))->label('State')->group('Opportunity');
        $this->documentField($builder->string('opportunity.status_label'))->label('Status')->group('Opportunity');
        $this->documentField($builder->date('opportunity.starts_at'))->label('Start Date')->group('Opportunity');
        $this->documentField($builder->date('opportunity.ends_at'))->label('End Date')->group('Opportunity');
        $this->documentField($builder->string('opportunity.description'))->label('Description')->group('Opportunity');
        $this->documentField($builder->decimal('opportunity.rental_days'))->label('Rental Days')->group('Opportunity');
        $this->documentField($builder->currency('opportunity.charge_total'))->label('Charge Total')->group('Opportunity');
        $this->documentField($builder->currency('opportunity.tax_total'))->label('Tax Total')->group('Opportunity');
        $this->documentField($builder->currency('opportunity.grand_total'))->label('Grand Total')->group('Opportunity');

        $this->documentField($builder->string('member.name'))->label('Contact Name')->group('Customer');
        $this->documentField($builder->string('member.company_name'))->label('Company Name')->group('Customer');
        $this->documentField($builder->string('member.email'))->label('Email')->group('Customer');
        $this->documentField($builder->string('member.phone'))->label('Phone')->group('Customer');
        $this->documentField($builder->string('member.vat_number'))->label('VAT Number')->group('Customer');

        $this->documentField($builder->string('store.name'))->label('Store Name')->group('Store');
        $this->documentField($builder->string('store.address'))->label('Address')->group('Store');
        $this->documentField($builder->string('store.phone'))->label('Phone')->group('Store');
        $this->documentField($builder->string('store.email'))->label('Email')->group('Store');

        $this->documentField($builder->collection('items'))->label('Line Items (loop)')->group('Line Items');
        $this->documentField($builder->string('item.name'))->label('Line Name')->group('Line Items');
        $this->documentField($builder->string('item.product_name'))->label('Product Name')->group('Line Items');
        $this->documentField($builder->decimal('item.quantity'))->label('Quantity')->group('Line Items');
        $this->documentField($builder->currency('item.unit_price'))->label('Unit Price')->group('Line Items');
        $this->documentField($builder->currency('item.discount'))->label('Discount')->group('Line Items');
        $this->documentField($builder->currency('item.total'))->label('Line Total')->group('Line Items');
        $this->documentField($builder->string('item.description'))->label('Description')->group('Line Items');
        $this->documentField($builder->string('item.transaction_type_label'))->label('Transaction Type')->group('Line Items');

        $this->documentField($builder->collection('costs'))->label('Costs (loop)')->group('Costs');
        $this->documentField($builder->string('cost.description'))->label('Description')->group('Costs');
        $this->documentField($builder->currency('cost.amount'))->label('Amount')->group('Costs');
        $this->documentField($builder->string('cost.cost_type_label'))->label('Cost Type')->group('Costs');

        $this->documentField($builder->currency('tax_summary.total_net'))->label('Total Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.total_tax'))->label('Total Tax')->group('Tax Summary');
        $this->documentField($builder->collection('tax_summary.groups'))->label('Tax Groups (loop)')->group('Tax Summary');
        $this->documentField($builder->enum('tax_summary.group.result_type'))->label('Result Type')->group('Tax Summary');
        $this->documentField($builder->text('tax_summary.group.legal_text'))->label('Legal Text')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.net_total'))->label('Group Net')->group('Tax Summary');
        $this->documentField($builder->currency('tax_summary.group.tax_total'))->label('Group Tax')->group('Tax Summary');
        $this->documentField($builder->decimal('tax_summary.group.rate_percentage'))->label('Rate %')->group('Tax Summary');
        $this->documentField($builder->string('tax_summary.group.tax_rate_name'))->label('Tax Rate Name')->group('Tax Summary');
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
        return Opportunity::query()
            ->with([
                'member.emails' => fn ($query) => $query->where('is_primary', true),
                'member.phones' => fn ($query) => $query->where('is_primary', true),
                'store',
                'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
                'items.item' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Product::class,
                ]),
                'items.itemSurcharges',
                'costs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof Opportunity) {
            throw new \InvalidArgumentException('OpportunityDataResolver expects an Opportunity model.');
        }

        /** @var Opportunity $opportunity */
        $opportunity = $source;
        $store = $opportunity->store;
        $member = $opportunity->member;
        $currencyCode = $this->currencyFor($opportunity);
        $timezone = $store?->timezone ?: 'UTC';

        return [
            'opportunity' => $this->resolveOpportunity($opportunity, $currencyCode, $timezone),
            'member' => $this->resolveMember($member),
            'store' => $this->resolveStore($store),
            'items' => $this->resolveItems($opportunity, $currencyCode),
            'costs' => $this->resolveCosts($opportunity, $currencyCode),
            'tax_summary' => $this->resolveTaxSummary($opportunity, $currencyCode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOpportunity(Opportunity $opportunity, string $currencyCode, string $timezone): array
    {
        return [
            'number' => $opportunity->number,
            'subject' => $opportunity->subject,
            'reference' => $opportunity->reference,
            'state_label' => $opportunity->state->label(),
            'status_label' => $opportunity->statusEnum()->label(),
            'starts_at' => $this->formatDate($opportunity->starts_at, $timezone),
            'ends_at' => $this->formatDate($opportunity->ends_at, $timezone),
            'description' => $opportunity->description,
            'rental_days' => $this->rentalDays($opportunity),
            'charge_total' => $opportunity->formatMoneyCost('charge_total'),
            'tax_total' => $opportunity->formatMoneyCost('tax_total'),
            'grand_total' => $this->formatMinor(
                (int) $opportunity->charge_total + (int) $opportunity->tax_total,
                $currencyCode,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMember(?Member $member): array
    {
        if ($member === null) {
            return [
                'name' => null,
                'company_name' => null,
                'email' => null,
                'phone' => null,
                'vat_number' => null,
            ];
        }

        return [
            'name' => $member->name,
            'company_name' => $member->membership_type === MembershipType::Organisation ? $member->name : null,
            'email' => $member->emails->first()?->address,
            'phone' => $member->phones->first()?->number,
            'vat_number' => $member->tax_number,
        ];
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

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveItems(Opportunity $opportunity, string $currencyCode): array
    {
        return $opportunity->items
            ->filter(fn (OpportunityItem $item): bool => ! $item->is_optional
                && $item->item_type !== OpportunityItemType::Group
                && $item->item_type !== OpportunityItemType::Text)
            ->map(function (OpportunityItem $item) use ($currencyCode): array {
                $product = $item->item instanceof Product ? $item->item : null;
                $grossMinor = $this->quantityMinor->multiplyMinorByQuantity(
                    (int) $item->unit_price,
                    (string) $item->quantity,
                    $currencyCode,
                );
                $discountMinor = max(0, $grossMinor - (int) $item->total);

                return [
                    'name' => $item->name,
                    'product_name' => $product?->name ?? $item->name,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => $item->formatMoneyCost('unit_price'),
                    'discount' => $this->formatMinor($discountMinor, $currencyCode),
                    'total' => $item->formatMoneyCost('total'),
                    'description' => $item->description,
                    'transaction_type_label' => $item->transaction_type->label(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveCosts(Opportunity $opportunity, string $currencyCode): array
    {
        return $opportunity->costs
            ->filter(fn (OpportunityCost $cost): bool => ! $cost->is_optional)
            ->map(fn (OpportunityCost $cost): array => [
                'description' => $cost->description,
                'amount' => $cost->formatMoneyCost('amount'),
                'cost_type_label' => $cost->cost_type->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTaxSummary(Opportunity $opportunity, string $currencyCode): array
    {
        if ($opportunity->tax_locked) {
            return [
                'total_net' => $opportunity->formatMoneyCost('charge_total'),
                'total_tax' => $opportunity->formatMoneyCost('tax_total'),
                'groups' => [],
            ];
        }

        $summary = $this->summarizeTax($opportunity, $currencyCode);

        return [
            'total_net' => $this->formatMinor($summary->totalNet, $currencyCode),
            'total_tax' => $this->formatMinor($summary->totalTax, $currencyCode),
            'groups' => array_map(
                fn (DocumentTaxGroup $group): array => $this->formatTaxGroup($group, $currencyCode),
                $summary->groups,
            ),
        ];
    }

    private function summarizeTax(Opportunity $opportunity, string $currencyCode): DocumentTaxSummary
    {
        $defaultProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $taxLines = $this->buildTaxLines($opportunity, $defaultProductTaxClassId);
        $taxContext = TaxResolutionContext::fromOpportunity($opportunity);
        $taxPointDate = $this->taxPointDateResolver->resolveForOpportunity($opportunity);

        return $this->taxResolutionContextHolder->using(
            $taxContext,
            fn (): DocumentTaxSummary => $this->documentTaxBreakdown->summarize(new DocumentTaxRequest(
                lines: $taxLines,
                currencyCode: $currencyCode,
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $taxPointDate,
                pricesIncludeTax: false,
            )),
        );
    }

    /**
     * @return list<DocumentTaxLineInput>
     */
    private function buildTaxLines(Opportunity $opportunity, ?int $defaultProductTaxClassId): array
    {
        $taxLines = [];

        foreach ($opportunity->items as $item) {
            if ($item->is_optional) {
                continue;
            }

            $product = $item->item instanceof Product ? $item->item : null;

            $taxLines[] = new DocumentTaxLineInput(
                lineKey: 'item:'.$item->id,
                netMinor: (int) $item->total,
                productTaxClassId: $product?->tax_class_id,
                quantity: bccomp((string) $item->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $item->quantity, '0', 2),
            );

            foreach ($item->itemSurcharges as $itemSurcharge) {
                if ($itemSurcharge->is_disabled) {
                    continue;
                }

                $taxLines[] = new DocumentTaxLineInput(
                    lineKey: 'item:'.$item->id.':surcharge:'.$itemSurcharge->surcharge_id,
                    netMinor: (int) $itemSurcharge->amount,
                    productTaxClassId: $itemSurcharge->product_tax_class_id,
                    quantity: bccomp((string) $item->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $item->quantity, '0', 2),
                );
            }
        }

        foreach ($opportunity->costs as $cost) {
            if ($cost->is_optional) {
                continue;
            }

            $taxLines[] = new DocumentTaxLineInput(
                lineKey: 'cost:'.$cost->id,
                netMinor: (int) $cost->amount,
                productTaxClassId: $defaultProductTaxClassId,
                quantity: bccomp((string) $cost->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $cost->quantity, '0', 2),
            );
        }

        return $taxLines;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTaxGroup(DocumentTaxGroup $group, string $currencyCode): array
    {
        return [
            'result_type' => $group->resultType?->label(),
            'legal_text' => $group->legalText,
            'net_total' => $this->formatMinor($group->netTotal, $currencyCode),
            'tax_total' => $this->formatMinor($group->taxTotal, $currencyCode),
            'rate_percentage' => $group->ratePercentage,
            'tax_rate_name' => $group->taxRateName,
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

    private function rentalDays(Opportunity $opportunity): ?string
    {
        if ($opportunity->chargeable_days !== null) {
            return (string) $opportunity->chargeable_days;
        }

        if ($opportunity->starts_at === null || $opportunity->ends_at === null) {
            return null;
        }

        $days = $opportunity->starts_at->diffInDays($opportunity->ends_at) + 1;

        return (string) max(1, $days);
    }

    private function currencyFor(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        return is_string($code) && $code !== '' ? $code : (string) settings('company.base_currency', 'GBP');
    }

    private function formatMinor(int $minor, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minor, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
