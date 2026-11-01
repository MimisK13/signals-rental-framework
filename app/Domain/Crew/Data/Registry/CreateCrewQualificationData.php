<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class CreateCrewQualificationData extends Data
{
    public function __construct(public string $name,
        public ?string $slug = null,
        public ?string $issuer = null,
        public ?string $description = null,
        public ?int $validity_months = null,
        public bool $requires_expiry = true,
        public bool $is_mandatory = false,
        public bool $is_active = true) {}
}
