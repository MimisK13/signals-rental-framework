<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Assignments;

use App\Domain\Crew\Models\CrewAssignment;
use Spatie\LaravelData\Data;

final class CrewAssignmentData extends Data
{
    public function __construct(public int $id, public int $crew_request_id, public int $crew_request_line_id, public int $crew_member_id, public int $project_id, public int $store_id, public string $status) {}

    public static function fromModel(CrewAssignment $model): self
    {
        return new self($model->id, $model->crew_request_id, $model->crew_request_line_id, $model->crew_member_id, $model->project_id, $model->store_id, $model->status->value);
    }
}
