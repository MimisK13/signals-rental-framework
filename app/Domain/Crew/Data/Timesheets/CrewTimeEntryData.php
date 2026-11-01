<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use App\Domain\Crew\Models\CrewTimeEntry;
use Spatie\LaravelData\Data;

final class CrewTimeEntryData extends Data
{
    public function __construct(
        public int $id,
        public int $crew_timesheet_id,
        public ?int $crew_shift_id,
        public string $kind,
        public string $work_date,
        public string $started_at,
        public string $ended_at,
        public int $break_minutes,
        public int $minutes,
        public ?int $amount_minor,
        public ?string $notes,
    ) {}

    public static function fromModel(CrewTimeEntry $entry): self
    {
        return new self($entry->id, $entry->crew_timesheet_id, $entry->crew_shift_id, $entry->kind->value, $entry->work_date->toDateString(), $entry->started_at->toIso8601String(), $entry->ended_at->toIso8601String(), $entry->break_minutes, $entry->minutes, auth()->user()?->can('crew.rates.view') ? $entry->amount_minor : null, $entry->notes);
    }
}
