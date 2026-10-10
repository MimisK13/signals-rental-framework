<?php

namespace App\Services\Planning;

use App\Models\Member;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityPresentation;
use App\Support\Formatter;
use Calendar\Planner\TimelineRowBuilder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class JobPlannerRowBuilder
{
    public function __construct(
        private readonly TimelineRowBuilder $timelineBuilder,
        private readonly OpportunityPresentation $presentation,
        private readonly Formatter $formatter,
    ) {}

    /**
     * @param  Collection<int, Opportunity>  $jobs
     * @param  list<Carbon>  $days
     * @return list<array<string, mixed>>
     */
    public function build(Collection $jobs, Carbon $windowStart, Carbon $windowEnd, array $days): array
    {
        $jobsById = $jobs->keyBy('id')->all();
        $rows = [];

        foreach ($jobs as $job) {
            $status = $job->statusEnum();
            $start = $job->starts_at;
            $end = $job->ends_at;

            $rows[] = [
                'id' => $job->id,
                'subject' => $job->subject,
                'number' => $job->number,
                'member' => $job->member?->name,
                'location_label' => data_get($job->venue, 'name')
                    ?? data_get($job->member, 'name')
                    ?? data_get($job->store, 'name'),
                'address' => $this->locationAddressFor($job),
                'colour' => $this->presentation->statusColour($status),
                'status_label' => $this->presentation->statusLabel($status),
                'state_label' => $job->state->label(),
                'tags' => (array) ($job->tag_list ?? []),
                'customer_collecting' => (bool) $job->customer_collecting,
                'customer_returning' => (bool) $job->customer_returning,
                'starts_at' => $start,
                'ends_at' => $end,
                'datetime_range' => $this->formatter->dateTime($start).' – '.$this->formatter->dateTime($end),
            ];
        }

        return $this->timelineBuilder->build(
            rows: $rows,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            days: $days,
            segmentBuilder: function (array $row, CarbonInterface $dayStart, CarbonInterface $dayEnd) use ($jobsById): array {
                $phase = $this->presentation->phaseForDay(
                    $jobsById[$row['id']],
                    Carbon::instance($dayStart),
                    Carbon::instance($dayEnd),
                );

                return [
                    'phase' => $phase,
                    'label' => $this->presentation->phaseLabel($phase),
                ];
            },
        );
    }

    public function cardGapPx(): int
    {
        return $this->timelineBuilder->cardGapPx();
    }

    /** Resolve the primary address, falling back to the location name. */
    private function locationAddressFor(Opportunity $job): string
    {
        $entity = $job->venue ?? $job->member;
        $fallback = data_get($job->venue, 'name')
            ?? data_get($job->member, 'name')
            ?? data_get($job->store, 'name')
            ?? '';

        if ($entity instanceof Member) {
            $address = $entity->addresses->firstWhere('is_primary', true)
                ?? $entity->addresses->first();

            if ($address !== null) {
                $line = collect([$address->street, $address->city, $address->postcode])
                    ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                    ->implode(', ');

                if ($line !== '') {
                    return $line;
                }
            }
        }

        return $fallback;
    }
}
