<?php

namespace App\Services\Opportunities;

use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates zero-padded opportunity numbers from per-store document sequences.
 *
 * The number is allocated at event-fire time (in CreateOpportunity) and baked
 * into the OpportunityCreated event payload, so a truncate + Verbs::replay()
 * rebuild reproduces the identical number (same replay-stability principle as
 * the projection id). Allocation therefore lives ONLY in the action — replay
 * re-applies the stored payload and never calls this generator.
 *
 * Legacy installs allocated via SequenceAllocator with an optional global scope
 * (`opportunities.number_scope`, migration-input only). Runtime numbering is
 * always per-store via document_sequences. First provision seeds next_number from
 * the global numeric max + 1 when legacy scope was `global`, otherwise from the
 * store's own max + 1 so fresh multi-store installs keep independent counters.
 */
class OpportunityNumberGenerator
{
    public const DOCUMENT_TYPE = 'opportunity';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(int $storeId, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next($storeId, self::DOCUMENT_TYPE, $at);
    }

    public function ensureSequence(int $storeId): DocumentSequence
    {
        return DocumentSequenceDefaults::ensureExists($storeId, self::DOCUMENT_TYPE);
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
