<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\SaveChannelProviderData;
use App\Models\ChannelProvider;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveChannelProvider
{
    public function __construct(
        private readonly ChannelProviderRegistry $registry,
        private readonly ChannelProviderConfigService $configService,
    ) {}

    public function __invoke(?ChannelProvider $provider, SaveChannelProviderData $data): ChannelProvider
    {
        Gate::authorize('notifications.manage');

        $driver = $this->registry->resolve(driverKey: $data->driver);
        $config = $this->configService->mergeConfig($driver, $data->config, $provider);

        if ($driver->key() === 'email') {
            $this->configService->persist($driver, $config);

            return $this->emailProviderRecord($data);
        }

        if ($driver->configSchema() === []) {
            abort(422, 'This channel driver does not support persisted configuration.');
        }

        return DB::transaction(function () use ($provider, $data, $config): ChannelProvider {
            if ($data->is_default) {
                ChannelProvider::query()
                    ->where('channel', $data->channel)
                    ->when($provider !== null, fn ($query) => $query->whereKeyNot($provider->id))
                    ->update(['is_default' => false]);
            }

            if ($provider === null) {
                return ChannelProvider::query()->create([
                    'channel' => $data->channel,
                    'driver' => $data->driver,
                    'name' => $data->name,
                    'config' => $config,
                    'is_default' => $data->is_default,
                    'is_active' => $data->is_active,
                ]);
            }

            $provider->update([
                'name' => $data->name,
                'config' => $config,
                'is_default' => $data->is_default,
                'is_active' => $data->is_active,
            ]);

            return $provider->refresh();
        });
    }

    private function emailProviderRecord(SaveChannelProviderData $data): ChannelProvider
    {
        return new ChannelProvider([
            'id' => 0,
            'channel' => $data->channel,
            'driver' => $data->driver,
            'name' => $data->name,
            'config' => null,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
