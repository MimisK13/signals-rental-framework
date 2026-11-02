<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Models\QuotationVersion;
use Spatie\LaravelData\Data;

final class QuotationVersionData extends Data
{
    public function __construct(
        public int $id,
        public int $state_id,
        public int $quotation_id,
        public int $version_number,
        public string $version_type,
        public ?string $label,
        public bool $is_active,
        public string $status,
        public int $charge_excluding_tax_total,
        public int $tax_total,
        public int $charge_including_tax_total,
        public int $charge_total,
        public string $currency_code,
        public string $exchange_rate,
        public bool $prices_include_tax,
    ) {}

    public static function fromModel(QuotationVersion $version): self
    {
        return new self(
            id: $version->id,
            state_id: $version->state_id,
            quotation_id: $version->quotation_id,
            version_number: $version->version_number,
            version_type: $version->version_type->value,
            label: $version->label,
            is_active: $version->is_active,
            status: $version->status->value,
            charge_excluding_tax_total: $version->charge_excluding_tax_total,
            tax_total: $version->tax_total,
            charge_including_tax_total: $version->charge_including_tax_total,
            charge_total: $version->charge_total,
            currency_code: $version->currency_code,
            exchange_rate: $version->exchange_rate,
            prices_include_tax: $version->prices_include_tax,
        );
    }
}
