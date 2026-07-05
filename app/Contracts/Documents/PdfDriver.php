<?php

namespace App\Contracts\Documents;

use App\ValueObjects\Documents\PdfDocumentParts;

/**
 * Converts assembled HTML into a PDF binary.
 */
interface PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig, ?PdfDocumentParts $parts = null): string;
}
