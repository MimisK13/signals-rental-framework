<?php

namespace App\Services\Invoices;

use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted credit note numbers from per-store document sequences.
 *
 * The number is allocated at event-fire time and baked into the CreditNoteIssued
 * event payload so replay reproduces the identical number.
 */
class CreditNoteNumberGenerator
{
    public const DOCUMENT_TYPE = 'credit_note';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(int $storeId, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next($storeId, self::DOCUMENT_TYPE, $at);
    }

    public function ensureSequence(int $storeId): DocumentSequence
    {
        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if ($sequence !== null) {
            return $sequence;
        }

        return DocumentSequence::query()->create(array_merge(
            [
                'store_id' => $storeId,
                'document_type' => self::DOCUMENT_TYPE,
            ],
            DocumentSequenceDefaults::attributes(self::DOCUMENT_TYPE),
        ));
    }

    public function nextOrCreate(int $storeId, ?DateTimeInterface $at = null): string
    {
        try {
            return $this->next($storeId, $at);
        } catch (ModelNotFoundException) {
            $this->ensureSequence($storeId);

            return $this->next($storeId, $at);
        }
    }
}
