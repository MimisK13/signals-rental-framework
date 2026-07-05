<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\InvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\CurrencyService;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\InvoiceGenerated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new draft invoice via the InvoiceGenerated event, committing the
 * event and its projection atomically.
 */
class CreateDraftInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(CreateDraftInvoiceData $data): InvoiceData
    {
        Gate::authorize('invoices.create');

        $type = InvoiceType::from($data->type);
        $status = app(InvoiceStatusRegistry::class)->defaultStatus();
        app(InvoiceStatusRegistry::class)->assertKnown($status);

        $currency = $this->resolveCurrency($data->currency);
        $generatedAt = CarbonImmutable::now('UTC');

        $invoiceId = $this->commitVerbs(function () use ($data, $type, $status, $currency, $generatedAt): int {
            $invoiceId = app(SequenceAllocator::class)->next('invoices');

            $number = app(InvoiceNumberGenerator::class)->nextOrCreate(
                $data->store_id,
                $type,
                $generatedAt,
            );

            InvoiceGenerated::fire(
                invoice_id: $invoiceId,
                number: $number,
                type: $type->value,
                status: $status,
                member_id: $data->member_id,
                store_id: $data->store_id,
                opportunity_id: $data->opportunity_id,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency),
                prices_include_tax: $data->prices_include_tax ?? (bool) settings('finance.prices_include_tax_default', false),
                notes: $data->notes,
                reference: $data->reference,
                tag_list: array_values($data->tag_list ?? []),
                generated_at: $generatedAt->toIso8601String(),
            );

            return $invoiceId;
        });

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();

        return InvoiceData::fromModel($invoice);
    }

    private function resolveCurrency(?string $currency): string
    {
        if ($currency !== null && $currency !== '') {
            return $currency;
        }

        return app(CurrencyService::class)->baseCurrencyCode();
    }

    private function resolveExchangeRate(string $currency): string
    {
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
