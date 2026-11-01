<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class UpdateCrewRoleData extends Data
{
    public function __construct(public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public bool $is_supervisory = false,
        public bool $requires_certification = false,
        public int $sort_order = 0,
        public bool $is_active = true) {}
}
