<?php

namespace App\Data\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Services\FileService;
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
        public ?string $first_viewed_at,
        public ?string $download_url,
        public ?DocumentTemplateSummaryData $template,
        public ?DocumentPublicLinkData $public_link,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(
        Document $document,
        bool $includeTemplate = false,
        bool $includePublicLink = false,
        bool $includeDownloadUrl = false,
    ): self {
        $document->loadMissing('documentTemplate');

        /** @var Carbon $createdAt */
        $createdAt = $document->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $document->updated_at;

        /** @var Carbon|null $firstViewedAt */
        $firstViewedAt = $document->first_viewed_at;

        $fileService = app(FileService::class);

        $downloadPath = $document->pdf_path ?? $document->html_snapshot_path;

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
            first_viewed_at: $firstViewedAt?->toIso8601String(),
            download_url: $includeDownloadUrl
                ? $fileService->signedUrlOrNull($downloadPath)
                : null,
            template: $includeTemplate && $document->documentTemplate instanceof DocumentTemplate
                ? DocumentTemplateSummaryData::fromModel($document->documentTemplate)
                : null,
            public_link: $includePublicLink
                ? self::publicLinkFromModel($document)
                : null,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }

    public static function publicLinkFromModel(Document $document): DocumentPublicLinkData
    {
        $token = $document->public_token;
        $isActive = is_string($token) && $token !== '';

        $expiresAtIso = $document->public_expires_at?->toIso8601String();

        return new DocumentPublicLinkData(
            is_active: $isActive,
            public_url: $isActive ? route('documents.public.show', ['token' => $token]) : null,
            public_expires_at: $expiresAtIso,
            is_pin_protected: is_string($document->public_pin_hash) && $document->public_pin_hash !== '',
        );
    }
}
