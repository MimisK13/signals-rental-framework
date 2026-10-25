<?php

namespace App\Services\Shortages\Resolvers;

use App\Contracts\StockContextAwareShortageResolver;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Services\Availability\DemandReadContext;
use App\Services\Availability\StockReadContext;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageEventRecorder;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Date shift (shortage-resolution-sub-hires.md §4.4).
 *
 * Searches adjacent date ranges (forward and backward, in day increments) where
 * the full requested quantity is available, and offers each as an option.
 * Execution does NOT mutate the opportunity — per the spec, it records the
 * proposed shift (original vs shifted dates) and the user confirms the change
 * through the normal edit flow. Self-contained (it only reads the availability
 * engine) so it records a Confirmed resolution capturing the proposed window.
 */
class DateShiftResolver extends AbstractShortageResolver implements StockContextAwareShortageResolver
{
    /** Days to search either side of the requested window. */
    private const int SEARCH_DAYS = 3;

    public function __construct(
        ShortageEventRecorder $events,
        private readonly AvailabilityService $availability,
    ) {
        parent::__construct($events);
    }

    public function key(): string
    {
        return 'date_shift';
    }

    public function name(): string
    {
        return 'Shift dates';
    }

    public function priority(): int
    {
        return 40;
    }

    public function isAutoExecutable(): bool
    {
        return false;
    }

    /**
     * Only worth offering for definite (non-indefinite) windows we can shift.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0
            && $shortage->endsAt->lessThan(Demand::sentinel());
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        return $this->getOptionsUsingStockContext($shortage, null, null);
    }

    public function getOptionsWithStockContext(
        Shortage $shortage,
        StockReadContext $stockContext,
        ?DemandReadContext $demandContext = null,
    ): array {
        return $this->getOptionsUsingStockContext($shortage, $stockContext, $demandContext);
    }

    /**
     * @return list<ResolutionOption>
     */
    private function getOptionsUsingStockContext(
        Shortage $shortage,
        ?StockReadContext $stockContext,
        ?DemandReadContext $demandContext,
    ): array {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        // diffInSeconds is signed in Carbon 3 — take the absolute span so a shifted
        // window never ends before it starts (which would build an invalid tstzrange).
        $durationSeconds = abs($shortage->startsAt->diffInSeconds($shortage->endsAt));
        $options = [];
        $candidates = [];

        foreach ($this->candidateOffsets() as $offsetDays) {
            $start = $shortage->startsAt->copy()->addDays($offsetDays);
            $end = $start->copy()->addSeconds((int) $durationSeconds);

            $candidateKey = implode('|', [
                $offsetDays,
                $start->getTimestamp(),
                $end->getTimestamp(),
            ]);
            $candidates[$candidateKey] = [
                'offset_days' => $offsetDays,
                'start' => $start,
                'end' => $end,
            ];
        }

        // Evaluate shifted windows together so stock is shared by product/store
        // while demand remains specific to each booking window.
        $availability = $this->availability->availableForItems(array_values(array_map(
            fn (array $candidate): array => [
                'product_id' => $shortage->productId,
                'store_id' => $shortage->storeId,
                'from' => $candidate['start'],
                'to' => $candidate['end'],
                'exclude_source_type' => 'opportunity_item',
                'exclude_source_id' => $shortage->opportunityItemId,
            ],
            $candidates,
        )), $stockContext, $demandContext);

        $availableByCandidate = [];
        foreach (array_keys($candidates) as $index => $candidateKey) {
            $availableByCandidate[$candidateKey] = $availability[$index] ?? 0;
        }

        foreach ($candidates as $candidateKey => $candidate) {
            $available = $availableByCandidate[$candidateKey];

            if ($available < $shortage->requestedQuantity) {
                continue;
            }

            $direction = $candidate['offset_days'] > 0 ? 'later' : 'earlier';

            $options[] = new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::DateShift,
                label: sprintf('Shift %d day(s) %s', abs($candidate['offset_days']), $direction),
                description: sprintf(
                    'Full quantity (%d) is available %s, from %s.',
                    $shortage->requestedQuantity,
                    $candidate['start']->toDateString(),
                    $candidate['start']->toIso8601String(),
                ),
                quantityResolved: $shortage->requestedQuantity,
                isPartial: false,
                autoExecutable: false,
                metadata: [
                    'shifted_starts_at' => $candidate['start']->utc()->toIso8601String(),
                    'shifted_ends_at' => $candidate['end']->utc()->toIso8601String(),
                    'offset_days' => $candidate['offset_days'],
                ],
            );
        }

        return $options;
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->requestedQuantity,
            status: ShortageResolutionStatus::Confirmed,
            metadata: [
                'original_starts_at' => $shortage->startsAt->utc()->toIso8601String(),
                'original_ends_at' => $shortage->endsAt->utc()->toIso8601String(),
                'shifted_starts_at' => $option->metadata['shifted_starts_at'] ?? null,
                'shifted_ends_at' => $option->metadata['shifted_ends_at'] ?? null,
            ],
        );

        return ResolutionResult::confirmed(
            $resolution,
            'Recorded a date-shift proposal; apply the new dates through the opportunity edit flow.',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::DateShift;
    }

    /**
     * Offsets to probe: nearest days first, alternating earlier/later.
     *
     * @return list<int>
     */
    private function candidateOffsets(): array
    {
        $offsets = [];

        for ($day = 1; $day <= self::SEARCH_DAYS; $day++) {
            $offsets[] = $day;
            $offsets[] = -$day;
        }

        return $offsets;
    }
}
