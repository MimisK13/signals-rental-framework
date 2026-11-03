<?php

use App\Domain\Communications\Contracts\ChannelDriver;
use App\Domain\Communications\Data\ChannelConfigFieldData;
use App\Domain\Communications\Data\ChannelSendData;
use App\Domain\Communications\Data\ChannelTestResultData;
use App\Domain\Communications\Data\TestChannelProviderData;
use App\Domain\Communications\Models\ChannelProvider;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Notifications\Enums\NotificationAudience;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Presentation\Livewire\Admin\NotificationSettings;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Services\Plugins\PluginRegistrar;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->owner()->create();
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->admin()->create();
    $this->manager->givePermissionTo('notifications.manage');
    $this->viewer = User::factory()->create(['is_owner' => false]);
});

test('notifications page requires notifications.manage permission', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.notifications'))
        ->assertForbidden();
});

test('notifications page renders for users with notifications.manage', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.settings.notifications'))
        ->assertOk()
        ->assertSee('Channel Providers');
});

test('notifications page displays channel provider cards from registry', function () {
    $this->actingAs($this->manager)
        ->get(route('admin.settings.notifications'))
        ->assertOk()
        ->assertSee('In-App (Database)')
        ->assertSee('Email (Settings)')
        ->assertSee('Real-Time (Broadcast)');
});

test('notifications page displays notification types grouped by category', function () {
    NotificationType::factory()->create([
        'category' => 'Users',
        'name' => 'User Invited',
    ]);

    $this->actingAs($this->manager)
        ->get(route('admin.settings.notifications'))
        ->assertOk()
        ->assertSee('Users')
        ->assertSee('User Invited');
});

test('owner can toggle a channel for a notification type', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('toggleChannel', $type->id, 'mail');

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->channels)->not->toContain('mail');
    expect($setting->channels)->toContain('database');
});

test('tenant cannot enable channels outside type defaults', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database'],
    ]);

    Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('toggleChannel', $type->id, 'mail')
        ->assertHasErrors('channel');

    expect(NotificationSetting::where('notification_type_id', $type->id)->exists())->toBeFalse();
});

test('unauthorized user cannot access notifications page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.notifications'))
        ->assertForbidden();
});

test('unauthorized user cannot toggle channels via livewire', function () {
    $type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(NotificationSettings::class)
        ->assertForbidden();
});

test('unauthorized user cannot save recipient rules via livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(NotificationSettings::class)
        ->assertForbidden();
});

test('owner can save recipient rules for a notification type', function () {
    $type = NotificationType::factory()->create([
        'default_recipients' => [['type' => 'subject_owner']],
    ]);

    Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('openRecipientEditor', $type->id)
        ->set('editingRecipientRules', [
            ['type' => 'role', 'role' => 'admin'],
            ['type' => 'trigger_actor'],
        ])
        ->call('saveRecipientRules')
        ->assertHasNoErrors();

    $setting = NotificationSetting::where('notification_type_id', $type->id)->first();
    expect($setting->recipient_rules)->toHaveCount(2);
});

test('unauthorized user cannot open provider config via livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(NotificationSettings::class)
        ->assertForbidden();
});

test('provider config modal masks secret values and never returns cleartext on round trip', function () {
    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'smtp.example.com',
        'email.smtp_password' => 'super-secret-password',
        'email.from_address' => 'noreply@example.com',
    ]);

    $component = Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('openProviderConfig', 'email');

    expect($component->get('providerConfig')['smtp_password'] ?? '')
        ->not->toBe('super-secret-password')
        ->toContain('•');

    $component
        ->set('providerConfig.smtp_host', 'smtp.updated.example.com')
        ->call('saveProviderConfig')
        ->call('openProviderConfig', 'email');

    expect($component->get('providerConfig')['smtp_password'] ?? '')
        ->not->toBe('super-secret-password')
        ->toContain('•');

    expect(settings('email.smtp_password'))->toBe('super-secret-password');
    expect(settings('email.smtp_host'))->toBe('smtp.updated.example.com');
});

test('unauthorized user cannot save provider config via livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(NotificationSettings::class)
        ->assertForbidden();
});

test('manager can send a database channel provider test', function () {
    Livewire::actingAs($this->manager)
        ->test(NotificationSettings::class)
        ->call('sendProviderTest', 'database')
        ->assertSet('providerTestSuccess', true);
});

test('plugin provider test result is persisted on the channel provider record', function () {
    $provider = ChannelProvider::factory()->create([
        'channel' => 'fake_channel',
        'driver' => 'fake_plugin',
        'config' => ['api_key' => 'configured'],
    ]);

    app(PluginRegistrar::class)
        ->channelDriver('fake_plugin', AdminSettingsPluginChannelDriver::class);

    Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('openProviderConfig', 'fake_plugin')
        ->call('sendProviderTest', 'fake_plugin');

    $provider->refresh();

    expect($provider->last_test_status)->toBe('passed');
});

test('both-audience types store internal channel overrides separately', function () {
    $type = NotificationType::factory()->create([
        'audience' => NotificationAudience::Both,
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);

    Livewire::actingAs($this->owner)
        ->test(NotificationSettings::class)
        ->call('toggleChannel', $type->id, 'mail', 'internal');

    $setting = NotificationSetting::where('notification_type_id', $type->id)->firstOrFail();

    $channelConfig = $setting->channel_config ?? [];
    $audiences = is_array($channelConfig['audiences'] ?? null) ? $channelConfig['audiences'] : [];
    $internalConfig = is_array($audiences['internal'] ?? null) ? $audiences['internal'] : [];
    $internalChannels = is_array($internalConfig['channels'] ?? null) ? $internalConfig['channels'] : [];

    expect($internalChannels)->not->toContain('mail');
});

test('communication template editor link is shown for notification types with templates', function () {
    $type = NotificationType::factory()->create(['name' => 'Booking Confirmed']);
    $store = Store::factory()->create();
    $template = CommunicationTemplate::factory()->create([
        'notification_type_id' => $type->id,
        'store_id' => $store->id,
        'name' => 'Confirmation Email',
    ]);

    $this->actingAs($this->manager)
        ->get(route('admin.settings.notifications'))
        ->assertOk()
        ->assertSee('Confirmation Email')
        ->assertSee(route('admin.settings.communication-templates.edit', $template, absolute: false));
});

test('notification log and unsubscribe settings round-trip from admin page', function () {
    Livewire::actingAs($this->manager)
        ->test(NotificationSettings::class)
        ->set('communicationLogRetentionDays', 180)
        ->set('communicationLogPruneSafetyDays', 14)
        ->set('unsubscribeUrlTtlDays', 90)
        ->call('saveNotificationSettings')
        ->assertHasNoErrors()
        ->assertDispatched('notification-settings-saved');

    expect(settings('notifications.communication_log_retention_days'))->toBe(180)
        ->and(settings('notifications.communication_log_prune_safety_days'))->toBe(14)
        ->and(settings('notifications.unsubscribe_url_ttl_days'))->toBe(90);
});

class AdminSettingsPluginChannelDriver implements ChannelDriver
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
        return ChannelTestResultData::passed($this, 'ok');
    }
}
