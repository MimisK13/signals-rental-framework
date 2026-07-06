<?php

namespace App\Data\Documents;

use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentSequenceData extends Data
{
    public function __construct(
        public int $id,
        public int $store_id,
        public string $document_type,
        public string $prefix,
        public string $suffix,
        public bool $include_year,
        public bool $include_month,
        public int $next_number,
        public int $padding,
        public string $reset_frequency,
        public ?int $current_period_year,
        public ?int $current_period_month,
        public string $next_number_preview,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(DocumentSequence $sequence): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $sequence->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $sequence->updated_at;

        $preview = app(DocumentNumberGenerator::class)->preview(
            $sequence->store_id,
            $sequence->document_type,
        );

        return new self(
            id: $sequence->id,
            store_id: $sequence->store_id,
            document_type: $sequence->document_type,
            prefix: $sequence->prefix,
            suffix: $sequence->suffix,
            include_year: $sequence->include_year,
            include_month: $sequence->include_month,
            next_number: $sequence->next_number,
            padding: $sequence->padding,
            reset_frequency: $sequence->reset_frequency,
            current_period_year: $sequence->current_period_year,
            current_period_month: $sequence->current_period_month,
            next_number_preview: $preview,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
