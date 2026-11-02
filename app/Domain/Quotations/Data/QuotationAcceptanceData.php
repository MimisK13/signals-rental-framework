<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use Spatie\LaravelData\Data;

final class QuotationAcceptanceData extends Data
{
    public function __construct(
        public int $quotation_id,
        public int $version_id,
        public int $project_id,
        public string $status,
        public string $accepted_at,
    ) {}

    public static function fromModels(Quotation $quotation, QuotationVersion $version): self
    {
        return new self(
            quotation_id: $quotation->id,
            version_id: $version->id,
            project_id: $quotation->project_id,
            status: $quotation->status->value,
            accepted_at: $quotation->accepted_at?->toIso8601String() ?? '',
        );
    }
}
