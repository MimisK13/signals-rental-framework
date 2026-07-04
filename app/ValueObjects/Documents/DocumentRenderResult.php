<?php

namespace App\ValueObjects\Documents;

use App\Enums\DocumentOutputFormat;

/**
 * Result of the resolve → compile → assemble → output pipeline stages.
 */
readonly class DocumentRenderResult
{
    /**
     * @param  array<string, mixed>|null  $renderedData
     */
    public function __construct(
        public bool $success,
        public ?string $html = null,
        public ?string $pdfBinary = null,
        public ?array $renderedData = null,
        public ?string $error = null,
        public DocumentOutputFormat $format = DocumentOutputFormat::Preview,
    ) {}

    /**
     * @param  array<string, mixed>|null  $renderedData
     */
    public static function success(
        string $html,
        ?array $renderedData,
        DocumentOutputFormat $format,
        ?string $pdfBinary = null,
    ): self {
        return new self(
            success: true,
            html: $html,
            pdfBinary: $pdfBinary,
            renderedData: $renderedData,
            format: $format,
        );
    }

    public static function failure(string $error, DocumentOutputFormat $format): self
    {
        return new self(
            success: false,
            error: $error,
            format: $format,
        );
    }
}
