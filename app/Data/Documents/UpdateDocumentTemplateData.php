<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class UpdateDocumentTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $page_config
     * @param  array<string, mixed>|null  $branding_config
     */
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        #[Max(255)]
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $body_html = null,
        public ?string $header_html = null,
        public ?string $footer_html = null,
        public ?string $styles_css = null,
        public ?array $page_config = null,
        public ?array $branding_config = null,
        public ?bool $is_active = null,
        public ?string $change_note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'body_html' => ['sometimes', 'string'],
            'header_html' => ['sometimes', 'nullable', 'string'],
            'footer_html' => ['sometimes', 'nullable', 'string'],
            'styles_css' => ['sometimes', 'nullable', 'string'],
            'page_config' => ['sometimes', 'nullable', 'array'],
            'branding_config' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'change_note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
