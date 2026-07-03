<?php

namespace App\Contracts\Documents;

/**
 * Converts assembled HTML into a PDF binary.
 */
interface PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig): string;
}
