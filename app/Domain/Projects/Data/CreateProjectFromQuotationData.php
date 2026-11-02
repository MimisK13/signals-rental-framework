<?php

declare(strict_types=1);

namespace App\Domain\Projects\Data;

use Spatie\LaravelData\Data;

final class CreateProjectFromQuotationData extends Data
{
    public function __construct(
        public string $name,
        public int $member_id,
        public ?int $venue_id = null,
        public ?int $owned_by = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}
}
