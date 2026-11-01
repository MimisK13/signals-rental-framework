<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use App\Domain\Crew\Models\CrewRole;
use Spatie\LaravelData\Data;

final class CrewRoleData extends Data
{
    public function __construct(public int $id, public string $name, public ?string $slug, public ?string $description, public bool $is_supervisory, public bool $requires_certification, public int $sort_order, public bool $is_active) {}

    public static function fromModel(CrewRole $record): self
    {
        return new self($record->id, $record->name, $record->slug, $record->description, $record->is_supervisory, $record->requires_certification, $record->sort_order, $record->is_active);
    }
}
