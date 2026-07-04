<?php

namespace App\Data\Invoices;

use App\Data\Concerns\StripsCostFields;
use App\Models\InvoiceItem;
use Brick\Money\Currency;
use Brick\Money\Money;
use Spatie\LaravelData\Data;

/**
 * API representation of an invoice line item.
 */
class InvoiceItemData extends Data
{
    use StripsCostFields;

    /** @return list<string> */
    protected static function costFieldKeys(): array
    {
        return ['margin', 'margin_percent'];
    }

    public function __construct(
        public int $id,
        public int $invoice_id,
        public ?int $opportunity_item_id,
        public ?int $product_id,
        public string $description,
        public string $quantity,
        public ?string $invoiced_quantity,
        public string $unit_price,
        public string $discount,
        public string $tax,
        public string $total,
        public string $tax_treatment,
        public ?int $product_tax_class_id,
        public int $sort_order,
        public ?string $margin = null,
        public ?string $margin_percent = null,
    ) {}

    public static function fromModel(InvoiceItem $item, ?InvoiceLineMarginResult $lineMargin = null): self
    {
        return new self(
            id: $item->id,
            invoice_id: $item->invoice_id,
            opportunity_item_id: $item->opportunity_item_id,
            product_id: $item->product_id,
            description: $item->description,
            quantity: (string) $item->quantity,
            invoiced_quantity: $item->invoiced_quantity !== null ? (string) $item->invoiced_quantity : null,
            unit_price: $item->formatMoneyCost('unit_price'),
            discount: $item->formatMoneyCost('discount'),
            tax: $item->formatMoneyCost('tax'),
            total: $item->formatMoneyCost('total'),
            tax_treatment: $item->tax_treatment,
            product_tax_class_id: $item->product_tax_class_id,
            sort_order: $item->sort_order,
            margin: $lineMargin?->has_basis
                ? self::formatMinor($item, (int) ($lineMargin->margin_minor ?? 0))
                : null,
            margin_percent: $lineMargin?->margin_percent,
        );
    }

    private static function formatMinor(InvoiceItem $item, int $minor): string
    {
        $code = $item->currency_code !== '' ? $item->currency_code : 'GBP';
        $currency = Currency::of($code);

        return (string) Money::ofMinor($minor, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
