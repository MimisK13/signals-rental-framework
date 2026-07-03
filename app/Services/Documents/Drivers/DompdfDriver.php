<?php

namespace App\Services\Documents\Drivers;

use App\Contracts\Documents\PdfDriver;
use Barryvdh\DomPDF\Facade\Pdf;

class DompdfDriver implements PdfDriver
{
    /**
     * @param  array<string, mixed>  $pageConfig
     */
    public function render(string $html, array $pageConfig): string
    {
        $pageSize = (string) ($pageConfig['page_size'] ?? config('documents.defaults.page_size', 'A4'));
        $orientation = (string) ($pageConfig['page_orientation'] ?? config('documents.defaults.page_orientation', 'portrait'));

        return Pdf::loadHTML($html)
            ->setPaper($pageSize, $orientation)
            ->output();
    }
}
