<?php

namespace App\ValueObjects\Documents;

/**
 * Structured header/body/footer fragments for PDF drivers that repeat margins on every page.
 */
readonly class PdfDocumentParts
{
    public function __construct(
        public string $header,
        public string $footer,
        public string $brandingCss,
        public string $stylesCss,
    ) {}

    public function hasHeader(): bool
    {
        return trim(strip_tags($this->header)) !== '';
    }

    public function hasFooter(): bool
    {
        return trim(strip_tags($this->footer)) !== '';
    }
}
