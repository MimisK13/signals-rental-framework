<?php

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Models\ChannelProvider;
use App\Models\User;
use App\Services\Notifications\ChannelConfigMasker;
use App\Services\Plugins\PluginRegistrar;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    app(PluginRegistrar::class)->channelDriver('fake_plugin', ApiTestChannelDriver::class);

    $this->owner = User::factory()->owner()->create();
    $this->owner->givePermissionTo('notifications.manage');
});

/**
 * @param  list<string>  $abilities
 */
function channelProviderApiToken(User $user, array $abilities = ['channel-providers:read', 'channel-providers:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

class ApiTestChannelDriver implements ChannelDriver
{
    public function key(): string
    {
        return 'fake_plugin';
    }

    public function channel(): string
    {
        return 'fake_channel';
    }

    public function label(): string
    {
        return 'Fake Plugin Channel';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [
            new ChannelConfigFieldData('api_key', 'API Key', 'password', required: true, secret: true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return ['api_key' => 'configured'];
    }

    public function send(ChannelSendData $payload): void {}

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        return ChannelTestResultData::passed($this, 'Fake plugin channel test succeeded.');
    }
}

describe('GET /api/v1/channel_providers', function () {
    it('lists channel providers with ransack eq filter', function () {
        ChannelProvider::factory()->create(['driver' => 'fake_plugin', 'channel' => 'fake_channel']);
        ChannelProvider::factory()->create(['driver' => 'other_driver', 'channel' => 'other_channel']);

        $token = channelProviderApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/channel_providers?q[driver_eq]=fake_plugin')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('channel_providers.0.driver', 'fake_plugin');
    });

    it('requires channel-providers:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/channel_providers')
            ->assertForbidden();
    });

    it('never exposes secret config values in list responses', function () {
        $secret = 'super-secret-api-key-value';
        ChannelProvider::factory()->create([
            'driver' => 'fake_plugin',
            'channel' => 'fake_channel',
            'config' => ['api_key' => $secret],
        ]);

        $token = channelProviderApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/channel_providers')
            ->assertOk();

        expect(json_encode($response->json()))->not->toContain($secret)
            ->and($response->json('channel_providers.0.config.api_key'))->toContain('•');
    });
});

describe('channel provider CRUD and test', function () {
    it('creates, shows, updates with masked round-trip, tests, and deletes providers', function () {
        $token = channelProviderApiToken($this->owner);
        $secret = 'create-time-secret-key';

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/channel_providers', [
                'channel' => 'fake_channel',
                'driver' => 'fake_plugin',
                'name' => 'Fake Provider',
                'config' => ['api_key' => $secret],
            ])
            ->assertCreated();

        $providerId = $create->json('channel_provider.id');

        expect($create->json('channel_provider.config.api_key'))->not->toBe($secret)
            ->and($create->json('channel_provider.config.api_key'))->toContain('•');

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/channel_providers/{$providerId}")
            ->assertOk();

        expect(json_encode($show->json()))->not->toContain($secret);

        $masked = ChannelConfigMasker::maskValue($secret);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/channel_providers/{$providerId}", [
                'channel' => 'fake_channel',
                'driver' => 'fake_plugin',
                'name' => 'Fake Provider Updated',
                'config' => [
                    'api_key' => $masked,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('channel_provider.name', 'Fake Provider Updated');

        expect(ChannelProvider::find($providerId)?->config['api_key'])->toBe($secret);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/channel_providers/{$providerId}/test")
            ->assertOk()
            ->assertJsonPath('channel_test_result.success', true);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/channel_providers/{$providerId}")
            ->assertNoContent();

        expect(ChannelProvider::find($providerId))->toBeNull();
    });

    it('requires channel-providers:write ability for mutations', function () {
        $provider = ChannelProvider::factory()->create([
            'driver' => 'fake_plugin',
            'channel' => 'fake_channel',
        ]);
        $token = $this->owner->createToken('test', ['channel-providers:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/channel_providers', [
                'channel' => 'fake_channel',
                'driver' => 'fake_plugin',
                'name' => 'Blocked',
                'config' => ['api_key' => 'x'],
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/channel_providers/{$provider->id}", [
                'channel' => 'fake_channel',
                'driver' => 'fake_plugin',
                'name' => 'Blocked',
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/channel_providers/{$provider->id}/test")
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/channel_providers/{$provider->id}")
            ->assertForbidden();
    });
});
