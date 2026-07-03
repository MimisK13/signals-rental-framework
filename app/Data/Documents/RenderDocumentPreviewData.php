<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class RenderDocumentPreviewData extends Data
{
    public function __construct(
        #[Required, Exists('document_templates', 'id')]
        public int $document_template_id,
        #[Required]
        public int $source_id,
    ) {}
}
