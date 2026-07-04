<?php

namespace App\Services\Opportunities;

use App\Models\DocumentSequence;
use App\Models\Opportunity;
use App\Services\Documents\DocumentNumberGenerator;
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
        $this->ensureSequence($storeId);

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

        return DocumentSequence::query()->create([
            'store_id' => $storeId,
            'document_type' => self::DOCUMENT_TYPE,
            'prefix' => '',
            'suffix' => '',
            'include_year' => false,
            'include_month' => false,
            'next_number' => $this->seededNextNumber($storeId),
            'padding' => $this->provisioningPadding(),
            'reset_frequency' => 'never',
            'is_entity_number' => true,
        ]);
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

    /**
     * Padding width read once when provisioning a store's opportunity sequence.
     *
     * @deprecated Setting `opportunities.number_pad` is migration-input only; the
     *             Numbering admin page owns padding after convergence.
     */
    private function provisioningPadding(): int
    {
        return (int) settings('opportunities.number_pad', 10);
    }

    /**
     * Next counter for a newly provisioned store sequence.
     *
     * When `opportunities.number_scope` was `global` (migration-input only), seed
     * from the highest numeric `number` across **all** stores + 1 so no store
     * re-issues a number consumed by the legacy shared SequenceAllocator counter.
     *
     * When scope was `store`, seed from this store's highest numeric number + 1 so
     * each store keeps an independent running sequence after convergence.
     */
    private function seededNextNumber(int $storeId): int
    {
        /** @var 'store'|'global' $scope @deprecated migration-input only */
        $scope = settings('opportunities.number_scope', 'store');

        if ($scope === 'global') {
            return $this->globalNumericMax() + 1;
        }

        return $this->storeNumericMax($storeId) + 1;
    }

    private function globalNumericMax(): int
    {
        $max = Opportunity::withTrashed()
            ->whereNotNull('number')
            ->pluck('number')
            ->map(fn (string $number): int => $this->numericPortion($number))
            ->max();

        return (int) $max;
    }

    private function storeNumericMax(int $storeId): int
    {
        $max = Opportunity::withTrashed()
            ->where('store_id', $storeId)
            ->whereNotNull('number')
            ->pluck('number')
            ->map(fn (string $number): int => $this->numericPortion($number))
            ->max();

        return (int) $max;
    }

    private function numericPortion(string $number): int
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        if ($digits === '') {
            return 0;
        }

        return (int) $digits;
    }
}
