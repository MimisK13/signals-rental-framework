<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Data;

class DocumentPreviewData extends Data
{
    /**
     * @param  array<string, mixed>|null  $rendered_data
     */
    public function __construct(
        public string $html,
        public ?array $rendered_data = null,
    ) {}
}
