<?php

declare(strict_types=1);

namespace App\Domain\Communications\Data;

use Spatie\LaravelData\Data;

/**
 * Payload dispatched through a channel driver (P23 infrastructure seam).
 */
final class ChannelSendData extends Data
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $driverKey,
        public int $notifiableId,
        public string $notifiableType,
        public string $title,
        public string $body,
        public array $data = [],
        public array $config = [],
    ) {}
}
