<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\ValueObjects\DocumentRenderResult;

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
