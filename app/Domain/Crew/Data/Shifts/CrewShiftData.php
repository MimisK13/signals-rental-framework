<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Shifts;

use App\Domain\Crew\Models\CrewShift;
use Spatie\LaravelData\Data;

final class CrewShiftData extends Data
{
    public function __construct(public int $id, public ?int $crew_assignment_id, public ?int $crew_member_id, public int $project_id, public int $store_id, public string $status, public string $type, public string $starts_at, public string $ends_at, public string $timezone, public int $break_minutes, public bool $is_overnight) {}

    public static function fromModel(CrewShift $model): self
    {
        return new self($model->id, $model->crew_assignment_id, $model->crew_member_id, $model->project_id, $model->store_id, $model->status->value, $model->type->value, $model->starts_at->toIso8601String(), $model->ends_at->toIso8601String(), $model->timezone, $model->break_minutes, $model->is_overnight);
    }
}
