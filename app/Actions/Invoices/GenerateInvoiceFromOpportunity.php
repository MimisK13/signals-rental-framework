<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\InvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Services\CurrencyService;
use App\Services\CustomFieldCopier;
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
 * Checkout: generate an invoice from an opportunity with a frozen tax snapshot.
 */
class GenerateInvoiceFromOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(GenerateInvoiceFromOpportunityData $data): InvoiceData
    {
        Gate::authorize('invoices.create');

        $type = InvoiceType::from($data->type);

        if ($type !== InvoiceType::Standard) {
            throw ValidationException::withMessages([
                'type' => ['Standard checkout uses GenerateInvoiceFromOpportunity; use the dedicated action for deposit or pro-forma invoices.'],
            ]);
        }

        $opportunity = Opportunity::query()
            ->with(['member', 'store'])
            ->findOrFail($data->opportunity_id);

        app(InvoiceSourceStateRule::class)->assertAllowed($opportunity, $type);

        $generatedAt = CarbonImmutable::now('UTC');

        $invoiceId = $this->commitVerbs(function () use ($data, $type, $opportunity, $generatedAt): int {
            $existing = Invoice::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return (int) $existing->id;
            }

            $snapshot = app(OpportunityInvoiceSnapshotter::class)->snapshot($opportunity);

            $totals = app(InvoiceTotalsCalculator::class)->calculate(
                $opportunity,
                $snapshot['lines'],
                $snapshot['tax_lines'],
                $snapshot['net_headline'],
                $generatedAt,
            );

            if ($totals['total'] !== (int) $opportunity->charge_including_tax_total) {
                throw ValidationException::withMessages([
                    'opportunity_id' => [
                        sprintf(
                            'Invoice total (%d) does not match opportunity charge_including_tax_total (%d).',
                            $totals['total'],
                            (int) $opportunity->charge_including_tax_total,
                        ),
                    ],
                ]);
            }

            $invoiceId = app(SequenceAllocator::class)->next('invoices');
            $allocator = app(SequenceAllocator::class);

            $linePayloads = array_map(
                function (FrozenInvoiceLineData $line) use ($allocator): array {
                    $lineId = $allocator->next('invoice_items');

                    return $this->lineToPayload($line, $lineId);
                },
                $totals['lines'],
            );

            $taxPayloads = array_map(
                function (FrozenInvoiceTaxRowData $row) use ($allocator): array {
                    $taxId = $allocator->next('invoice_taxes');

                    return $this->taxRowToPayload($row, $taxId);
                },
                $totals['tax_rows'],
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
                subtotal: $totals['subtotal'],
                tax_total: $totals['tax_total'],
                total: $totals['total'],
                notes: $data->notes,
                reference: $data->reference ?? $opportunity->reference,
                tag_list: array_values($data->tag_list ?? []),
                generated_at: $generatedAt->toIso8601String(),
                line_items: $linePayloads,
                tax_rows: $taxPayloads,
                mark_opportunity_invoiced: true,
            );

            return $invoiceId;
        });

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();

        app(CustomFieldCopier::class)->copy($opportunity, $invoice, 'Invoice');

        return InvoiceData::fromModel($invoice);
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
