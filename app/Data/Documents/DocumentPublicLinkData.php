<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Data;

class DocumentPublicLinkData extends Data
{
    public function __construct(
        public bool $is_active,
        public ?string $public_url,
        public ?string $public_expires_at,
        public bool $is_pin_protected,
    ) {}
}
