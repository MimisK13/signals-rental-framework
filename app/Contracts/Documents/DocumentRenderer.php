<?php

namespace App\Contracts\Documents;

use App\Enums\DocumentOutputFormat;
use App\Models\DocumentTemplate;
use App\ValueObjects\Documents\DocumentRenderResult;

/**
 * Five-stage document render pipeline consumed by quotes, invoices, POs, etc.
 *
 * Stages: resolve → compile (sandboxed Blade) → assemble → output → (record via action).
 * Implementations must remain Octane-safe — no request-scoped state in singletons.
 */
interface DocumentRenderer
{
    /**
     * Render a template against live source data.
     */
    public function render(
        DocumentTemplate $template,
        string $documentType,
        int $sourceId,
        DocumentOutputFormat $format = DocumentOutputFormat::Preview,
    ): DocumentRenderResult;

    /**
     * Re-render from a frozen rendered_data snapshot (immutable document reproduction).
     *
     * @param  array<string, mixed>  $renderedData
     */
    public function renderFromSnapshot(
        DocumentTemplate $template,
        array $renderedData,
        DocumentOutputFormat $format = DocumentOutputFormat::Preview,
    ): DocumentRenderResult;
}
