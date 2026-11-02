<?php

declare(strict_types=1);

namespace App\Domain\Communications\Data;

use Spatie\LaravelData\Data;

/**
 * Describes one configurable field on a channel driver's config schema.
 */
final class ChannelConfigFieldData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required = false,
        public bool $secret = false,
    ) {}
}
