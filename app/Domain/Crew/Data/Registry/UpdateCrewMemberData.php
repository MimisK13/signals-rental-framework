<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class UpdateCrewMemberData extends Data
{
    public function __construct(public string $name,
        public string $engagement_type = 'employee',
        public ?string $email = null,
        public ?string $phone = null,
        public string $home_timezone = 'UTC',
        public ?int $member_id = null,
        public ?int $user_id = null,
        public ?int $supplier_member_id = null,
        public ?string $job_title = null,
        public ?string $internal_notes = null,
        public ?string $starts_on = null,
        public ?string $ends_on = null, ) {}
}
