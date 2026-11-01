<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use App\Domain\Crew\Models\CrewQualification;
use Spatie\LaravelData\Data;

final class CrewQualificationData extends Data
{
    public function __construct(public int $id, public string $name, public ?string $slug, public ?string $issuer, public ?string $description, public ?int $validity_months, public bool $requires_expiry, public bool $is_mandatory, public bool $is_active) {}

    public static function fromModel(CrewQualification $record): self
    {
        return new self($record->id, $record->name, $record->slug, $record->issuer, $record->description, $record->validity_months, $record->requires_expiry, $record->is_mandatory, $record->is_active);
    }
}
