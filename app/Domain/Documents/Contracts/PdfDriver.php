<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\ValueObjects\PdfDocumentParts;

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
