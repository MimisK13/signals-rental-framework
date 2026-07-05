<?php

namespace App\Data\Documents;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $page_config
     * @param  array<string, mixed>|null  $branding_config
     * @param  list<DocumentTemplateVersionData>|null  $versions
     */
    public function __construct(
        public int $id,
        public int $store_id,
        public ?int $created_by_member_id,
        public string $name,
        public string $slug,
        public ?string $description,
        public string $document_type,
        public string $model_type,
        public string $body_html,
        public ?string $header_html,
        public ?string $footer_html,
        public ?string $styles_css,
        public ?array $page_config,
        public ?array $branding_config,
        public bool $is_default,
        public bool $is_system,
        public bool $is_active,
        public int $version,
        public ?string $last_edited_at,
        public string $created_at,
        public string $updated_at,
        public ?array $versions = null,
    ) {}

    public static function fromModel(DocumentTemplate $template, bool $includeVersions = false): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $template->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $template->updated_at;

        $versions = null;

        if ($includeVersions) {
            $template->loadMissing('versions');

            $versions = $template->versions
                ->map(fn (DocumentTemplateVersion $version): DocumentTemplateVersionData => DocumentTemplateVersionData::fromModel($version))
                ->values()
                ->all();
        }

        /** @var Carbon|null $lastEditedAt */
        $lastEditedAt = $template->last_edited_at;

        return new self(
            id: $template->id,
            store_id: $template->store_id,
            created_by_member_id: $template->created_by_member_id,
            name: $template->name,
            slug: $template->slug,
            description: $template->description,
            document_type: $template->document_type,
            model_type: $template->model_type,
            body_html: $template->body_html,
            header_html: $template->header_html,
            footer_html: $template->footer_html,
            styles_css: $template->styles_css,
            page_config: $template->page_config,
            branding_config: $template->branding_config,
            is_default: $template->is_default,
            is_system: $template->is_system,
            is_active: $template->is_active,
            version: $template->version,
            last_edited_at: $lastEditedAt?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            versions: $versions,
        );
    }
}
