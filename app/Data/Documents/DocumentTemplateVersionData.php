<?php

namespace App\Data\Documents;

use App\Models\DocumentTemplateVersion;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentTemplateVersionData extends Data
{
    /**
     * @param  array<string, mixed>|null  $page_config
     */
    public function __construct(
        public int $id,
        public int $document_template_id,
        public ?int $edited_by_member_id,
        public int $version,
        public string $body_html,
        public ?string $header_html,
        public ?string $footer_html,
        public ?string $styles_css,
        public ?array $page_config,
        public ?string $change_note,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(DocumentTemplateVersion $version): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $version->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $version->updated_at;

        return new self(
            id: $version->id,
            document_template_id: $version->document_template_id,
            edited_by_member_id: $version->edited_by_member_id,
            version: $version->version,
            body_html: $version->body_html,
            header_html: $version->header_html,
            footer_html: $version->footer_html,
            styles_css: $version->styles_css,
            page_config: $version->page_config,
            change_note: $version->change_note,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
