<?php

namespace App\Data\Notifications;

use App\Models\ChannelProvider;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ChannelProviderData extends Data
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public int $id,
        public string $channel,
        public string $driver,
        public string $name,
        public ?array $config,
        public bool $is_default,
        public bool $is_active,
        public ?string $last_test_status,
        public ?string $last_test_message,
        public ?string $last_tested_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ChannelProvider $provider): self
    {
        $registry = app(ChannelProviderRegistry::class);
        $configService = app(ChannelProviderConfigService::class);

        $config = null;

        if ($registry->has($provider->driver)) {
            $driver = $registry->resolve(driverKey: $provider->driver);
            $config = $configService->maskedConfig($driver, $provider);
        }

        /** @var Carbon $createdAt */
        $createdAt = $provider->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $provider->updated_at;

        return new self(
            id: $provider->id,
            channel: $provider->channel,
            driver: $provider->driver,
            name: $provider->name,
            config: $config,
            is_default: $provider->is_default,
            is_active: $provider->is_active,
            last_test_status: $provider->last_test_status,
            last_test_message: $provider->last_test_message,
            last_tested_at: $provider->last_tested_at?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
