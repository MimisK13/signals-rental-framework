<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use App\Domain\Crew\Models\CrewSkill;
use Spatie\LaravelData\Data;

final class CrewSkillData extends Data
{
    public function __construct(public int $id, public string $name, public ?string $slug, public ?string $category, public ?string $description, public bool $is_active) {}

    public static function fromModel(CrewSkill $record): self
    {
        return new self($record->id, $record->name, $record->slug, $record->category, $record->description, $record->is_active);
    }
}
