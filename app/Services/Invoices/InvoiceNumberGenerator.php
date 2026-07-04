<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceType;
use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted invoice numbers from per-store, per-type document sequences.
 *
 * The number is allocated at event-fire time (in CreateDraftInvoice) and baked
 * into the InvoiceGenerated event payload, so a truncate + Verbs::replay()
 * rebuild reproduces the identical number. Allocation therefore lives ONLY in
 * the action — replay re-applies the stored payload and never calls this
 * generator.
 */
class InvoiceNumberGenerator
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    /**
     * Allocate and format the next invoice number for a store and invoice type.
     */
    public function next(int $storeId, InvoiceType $type, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next(
            $storeId,
            $type->documentSequenceType(),
            $at,
        );
    }

    /**
     * Ensure a document sequence row exists for the store and type, seeding a
     * sensible default when missing (fire-time only — never on replay).
     */
    public function ensureSequence(int $storeId, InvoiceType $type): DocumentSequence
    {
        $documentType = $type->documentSequenceType();

        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->first();

        if ($sequence !== null) {
            return $sequence;
        }

        return DocumentSequence::query()->create(array_merge(
            [
                'store_id' => $storeId,
                'document_type' => $documentType,
            ],
            DocumentSequenceDefaults::attributes($documentType),
        ));
    }

    /**
     * Allocate the next number, creating the backing sequence on first use.
     */
    public function nextOrCreate(int $storeId, InvoiceType $type, ?DateTimeInterface $at = null): string
    {
        try {
            return $this->next($storeId, $type, $at);
        } catch (ModelNotFoundException) {
            $this->ensureSequence($storeId, $type);

            return $this->next($storeId, $type, $at);
        }
    }

    public function preview(int $storeId, InvoiceType $type, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->preview(
            $storeId,
            $type->documentSequenceType(),
            $at,
        );
    }
}
