<?php

declare(strict_types=1);

namespace App\Domain\Documents\Data;

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
