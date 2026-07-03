<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

/**
 * Describes one configurable field on a channel driver's config schema.
 */
class ChannelConfigFieldData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required = false,
        public bool $secret = false,
    ) {}
}
