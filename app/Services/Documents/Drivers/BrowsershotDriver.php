<?php

namespace App\Services\Documents\Drivers;

use App\Contracts\Documents\PdfDriver;
use App\Services\Documents\PageDimensions;
use App\Services\Documents\PdfPageLayout;
use App\ValueObjects\Documents\PdfDocumentParts;
use RuntimeException;

/**
 * Optional headless-Chromium PDF driver (requires spatie/browsershot).
 */
class BrowsershotDriver implements PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig, ?PdfDocumentParts $parts = null): string
    {
        if (! class_exists('Spatie\Browsershot\Browsershot')) {
            throw new RuntimeException(
                'The Browsershot PDF driver requires spatie/browsershot to be installed.'
            );
        }

        $paper = PageDimensions::browsershotPaper($pageConfig);
        $orientation = (string) ($pageConfig['page_orientation'] ?? config('documents.defaults.page_orientation', 'portrait'));

        if ($parts === null) {
            return $this->renderWithBrowsershot(
                html: $html,
                paper: $paper,
                orientation: $orientation,
                margins: PdfPageLayout::baseMargins($pageConfig),
                headerTemplate: null,
                footerTemplate: null,
            );
        }

        $hasHeader = $parts->hasHeader();
        $hasFooter = $parts->hasFooter();
        $margins = PdfPageLayout::pageMargins($pageConfig, $hasHeader, $hasFooter);

        return $this->renderWithBrowsershot(
            html: $this->wrapBodyHtml($html, $parts),
            paper: $paper,
            orientation: $orientation,
            margins: $margins,
            headerTemplate: $hasHeader
                ? $this->wrapChromiumTemplate($parts->header, $parts)
                : null,
            footerTemplate: $hasFooter
                ? $this->wrapChromiumTemplate($parts->footer, $parts)
                : null,
        );
    }

    /**
     * @param  array{format: ?string, width: ?float, height: ?float, unit: ?string}  $paper
     * @param  array{top: float, right: float, bottom: float, left: float}  $margins
     */
    protected function renderWithBrowsershot(
        string $html,
        array $paper,
        string $orientation,
        array $margins,
        ?string $headerTemplate,
        ?string $footerTemplate,
    ): string {
        $browsershotClass = 'Spatie\Browsershot\Browsershot';

        $shot = $browsershotClass::html($html);

        if ($paper['format'] !== null) {
            $shot = $shot->format($paper['format']);
        } else {
            $shot = $shot->paperSize(
                (float) $paper['width'],
                (float) $paper['height'],
                (string) $paper['unit'],
            );
        }

        $shot = $shot->landscape($orientation === 'landscape')
            ->margins(
                $margins['top'],
                $margins['right'],
                $margins['bottom'],
                $margins['left'],
            );

        if ($headerTemplate !== null || $footerTemplate !== null) {
            $shot = $shot->showBrowserHeaderAndFooter();

            if ($headerTemplate !== null) {
                $shot = $shot->headerHtml($headerTemplate);
            } else {
                $shot = $shot->hideHeader();
            }

            if ($footerTemplate !== null) {
                $shot = $shot->footerHtml($footerTemplate);
            } else {
                $shot = $shot->hideFooter();
            }
        }

        return $shot->pdf();
    }

    protected function wrapBodyHtml(string $bodyHtml, PdfDocumentParts $parts): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
{$parts->brandingCss}
{$parts->stylesCss}
</style>
</head>
<body>
<div class="document-body">{$bodyHtml}</div>
</body>
</html>
HTML;
    }

    protected function wrapChromiumTemplate(string $fragment, PdfDocumentParts $parts): string
    {
        return <<<HTML
<div style="width:100%; font-size:10px; -webkit-print-color-adjust:exact;">
<style>
{$parts->brandingCss}
{$parts->stylesCss}
</style>
{$fragment}
</div>
HTML;
    }
}
