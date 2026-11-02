<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Models\Quotation;
use Spatie\LaravelData\Data;

final class QuotationData extends Data
{
    public function __construct(
        public int $id,
        public int $state_id,
        public ?int $project_id,
        public int $member_id,
        public ?int $venue_id,
        public int $store_id,
        public ?int $owned_by,
        public string $number,
        public string $subject,
        public ?string $reference,
        public ?string $description,
        public string $status,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?string $valid_until,
        public string $currency_code,
        public string $exchange_rate,
        public bool $prices_include_tax,
        public ?int $active_version_id,
        public ?int $accepted_version_id,
        public int $version_count,
        public int $charge_excluding_tax_total,
        public int $tax_total,
        public int $charge_including_tax_total,
        public int $charge_total,
        public int $editor_revision,
    ) {}

    public static function fromModel(Quotation $quotation): self
    {
        return new self(
            id: $quotation->id,
            state_id: $quotation->state_id,
            project_id: $quotation->project_id,
            member_id: $quotation->member_id,
            venue_id: $quotation->venue_id,
            store_id: $quotation->store_id,
            owned_by: $quotation->owned_by,
            number: $quotation->number,
            subject: $quotation->subject,
            reference: $quotation->reference,
            description: $quotation->description,
            status: $quotation->status->value,
            starts_at: $quotation->starts_at?->toIso8601String(),
            ends_at: $quotation->ends_at?->toIso8601String(),
            valid_until: $quotation->valid_until?->toIso8601String(),
            currency_code: $quotation->currency_code,
            exchange_rate: $quotation->exchange_rate,
            prices_include_tax: $quotation->prices_include_tax,
            active_version_id: $quotation->active_version_id,
            accepted_version_id: $quotation->accepted_version_id,
            version_count: $quotation->version_count,
            charge_excluding_tax_total: $quotation->charge_excluding_tax_total,
            tax_total: $quotation->tax_total,
            charge_including_tax_total: $quotation->charge_including_tax_total,
            charge_total: $quotation->charge_total,
            editor_revision: $quotation->editor_revision,
        );
    }
}
