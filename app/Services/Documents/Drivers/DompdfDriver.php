<?php

namespace App\Services\Documents\Drivers;

use App\Contracts\Documents\PdfDriver;
use App\Services\Documents\PageDimensions;
use App\Services\Documents\PdfPageLayout;
use App\ValueObjects\Documents\PdfDocumentParts;
use Barryvdh\DomPDF\Facade\Pdf;

class DompdfDriver implements PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig, ?PdfDocumentParts $parts = null): string
    {
        $documentHtml = $parts !== null
            ? $this->buildRepeatingLayoutHtml($html, $parts, $pageConfig)
            : $html;

        return $this->renderPdfFromHtml($documentHtml, $pageConfig);
    }

    /**
     * @param  array<string, mixed>  $pageConfig
     */
    protected function renderPdfFromHtml(string $html, array $pageConfig): string
    {
        [$paper, $orientation] = PageDimensions::dompdfPaper($pageConfig);

        return Pdf::loadHTML($html)
            ->setPaper($paper, $orientation)
            ->output();
    }

    /**
     * Build HTML with DomPDF fixed-position header/footer that repeat on every page.
     *
     * Body content is kept out of the normal header/footer flow so single-page documents
     * do not show a duplicate header above the body.
     *
     * @param  array<string, mixed>  $pageConfig
     */
    protected function buildRepeatingLayoutHtml(
        string $bodyHtml,
        PdfDocumentParts $parts,
        array $pageConfig,
    ): string {
        $hasHeader = $parts->hasHeader();
        $hasFooter = $parts->hasFooter();
        $baseMargins = PdfPageLayout::baseMargins($pageConfig);
        $pageMargins = PdfPageLayout::pageMargins($pageConfig, $hasHeader, $hasFooter);
        $headerZone = PdfPageLayout::headerZoneMm($pageConfig, $hasHeader);
        $footerZone = PdfPageLayout::footerZoneMm($pageConfig, $hasFooter);

        $layoutCss = <<<CSS
@page {
    margin-top: {$pageMargins['top']}mm;
    margin-right: {$pageMargins['right']}mm;
    margin-bottom: {$pageMargins['bottom']}mm;
    margin-left: {$pageMargins['left']}mm;
}
body {
    margin: 0;
}
.document-body {
    width: 100%;
}
CSS;

        if ($hasHeader) {
            $headerOffset = $baseMargins['top'] + $headerZone;
            $layoutCss .= <<<CSS

.pdf-page-header {
    position: fixed;
    top: -{$headerOffset}mm;
    left: 0;
    right: 0;
    height: {$headerZone}mm;
    overflow: hidden;
}
CSS;
        }

        if ($hasFooter) {
            $footerOffset = $baseMargins['bottom'] + $footerZone;
            $layoutCss .= <<<CSS

.pdf-page-footer {
    position: fixed;
    bottom: -{$footerOffset}mm;
    left: 0;
    right: 0;
    height: {$footerZone}mm;
    overflow: hidden;
}
CSS;
        }

        $headerMarkup = $hasHeader
            ? '<div class="pdf-page-header document-header">'.$parts->header.'</div>'
            : '';
        $footerMarkup = $hasFooter
            ? '<div class="pdf-page-footer document-footer">'.$parts->footer.'</div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
{$parts->brandingCss}
{$parts->stylesCss}
{$layoutCss}
</style>
</head>
<body>
{$headerMarkup}
<div class="document-body">{$bodyHtml}</div>
{$footerMarkup}
</body>
</html>
HTML;
    }
}
