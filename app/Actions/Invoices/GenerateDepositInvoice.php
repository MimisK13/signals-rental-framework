<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Invoices\InvoiceData;
use App\Enums\InvoiceType;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Services\CurrencyService;
use App\Services\CustomFieldCopier;
use App\Services\Invoices\DepositInvoiceCalculator;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Invoices\InvoiceSourceStateRule;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Services\Invoices\InvoiceTotalsCalculator;
use App\Services\Invoices\OpportunityInvoiceSnapshotter;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\InvoiceGenerated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Generate a deposit invoice (amount-based partial) from an opportunity.
 */
class GenerateDepositInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(GenerateDepositInvoiceData $data): InvoiceData
    {
        Gate::authorize('invoices.create');

        $type = InvoiceType::Deposit;
        $opportunity = Opportunity::query()
            ->with(['member', 'store'])
            ->findOrFail($data->opportunity_id);

        app(InvoiceSourceStateRule::class)->assertAllowed($opportunity, $type);

        $generatedAt = CarbonImmutable::now('UTC');
        $taxPolicy = PartialInvoiceTaxPolicy::tryFrom(
            (string) settings('finance.partial_invoice_tax', PartialInvoiceTaxPolicy::Proportional->value),
        ) ?? PartialInvoiceTaxPolicy::Proportional;

        $invoiceId = $this->commitVerbs(function () use ($data, $type, $opportunity, $generatedAt, $taxPolicy): int {
            $existing = Invoice::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return (int) $existing->id;
            }

            $snapshot = app(OpportunityInvoiceSnapshotter::class)->snapshot($opportunity);

            $fullTotals = app(InvoiceTotalsCalculator::class)->calculate(
                $opportunity,
                $snapshot['lines'],
                $snapshot['tax_lines'],
                $snapshot['net_headline'],
                $generatedAt,
            );

            $depositNet = $this->resolveDepositNet($data, $opportunity, $fullTotals['subtotal'], $fullTotals['total']);

            $depositTotals = app(DepositInvoiceCalculator::class)->calculate(
                depositNet: $depositNet,
                fullSubtotal: $fullTotals['subtotal'],
                fullTaxRows: $fullTotals['tax_rows'],
                taxPolicy: $taxPolicy,
            );

            $invoiceId = app(SequenceAllocator::class)->next('invoices');
            $allocator = app(SequenceAllocator::class);

            $linePayloads = array_map(
                function (FrozenInvoiceLineData $line) use ($allocator): array {
                    $lineId = $allocator->next('invoice_items');

                    return $this->lineToPayload($line, $lineId);
                },
                $depositTotals['lines'],
            );

            $taxPayloads = array_map(
                function (FrozenInvoiceTaxRowData $row) use ($allocator): array {
                    $taxId = $allocator->next('invoice_taxes');

                    return $this->taxRowToPayload($row, $taxId);
                },
                $depositTotals['tax_rows'],
            );

            $status = app(InvoiceStatusRegistry::class)->defaultStatus();
            app(InvoiceStatusRegistry::class)->assertKnown($status);

            $currency = $this->resolveCurrency($opportunity);
            $number = app(InvoiceNumberGenerator::class)->nextOrCreate(
                (int) $opportunity->store_id,
                $type,
                $generatedAt,
            );

            InvoiceGenerated::fire(
                invoice_id: $invoiceId,
                number: $number,
                type: $type->value,
                status: $status,
                member_id: (int) $opportunity->member_id,
                store_id: (int) $opportunity->store_id,
                opportunity_id: (int) $opportunity->id,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency, $opportunity),
                prices_include_tax: (bool) $opportunity->prices_include_tax,
                subtotal: $depositTotals['subtotal'],
                tax_total: $depositTotals['tax_total'],
                total: $depositTotals['total'],
                notes: $data->notes,
                reference: $data->reference ?? $opportunity->reference,
                tag_list: array_values($data->tag_list ?? []),
                generated_at: $generatedAt->toIso8601String(),
                line_items: $linePayloads,
                tax_rows: $taxPayloads,
                mark_opportunity_invoiced: false,
                is_posting: true,
            );

            return $invoiceId;
        });

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();

        app(CustomFieldCopier::class)->copy($opportunity, $invoice, 'Invoice');

        return InvoiceData::fromModel($invoice);
    }

    private function resolveDepositNet(
        GenerateDepositInvoiceData $data,
        Opportunity $opportunity,
        int $fullSubtotal,
        int $fullGross,
    ): int {
        $calculator = app(DepositInvoiceCalculator::class);

        if ($data->amount_percentage !== null) {
            return $calculator->netFromPercentage($fullSubtotal, (string) $data->amount_percentage);
        }

        if ($data->amount_fixed === null) {
            throw ValidationException::withMessages([
                'amount_fixed' => ['Either amount_percentage or amount_fixed is required.'],
            ]);
        }

        if ($data->amount_fixed > $fullGross) {
            throw ValidationException::withMessages([
                'amount_fixed' => [
                    sprintf(
                        'Fixed deposit amount (%d) cannot exceed the opportunity total (%d).',
                        $data->amount_fixed,
                        $fullGross,
                    ),
                ],
            ]);
        }

        return $calculator->netFromFixedGross($fullSubtotal, $fullGross, $data->amount_fixed);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineToPayload(FrozenInvoiceLineData $line, int $lineId): array
    {
        return [
            'invoice_item_id' => $lineId,
            'line_key' => $line->line_key,
            'source_type' => $line->source_type,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'discount' => $line->discount,
            'net_total' => $line->net_total,
            'tax' => $line->tax,
            'product_tax_class_id' => $line->product_tax_class_id,
            'tax_treatment' => $line->tax_treatment,
            'opportunity_item_id' => $line->opportunity_item_id,
            'opportunity_cost_id' => $line->opportunity_cost_id,
            'product_id' => $line->product_id,
            'surcharge_id' => $line->surcharge_id,
            'section_item_id' => $line->section_item_id,
            'sort_order' => $line->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxRowToPayload(FrozenInvoiceTaxRowData $row, int $taxId): array
    {
        return [
            'invoice_tax_id' => $taxId,
            'product_tax_class_key' => $row->product_tax_class_key,
            'taxable_amount' => $row->taxable_amount,
            'tax_amount' => $row->tax_amount,
            'rate_percentage' => $row->rate_percentage,
            'tax_rate_name' => $row->tax_rate_name,
            'tax_rate_id' => $row->tax_rate_id,
            'tax_rule_id' => $row->tax_rule_id,
            'result_type' => $row->result_type,
            'legal_text' => $row->legal_text,
            'zone_tax_rule_id' => $row->zone_tax_rule_id,
        ];
    }

    private function resolveCurrency(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        return app(CurrencyService::class)->baseCurrencyCode();
    }

    private function resolveExchangeRate(string $currency, Opportunity $opportunity): string
    {
        if ($opportunity->exchange_rate !== '') {
            return (string) $opportunity->exchange_rate;
        }

        $base = app(CurrencyService::class)->baseCurrencyCode();

        if ($base === '' || $currency === $base) {
            return '1';
        }

        try {
            return app(CurrencyService::class)->getRate($currency, $base);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'currency' => [
                    "No exchange rate is configured from {$currency} to the base currency {$base}.",
                ],
            ]);
        }
    }
}
