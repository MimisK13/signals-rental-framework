<?php

namespace App\Data\Notifications;

use App\Contracts\Notifications\ChannelDriver;
use Spatie\LaravelData\Data;

/**
 * Outcome of a channel driver self-test.
 */
class ChannelTestResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public string $driverKey,
        public string $channel,
    ) {}

    public static function passed(ChannelDriver $driver, string $message): self
    {
        return new self(
            success: true,
            message: $message,
            driverKey: $driver->key(),
            channel: $driver->channel(),
        );
    }

    public static function failed(ChannelDriver $driver, string $message): self
    {
        return new self(
            success: false,
            message: $message,
            driverKey: $driver->key(),
            channel: $driver->channel(),
        );
    }
}
