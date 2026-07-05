<?php

namespace App\Data\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentTemplateSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $document_type,
        public int $version,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(DocumentTemplate $template): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $template->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $template->updated_at;

        return new self(
            id: $template->id,
            name: $template->name,
            slug: $template->slug,
            document_type: $template->document_type,
            version: $template->version,
            is_active: $template->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
