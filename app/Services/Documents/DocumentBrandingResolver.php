<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\FileService;

/**
 * Resolves effective branding for a document render (template → store → defaults).
 *
 * Branding values are exposed as CSS custom properties for template styling.
 */
class DocumentBrandingResolver
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    /**
     * @return array{
     *     primary_colour: string,
     *     secondary_colour: string,
     *     font_family: string,
     *     logo_url: ?string,
     *     css: string
     * }
     */
    public function resolve(DocumentTemplate $template, Store $store): array
    {
        $defaults = config('documents.defaults.branding');
        $overrides = $template->branding_config ?? [];

        $primary = $overrides['primary_colour']
            ?? $store->brand_primary_colour
            ?? $defaults['primary_colour'];

        $secondary = $overrides['secondary_colour']
            ?? $store->brand_secondary_colour
            ?? $defaults['secondary_colour'];

        $fontFamily = $overrides['font_family']
            ?? $store->brand_font_family
            ?? $defaults['font_family'];

        $logoPath = $overrides['logo_path'] ?? $store->brand_logo_path;
        $logoUrl = $this->fileService->signedUrlOrNull($logoPath);

        $css = $this->buildCssVariables($primary, $secondary, $fontFamily, $logoUrl);

        return [
            'primary_colour' => $primary,
            'secondary_colour' => $secondary,
            'font_family' => $fontFamily,
            'logo_url' => $logoUrl,
            'css' => $css,
        ];
    }

    private function buildCssVariables(
        string $primary,
        string $secondary,
        string $fontFamily,
        ?string $logoUrl,
    ): string {
        $logoValue = $logoUrl !== null ? "'{$logoUrl}'" : 'none';

        return <<<CSS
:root {
    --primary-colour: {$primary};
    --secondary-colour: {$secondary};
    --font-family: {$fontFamily};
    --logo-url: {$logoValue};
}
body {
    font-family: var(--font-family), sans-serif;
    color: #111827;
}
CSS;
    }
}
