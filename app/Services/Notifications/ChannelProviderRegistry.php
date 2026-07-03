<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelDriverData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Jobs\SendChannelNotification;
use App\Services\Plugins\PluginRegistrar;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Registry of notification channel delivery drivers (P23).
 *
 * Core registers email, database, and broadcast on boot; plugins register
 * additional drivers via {@see PluginRegistrar}.
 */
class ChannelProviderRegistry
{
    /**
     * @var array<string, class-string<ChannelDriver>>
     */
    private array $drivers = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<ChannelDriver>  $driverClass
     */
    public function register(string $key, string $driverClass): void
    {
        $this->drivers[$key] = $driverClass;
    }

    /**
     * Resolve a driver by key or by channel name.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(?string $driverKey = null, ?string $channel = null): ChannelDriver
    {
        if ($driverKey !== null) {
            return $this->resolveByKey($driverKey);
        }

        if ($channel !== null) {
            return $this->resolveByChannel($channel);
        }

        throw new InvalidArgumentException('Either driverKey or channel must be provided.');
    }

    /**
     * Self-test a channel driver.
     */
    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        $driverKey = $data->driverKey ?? $this->defaultDriverKeyForChannel($data->channel);
        $driver = $this->resolveByKey($driverKey);

        return $driver->test($data);
    }

    /**
     * Queue delivery through the named driver on the appropriate queue.
     */
    public function send(ChannelSendData $payload): void
    {
        $driver = $this->resolveByKey($payload->driverKey);
        $queue = $driver->channel() === 'mail' ? 'mail' : 'notifications';

        SendChannelNotification::dispatch($payload)->onQueue($queue);
    }

    /**
     * List registered drivers, optionally filtered by channel.
     *
     * @return list<ChannelDriverData>
     */
    public function drivers(?string $channel = null): array
    {
        $summaries = [];

        foreach ($this->drivers as $key => $driverClass) {
            $driver = $this->container->make($driverClass);

            if ($channel !== null && $driver->channel() !== $channel) {
                continue;
            }

            $summaries[] = ChannelDriverData::fromDriver($driver);
        }

        usort(
            $summaries,
            static fn (ChannelDriverData $a, ChannelDriverData $b): int => $a->key <=> $b->key,
        );

        return $summaries;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Distinct delivery channel names across all registered drivers.
     *
     * @return list<string>
     */
    public function channels(): array
    {
        $channels = [];

        foreach ($this->drivers as $driverClass) {
            $channels[] = $this->container->make($driverClass)->channel();
        }

        $channels = array_values(array_unique($channels));
        sort($channels);

        return $channels;
    }

    public function has(string $driverKey): bool
    {
        return isset($this->drivers[$driverKey]);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveByKey(string $driverKey): ChannelDriver
    {
        $driverClass = $this->drivers[$driverKey] ?? null;

        if ($driverClass === null) {
            throw new InvalidArgumentException("Unknown channel driver [{$driverKey}].");
        }

        return $this->container->make($driverClass);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveByChannel(string $channel): ChannelDriver
    {
        foreach ($this->drivers as $driverClass) {
            $driver = $this->container->make($driverClass);

            if ($driver->channel() === $channel) {
                return $driver;
            }
        }

        throw new InvalidArgumentException("No channel driver registered for channel [{$channel}].");
    }

    /**
     * @throws InvalidArgumentException
     */
    private function defaultDriverKeyForChannel(string $channel): string
    {
        foreach ($this->drivers as $key => $driverClass) {
            $driver = $this->container->make($driverClass);

            if ($driver->channel() === $channel) {
                return $key;
            }
        }

        throw new InvalidArgumentException("No channel driver registered for channel [{$channel}].");
    }
}
