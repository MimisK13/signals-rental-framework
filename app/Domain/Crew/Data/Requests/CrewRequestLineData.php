<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Requests;

use App\Domain\Crew\Models\CrewRequestLine;
use Spatie\LaravelData\Data;

final class CrewRequestLineData extends Data
{
    public function __construct(public int $id, public int $crew_request_id, public int $crew_role_id, public int $quantity, public int $filled_quantity, public string $starts_at, public string $ends_at) {}

    public static function fromModel(CrewRequestLine $model): self
    {
        return new self($model->id, $model->crew_request_id, $model->crew_role_id, $model->quantity, $model->filled_quantity, $model->effectiveStartsAt()->toIso8601String(), $model->effectiveEndsAt()->toIso8601String());
    }
}
