<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for opportunity presentation on timelines and grids:
 * phase band colours (Delivery / In-Use / Collection), customer Collecting /
 * Returning badges, status swatches, and legend/key assembly.
 */
class OpportunityPresentation
{
    /**
     * Phase band colours — shared by the planner legend, card status row, and
     * availability booking grids.
     *
     * @return array{delivery: string, in-use: string, collection: string}
     */
    public function phaseColours(): array
    {
        return [
            'delivery' => '#3498db',
            'in-use' => '#8e95a6',
            'collection' => '#27ae60',
        ];
    }

    /**
     * @return array{
     *     delivery: array{colour: string, icon: null, label: string},
     *     in-use: array{colour: string, icon: null, label: string},
     *     collection: array{colour: string, icon: null, label: string}
     * }
     */
    public function phaseDefinitions(): array
    {
        $colours = $this->phaseColours();

        return [
            'delivery' => [
                'colour' => $colours['delivery'],
                'icon' => null,
                'label' => __('Delivery'),
            ],
            'in-use' => [
                'colour' => $colours['in-use'],
                'icon' => null,
                'label' => __('In Use'),
            ],
            'collection' => [
                'colour' => $colours['collection'],
                'icon' => null,
                'label' => __('Collection'),
            ],
        ];
    }

    public function phaseColour(string $phase): string
    {
        return $this->phaseColours()[$phase] ?? $this->phaseColours()['in-use'];
    }

    public function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'delivery' => __('Delivery'),
            'collection' => __('Collection'),
            default => __('In Use'),
        };
    }

    /**
     * Customer Collecting / Returning badge colours — match the legend key.
     *
     * @return array{collecting: string, returning: string}
     */
    public function customerBadgeColours(): array
    {
        return [
            'collecting' => '#27ae60',
            'returning' => '#17a2b8',
        ];
    }

    /**
     * @return array{
     *     collecting: array{colour: string, icon: string, label: string},
     *     returning: array{colour: string, icon: string, label: string}
     * }
     */
    public function customerFlagDefinitions(): array
    {
        $colours = $this->customerBadgeColours();

        return [
            'collecting' => [
                'colour' => $colours['collecting'],
                'icon' => 'truck',
                'label' => __('Collecting'),
            ],
            'returning' => [
                'colour' => $colours['returning'],
                'icon' => 'truck',
                'label' => __('Returning'),
            ],
        ];
    }

    public function statusColour(OpportunityStatus $status): string
    {
        return $status->colour();
    }

    public function statusLabel(OpportunityStatus $status): string
    {
        return $status->label();
    }

    /**
     * Planner-visible opportunity statuses for the legend key (quotation + order axes).
     *
     * @return list<array{label: string, colour: string}>
     */
    public function statusLegendEntries(): array
    {
        $entries = [];

        foreach (OpportunityStatus::cases() as $status) {
            if ($status->state() === OpportunityState::Draft) {
                continue;
            }

            $entries[] = [
                'label' => $this->statusLabel($status),
                'colour' => $this->statusColour($status),
            ];
        }

        return $entries;
    }

    /**
     * Phase-only legend entries (Delivery / In-Use / Collection).
     *
     * @return list<array{label: string, colour: string, icon: null}>
     */
    public function phaseLegendEntries(): array
    {
        $entries = [];

        foreach ($this->phaseDefinitions() as $definition) {
            $entries[] = [
                'label' => $definition['label'],
                'colour' => $definition['colour'],
                'icon' => null,
            ];
        }

        return $entries;
    }

    /**
     * Static legend entries for the overlap timeline and shared opportunity keys.
     *
     * @return list<array{label: string, colour: string, icon: ?string, border?: bool, narrow?: bool}>
     */
    public function legend(): array
    {
        $colours = $this->phaseColours();
        $statusEntries = $this->statusLegendEntries();
        $flags = $this->customerFlagDefinitions();

        return array_merge(
            $statusEntries,
            [
                ['label' => $flags['collecting']['label'], 'colour' => $flags['collecting']['colour'], 'icon' => $flags['collecting']['icon']],
                ['label' => $flags['returning']['label'], 'colour' => $flags['returning']['colour'], 'icon' => $flags['returning']['icon']],
                ['label' => __('Delivery'), 'colour' => $colours['delivery'], 'icon' => null],
                ['label' => __('In Use'), 'colour' => $colours['in-use'], 'icon' => null],
                ['label' => __('Collection'), 'colour' => $colours['collection'], 'icon' => null],
                ['label' => __('Today'), 'colour' => '#ffc107', 'icon' => null, 'border' => true],
                ['label' => __('Extends beyond view'), 'colour' => 'var(--violet, #8b5cf6)', 'icon' => null, 'narrow' => true],
            ],
        );
    }

    /**
     * Resolve the planner phase key for one calendar day within an opportunity window.
     */
    public function phaseForDay(Opportunity $job, Carbon $dayStart, Carbon $dayEnd): string
    {
        $overlaps = function (?CarbonInterface $from, ?CarbonInterface $to) use ($dayStart, $dayEnd): bool {
            if ($from === null || $to === null) {
                return false;
            }

            return $from <= $dayEnd && $to >= $dayStart;
        };

        if ($overlaps($job->deliver_starts_at, $job->deliver_ends_at)) {
            return 'delivery';
        }

        if ($overlaps($job->collect_starts_at, $job->collect_ends_at)) {
            return 'collection';
        }

        if ($overlaps($job->show_starts_at, $job->show_ends_at)) {
            return 'in-use';
        }

        if ($job->show_starts_at !== null && $dayEnd < $job->show_starts_at
            && $overlaps($job->starts_at, $job->show_starts_at)) {
            return 'delivery';
        }

        if ($job->show_ends_at !== null && $dayStart > $job->show_ends_at
            && $overlaps($job->show_ends_at, $job->ends_at)) {
            return 'collection';
        }

        return 'in-use';
    }
}
