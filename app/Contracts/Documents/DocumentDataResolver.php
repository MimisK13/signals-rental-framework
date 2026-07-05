<?php

namespace App\Contracts\Documents;

use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a source record into template-ready document data and declares the
 * field metadata consumed by the template field browser and schema API.
 */
interface DocumentDataResolver
{
    /**
     * Machine identifier for this document type (e.g. `opportunity`).
     */
    public function type(): string;

    /**
     * Human-readable label shown in admin UI and API listings.
     */
    public function label(): string;

    /**
     * Short description of what this document type renders.
     */
    public function description(): string;

    /**
     * Declare the merge fields available to templates for this document type.
     *
     * Field names use dot-path notation (`opportunity.subject`, `item.total`).
     * Metadata is grouped via {@see FieldBuilder::group()} for the field browser.
     */
    public function registerFields(SchemaBuilder $builder): void;

    /**
     * Load the source record with every relation required by {@see resolve()}.
     */
    public function loadSource(int $sourceId): Model;

    /**
     * Resolve the loaded source into nested, template-ready data.
     *
     * Money values are decimal strings; dates are formatted for the store
     * timezone; enums are rendered as human labels.
     *
     * @return array<string, mixed>
     */
    public function resolve(Model $source): array;
}
