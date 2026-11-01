<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use App\Domain\Crew\Models\CrewMemberQualification;
use Spatie\LaravelData\Data;

final class CrewMemberQualificationData extends Data
{
    public function __construct(public int $id, public int $crew_member_id, public int $crew_qualification_id, public ?string $issued_at, public ?string $expires_at, public ?string $verified_at, public string $status) {}

    public static function fromModel(CrewMemberQualification $record): self
    {
        return new self($record->id, $record->crew_member_id, $record->crew_qualification_id, $record->issued_at?->toDateString(), $record->expires_at?->toDateString(), $record->verified_at?->toIso8601String(), $record->status()->value);
    }
}
