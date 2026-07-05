<?php

namespace App\Services\Documents;

/**
 * Resolves document page sizes and converts between display units and canonical mm storage.
 */
class PageDimensions
{
    public const MM_PER_INCH = 25.4;

    /**
     * @param  array<string, mixed>  $pageConfig
     * @return array{width: float, height: float}
     */
    public static function sizeMm(array $pageConfig): array
    {
        $size = strtoupper((string) ($pageConfig['page_size'] ?? 'A4'));
        $orientation = strtolower((string) ($pageConfig['page_orientation'] ?? 'portrait'));

        [$widthMm, $heightMm] = match ($size) {
            'LETTER' => [216.0, 279.0],
            'A3' => [297.0, 420.0],
            'A5' => [148.0, 210.0],
            'CUSTOM' => [
                (float) ($pageConfig['page_width_mm'] ?? 210),
                (float) ($pageConfig['page_height_mm'] ?? 297),
            ],
            default => [210.0, 297.0],
        };

        if ($orientation === 'landscape') {
            return ['width' => $heightMm, 'height' => $widthMm];
        }

        return ['width' => $widthMm, 'height' => $heightMm];
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public static function displayUnit(array $pageConfig): string
    {
        $unit = strtolower((string) ($pageConfig['display_unit'] ?? 'mm'));

        return $unit === 'in' ? 'in' : 'mm';
    }

    public static function mmToDisplay(float $mm, string $unit): float
    {
        $value = $unit === 'in' ? $mm / self::MM_PER_INCH : $mm;

        return round($value, 2);
    }

    public static function displayToMm(float $display, string $unit): float
    {
        if ($unit === 'in') {
            return round($display * self::MM_PER_INCH, 4);
        }

        return round($display, 4);
    }

    /**
     * DomPDF setPaper() accepts a named format or a points array for custom sizes.
     *
     * @param  array<string, mixed>  $pageConfig
     * @return array{0: string|array{0: float, 1: float, 2: float, 3: float}, 1: string}
     */
    public static function dompdfPaper(array $pageConfig): array
    {
        $orientation = strtolower((string) ($pageConfig['page_orientation'] ?? 'portrait'));
        $size = strtoupper((string) ($pageConfig['page_size'] ?? 'A4'));

        if ($size === 'CUSTOM') {
            $dimensions = self::sizeMm($pageConfig);
            $widthPt = $dimensions['width'] * 72 / self::MM_PER_INCH;
            $heightPt = $dimensions['height'] * 72 / self::MM_PER_INCH;

            return [[0, 0, $widthPt, $heightPt], $orientation];
        }

        $paperName = match ($size) {
            'LETTER' => 'letter',
            'A3' => 'a3',
            'A5' => 'a5',
            default => 'a4',
        };

        return [$paperName, $orientation];
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     * @return array{format: ?string, width: ?float, height: ?float, unit: ?string}
     */
    public static function browsershotPaper(array $pageConfig): array
    {
        $size = strtoupper((string) ($pageConfig['page_size'] ?? 'A4'));

        if ($size === 'CUSTOM') {
            $dimensions = self::sizeMm(array_merge($pageConfig, ['page_orientation' => 'portrait']));

            return [
                'format' => null,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'unit' => 'mm',
            ];
        }

        $format = match ($size) {
            'LETTER' => 'Letter',
            'A3' => 'A3',
            'A5' => 'A5',
            default => 'A4',
        };

        return [
            'format' => $format,
            'width' => null,
            'height' => null,
            'unit' => null,
        ];
    }
}
