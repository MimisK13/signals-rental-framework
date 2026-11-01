<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class AddCrewMemberQualificationData extends Data
{
    public function __construct(public int $crew_qualification_id, public ?string $issued_at = null, public ?string $expires_at = null, public ?string $reference_number = null, public ?string $notes = null) {}
}
