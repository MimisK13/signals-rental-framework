<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use App\Domain\Crew\Models\CrewTimesheet;
use Spatie\LaravelData\Data;

final class CrewTimesheetData extends Data
{
    /** @param list<CrewTimeEntryData> $entries */
    public function __construct(
        public int $id,
        public int $crew_assignment_id,
        public int $crew_member_id,
        public int $project_id,
        public int $store_id,
        public string $period_start,
        public string $period_end,
        public string $status,
        public int $total_minutes,
        public ?int $total_pay_minor,
        public string $currency_code,
        public int $version,
        public ?string $finance_reference,
        public array $entries = [],
    ) {}

    public static function fromModel(CrewTimesheet $sheet): self
    {
        $sheet->loadMissing('entries');

        return new self($sheet->id, $sheet->crew_assignment_id, $sheet->crew_member_id, $sheet->project_id, $sheet->store_id, $sheet->period_start->toDateString(), $sheet->period_end->toDateString(), $sheet->status->value, $sheet->total_minutes, auth()->user()?->can('crew.rates.view') ? $sheet->total_pay_minor : null, $sheet->currency_code, $sheet->version, $sheet->finance_reference, $sheet->entries->map(static function ($entry): CrewTimeEntryData {
            return CrewTimeEntryData::fromModel($entry);
        })->all());
    }
}
