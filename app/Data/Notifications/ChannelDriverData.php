<?php

namespace App\Data\Notifications;

use App\Contracts\Notifications\ChannelDriver;
use App\Services\Notifications\ChannelConfigMasker;
use Spatie\LaravelData\Data;

/**
 * Serializable driver summary for API/settings surfaces (P23).
 *
 * Config values marked secret in the schema are masked before exposure.
 */
class ChannelDriverData extends Data
{
    /**
     * @param  list<ChannelConfigFieldData>  $configSchema
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $key,
        public string $channel,
        public string $label,
        public array $configSchema,
        public array $config,
    ) {}

    public static function fromDriver(ChannelDriver $driver): self
    {
        $schema = $driver->configSchema();
        $config = ChannelConfigMasker::mask($driver->resolveConfig(), $schema);

        return new self(
            key: $driver->key(),
            channel: $driver->channel(),
            label: $driver->label(),
            configSchema: $schema,
            config: $config,
        );
    }
}
