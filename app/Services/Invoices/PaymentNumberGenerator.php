<?php

namespace App\Services\Invoices;

use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted invoice payment numbers from per-store document sequences.
 *
 * The number is allocated at event-fire time (in RecordPayment) and baked into
 * the PaymentRecorded event payload, so replay reproduces the identical number.
 */
class PaymentNumberGenerator
{
    public const DOCUMENT_TYPE = 'invoice_payment';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(int $storeId, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next(
            $storeId,
            self::DOCUMENT_TYPE,
            $at,
        );
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
