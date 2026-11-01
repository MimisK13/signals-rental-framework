<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use App\Domain\Crew\Models\CrewRequest;
use Spatie\LaravelData\Data;

final class CrewRequestData extends Data
{
    public function __construct(public int $id, public string $title, public int $project_id, public int $store_id, public string $status, public string $starts_at, public string $ends_at) {}

    public static function fromModel(CrewRequest $model): self
    {
        return new self($model->id, $model->title, $model->project_id, $model->store_id, $model->status->value, $model->starts_at->toIso8601String(), $model->ends_at->toIso8601String());
    }
}
