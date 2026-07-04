<?php

namespace App\Services\Documents;

/**
 * Resolves page margins and header/footer zones for PDF drivers.
 */
class PdfPageLayout
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public static function headerZoneMm(array $pageConfig, bool $hasHeader): float
    {
        if (! $hasHeader) {
            return 0.0;
        }

        return (float) ($pageConfig['header_height_mm']
            ?? config('documents.defaults.page.header_height_mm', 25));
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public static function footerZoneMm(array $pageConfig, bool $hasFooter): float
    {
        if (! $hasFooter) {
            return 0.0;
        }

        return (float) ($pageConfig['footer_height_mm']
            ?? config('documents.defaults.page.footer_height_mm', 15));
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public static function baseMargins(array $pageConfig): array
    {
        $margins = $pageConfig['margins'] ?? config('documents.defaults.page.margins', []);

        return [
            'top' => (float) ($margins['top'] ?? 10),
            'right' => (float) ($margins['right'] ?? 10),
            'bottom' => (float) ($margins['bottom'] ?? 10),
            'left' => (float) ($margins['left'] ?? 10),
        ];
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public static function pageMargins(array $pageConfig, bool $hasHeader, bool $hasFooter): array
    {
        $base = self::baseMargins($pageConfig);

        return [
            'top' => $base['top'] + self::headerZoneMm($pageConfig, $hasHeader),
            'right' => $base['right'],
            'bottom' => $base['bottom'] + self::footerZoneMm($pageConfig, $hasFooter),
            'left' => $base['left'],
        ];
    }
}
