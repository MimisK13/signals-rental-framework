<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use App\Domain\Crew\Models\CrewMember;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;

final class CrewMemberData extends Data
{
    public function __construct(public int $id, public string $name, public string $engagement_type, public string $status, public ?string $email, public ?string $phone, public string $home_timezone, public ?string $job_title, public ?string $internal_notes, public ?int $member_id, public ?int $user_id, public ?int $supplier_member_id, public ?string $starts_on, public ?string $ends_on) {}

    public static function fromModel(CrewMember $member): self
    {
        $sensitive = Gate::allows('viewSensitive', $member);

        return new self($member->id, $member->name, $member->engagement_type->value, $member->status->value,
            $sensitive ? $member->email : null, $sensitive ? $member->phone : null,
            $member->home_timezone, $member->job_title, $sensitive ? $member->internal_notes : null,
            $member->member_id, $member->user_id, $member->supplier_member_id,
            $member->starts_on?->toDateString(), $member->ends_on?->toDateString());
    }
}
