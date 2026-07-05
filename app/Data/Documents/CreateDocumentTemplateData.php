<?php

namespace App\Data\Documents;

use App\Services\Documents\SystemDocumentTemplates;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateDocumentTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $page_config
     * @param  array<string, mixed>|null  $branding_config
     */
    public function __construct(
        #[Required]
        public int $store_id,
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(255)]
        public string $slug,
        public ?string $description,
        #[Required, Max(255)]
        public string $document_type,
        public ?string $model_type,
        #[Required]
        public string $body_html,
        public ?string $header_html = null,
        public ?string $footer_html = null,
        public ?string $styles_css = null,
        public ?array $page_config = null,
        public ?array $branding_config = null,
        public bool $is_default = false,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'document_type' => ['required', 'string', 'max:255', Rule::in(SystemDocumentTemplates::CORE_TYPES)],
            'model_type' => ['sometimes', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'header_html' => ['sometimes', 'nullable', 'string'],
            'footer_html' => ['sometimes', 'nullable', 'string'],
            'styles_css' => ['sometimes', 'nullable', 'string'],
            'page_config' => ['sometimes', 'nullable', 'array'],
            'branding_config' => ['sometimes', 'nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
