<?php

namespace App\Services\Documents\Drivers;

use App\Contracts\Documents\PdfDriver;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * Optional headless-Chromium PDF driver (requires spatie/browsershot).
 */
class BrowsershotDriver implements PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig): string
    {
        if (! class_exists(Browsershot::class)) {
            throw new RuntimeException(
                'The Browsershot PDF driver requires spatie/browsershot to be installed.'
            );
        }

        $pageSize = (string) ($pageConfig['page_size'] ?? config('documents.defaults.page_size', 'A4'));
        $orientation = (string) ($pageConfig['page_orientation'] ?? config('documents.defaults.page_orientation', 'portrait'));
        $margins = $pageConfig['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];

        /** @var class-string $browsershot */
        $browsershot = Browsershot::class;

        $shot = $browsershot::html($html)
            ->format($pageSize)
            ->landscape($orientation === 'landscape')
            ->margins(
                (float) ($margins['top'] ?? 10),
                (float) ($margins['right'] ?? 10),
                (float) ($margins['bottom'] ?? 10),
                (float) ($margins['left'] ?? 10),
            );

        return $shot->pdf();
    }
}
