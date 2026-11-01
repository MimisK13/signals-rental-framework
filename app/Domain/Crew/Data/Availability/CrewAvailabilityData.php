<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Availability;

use App\Domain\Crew\Models\CrewAvailability;
use Spatie\LaravelData\Data;

final class CrewAvailabilityData extends Data
{
    public function __construct(public int $id, public int $crew_member_id, public string $status, public string $type, public string $starts_at, public string $ends_at, public string $timezone) {}

    public static function fromModel(CrewAvailability $model): self
    {
        return new self($model->id, $model->crew_member_id, $model->status->value, $model->type->value, $model->starts_at->toIso8601String(), $model->ends_at->toIso8601String(), $model->timezone);
    }
}
