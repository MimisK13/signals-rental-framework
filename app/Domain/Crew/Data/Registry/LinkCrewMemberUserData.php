<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class LinkCrewMemberUserData extends Data
{
    public function __construct(public ?int $user_id) {}
}
