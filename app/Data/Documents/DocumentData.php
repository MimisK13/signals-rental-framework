<?php

namespace App\Data\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentData extends Data
{
    /**
     * @param  array<string, mixed>|null  $rendered_data
     */
    public function __construct(
        public int $id,
        public int $store_id,
        public ?int $document_template_id,
        public ?int $generated_by_user_id,
        public string $document_type,
        public string $document_number,
        public string $documentable_type,
        public int $documentable_id,
        public ?string $pdf_path,
        public ?string $html_snapshot_path,
        public DocumentStatus $status,
        public ?array $rendered_data,
        public string $locale,
        public string $currency_code,
        public ?int $template_version,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Document $document): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $document->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $document->updated_at;

        return new self(
            id: $document->id,
            store_id: $document->store_id,
            document_template_id: $document->document_template_id,
            generated_by_user_id: $document->generated_by_user_id,
            document_type: $document->document_type,
            document_number: $document->document_number,
            documentable_type: $document->documentable_type,
            documentable_id: $document->documentable_id,
            pdf_path: $document->pdf_path,
            html_snapshot_path: $document->html_snapshot_path,
            status: $document->status,
            rendered_data: $document->rendered_data,
            locale: $document->locale,
            currency_code: $document->currency_code,
            template_version: $document->template_version,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
