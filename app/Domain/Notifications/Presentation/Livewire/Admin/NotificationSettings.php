<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Presentation\Livewire\Admin;

use App\Domain\Communications\Actions\SaveChannelProvider;
use App\Domain\Communications\Actions\TestChannelProvider;
use App\Domain\Communications\Data\ChannelDriverData;
use App\Domain\Communications\Data\SaveChannelProviderData;
use App\Domain\Communications\Data\TestChannelProviderData;
use App\Domain\Communications\Models\ChannelProvider;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\ChannelProviderConfigService;
use App\Domain\Communications\Services\ChannelProviderRegistry;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Enums\NotificationAudience;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Services\RecipientResolverRegistry;
use App\Services\SettingsRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')] #[Title('Notifications')] final class NotificationSettings extends Component
{
    public bool $showProviderModal = false;

    public string $editingDriverKey = '';

    public string $providerName = '';

    /** @var array<string, mixed> */
    public array $providerConfig = [];

    public string $testRecipient = '';

    public ?string $providerTestMessage = null;

    public bool $providerTestSuccess = false;

    public bool $showRecipientModal = false;

    public ?int $editingTypeId = null;

    public string $editingAudience = 'internal';

    /** @var list<array<string, mixed>> */
    public array $editingRecipientRules = [];

    public ?int $editingRoleMemberId = null;

    public int $communicationLogRetentionDays = 365;

    public int $communicationLogPruneSafetyDays = 7;

    public int $unsubscribeUrlTtlDays = 365;

    public function mount(): void
    {
        Gate::authorize('notifications.manage');

        $group = settings()->group('notifications');

        $this->communicationLogRetentionDays = (int) $group['communication_log_retention_days'];
        $this->communicationLogPruneSafetyDays = (int) $group['communication_log_prune_safety_days'];
        $this->unsubscribeUrlTtlDays = (int) $group['unsubscribe_url_ttl_days'];
    }

    public function saveNotificationSettings(): void
    {
        Gate::authorize('notifications.manage');

        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('notifications');
        $types = $registry->types('notifications');

        $validated = $this->validate([
            'communicationLogRetentionDays' => $rules['communication_log_retention_days'],
            'communicationLogPruneSafetyDays' => $rules['communication_log_prune_safety_days'],
            'unsubscribeUrlTtlDays' => $rules['unsubscribe_url_ttl_days'],
        ]);

        settings()->setMany([
            'notifications.communication_log_retention_days' => [
                'value' => $validated['communicationLogRetentionDays'],
                'type' => $types['communication_log_retention_days'],
            ],
            'notifications.communication_log_prune_safety_days' => [
                'value' => $validated['communicationLogPruneSafetyDays'],
                'type' => $types['communication_log_prune_safety_days'],
            ],
            'notifications.unsubscribe_url_ttl_days' => [
                'value' => $validated['unsubscribeUrlTtlDays'],
                'type' => $types['unsubscribe_url_ttl_days'],
            ],
        ]);

        $this->dispatch('notification-settings-saved');
    }

    public function openProviderConfig(string $driverKey): void
    {
        Gate::authorize('notifications.manage');

        $registry = app(ChannelProviderRegistry::class);
        $driver = $registry->resolve(driverKey: $driverKey);
        $configService = app(ChannelProviderConfigService::class);
        $provider = $this->providerRecordForDriver($driverKey);

        $this->editingDriverKey = $driverKey;
        $this->providerName = $provider !== null ? $provider->name : $driver->label();
        $this->providerConfig = $configService->maskedConfig($driver, $provider);
        $this->testRecipient = '';
        $this->providerTestMessage = null;
        $this->showProviderModal = true;
    }

    public function saveProviderConfig(): void
    {
        Gate::authorize('notifications.manage');

        $registry = app(ChannelProviderRegistry::class);
        $driver = $registry->resolve(driverKey: $this->editingDriverKey);
        $provider = $this->providerRecordForDriver($this->editingDriverKey);

        (app(SaveChannelProvider::class))($provider, SaveChannelProviderData::from([
            'channel' => $driver->channel(),
            'driver' => $driver->key(),
            'name' => $this->providerName,
            'config' => $this->providerConfig,
        ]));

        $this->providerConfig = app(ChannelProviderConfigService::class)->maskedConfig($driver, $provider?->fresh());
        $this->dispatch('notification-provider-saved');
    }

    public function sendProviderTest(string $driverKey): void
    {
        Gate::authorize('notifications.manage');

        $registry = app(ChannelProviderRegistry::class);
        $driver = $registry->resolve(driverKey: $driverKey);
        $provider = $this->providerRecordForDriver($driverKey);
        $configService = app(ChannelProviderConfigService::class);
        $config = $configService->mergeConfig($driver, $this->providerConfig, $provider);

        if ($this->showProviderModal && $this->editingDriverKey === $driverKey) {
            $this->validate([
                'testRecipient' => $driver->channel() === 'mail' ? ['required', 'email'] : ['nullable', 'email'],
            ]);
        }

        $result = app(TestChannelProvider::class)(new TestChannelProviderData(
            channel: $driver->channel(),
            driverKey: $driverKey,
            recipient: $this->testRecipient !== '' ? $this->testRecipient : null,
            config: $config,
        ));

        $this->providerTestSuccess = $result->success;
        $this->providerTestMessage = $result->message;

        if ($provider !== null && $provider->exists) {
            $provider->update([
                'last_test_status' => $result->success ? 'passed' : 'failed',
                'last_test_message' => $result->message,
                'last_tested_at' => now(),
            ]);
        }
    }

    public function toggleChannel(int $typeId, string $channel, string $audience = 'default'): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail($typeId);

        if ($type->is_system) {
            $this->addError('system', 'System notifications cannot be disabled.');

            return;
        }

        $effective = $this->effectiveChannelsFor($type, $type->setting, $audience);
        $defaults = $this->normalizeChannels($type->default_channels);

        if (in_array($channel, $effective, true)) {
            $channels = array_values(array_diff($effective, [$channel]));
        } else {
            if (! in_array($channel, $defaults, true)) {
                $this->addError('channel', 'Tenant settings can only narrow default channels, not add new ones.');

                return;
            }

            $channels = array_values(array_unique([...$effective, $channel]));
        }

        $setting = $this->settingFor($type);
        $this->persistChannels($setting, $type, $channels, $audience);
        $this->dispatch('$refresh');
    }

    public function toggleEnabled(int $typeId): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail($typeId);

        if ($type->is_system) {
            $this->addError('system', 'System notifications cannot be disabled.');

            return;
        }

        $setting = $this->settingFor($type);
        $setting->update(['is_enabled' => ! $setting->is_enabled]);

        $this->dispatch('$refresh');
    }

    public function openRecipientEditor(int $typeId, string $audience = 'default'): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail($typeId);
        $setting = $type->setting;

        $this->editingTypeId = $typeId;
        $this->editingAudience = $audience;
        $this->editingRecipientRules = $this->recipientRulesFor($type, $setting, $audience);
        $this->showRecipientModal = true;
    }

    public function addRecipientRule(string $ruleType): void
    {
        Gate::authorize('notifications.manage');

        $rule = ['type' => $ruleType];

        if ($ruleType === 'role') {
            $rule['role'] = '';
        }

        if ($ruleType === 'specific_member') {
            $rule['member_id'] = null;
        }

        $this->editingRecipientRules[] = $rule;
    }

    public function removeRecipientRule(int $index): void
    {
        Gate::authorize('notifications.manage');

        unset($this->editingRecipientRules[$index]);
        $this->editingRecipientRules = array_values($this->editingRecipientRules);
    }

    public function saveRecipientRules(): void
    {
        Gate::authorize('notifications.manage');

        $type = NotificationType::findOrFail((int) $this->editingTypeId);
        $setting = $this->settingFor($type);
        $rules = $this->sanitizeRecipientRules($this->editingRecipientRules);

        if ($this->usesSplitAudience($type, $this->editingAudience)) {
            $channelConfig = $setting->channel_config ?? [];
            $audiences = is_array($channelConfig['audiences'] ?? null) ? $channelConfig['audiences'] : [];
            $audiences[$this->editingAudience] = array_merge(
                is_array($audiences[$this->editingAudience] ?? null) ? $audiences[$this->editingAudience] : [],
                ['recipient_rules' => $rules],
            );
            $channelConfig['audiences'] = $audiences;
            $setting->update(['channel_config' => $channelConfig]);
        } else {
            $setting->update(['recipient_rules' => $rules]);
        }

        $this->showRecipientModal = false;
        $this->dispatch('$refresh');
    }

    /**
     * @return array{
     *     grouped: Collection<string, \Illuminate\Database\Eloquent\Collection<int, NotificationType>>,
     *     drivers: list<ChannelDriverData>,
     *     providers: Collection<string, ChannelProvider>,
     *     recipientTypes: list<string>,
     *     roles: Collection<string, string>,
     *     members: \Illuminate\Database\Eloquent\Collection<int, Member>,
     *     channelLabels: array<string, string>
     * }
     */
    public function with(): array
    {
        $types = NotificationType::query()
            ->with('setting')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $registry = app(ChannelProviderRegistry::class);
        $drivers = $registry->drivers();

        $providers = ChannelProvider::query()
            ->orderBy('channel')
            ->orderBy('name')
            ->get()
            ->keyBy('driver');

        return [
            'grouped' => $types->groupBy('category'),
            'drivers' => $drivers,
            'providers' => $providers,
            'recipientTypes' => app(RecipientResolverRegistry::class)->types(),
            'roles' => Role::query()->orderBy('name')->pluck('name', 'name'),
            'members' => Member::query()->orderBy('name')->limit(100)->get(['id', 'name']),
            'channelLabels' => [
                'database' => 'In-App',
                'broadcast' => 'Real-Time',
                'mail' => 'Email',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function effectiveChannelsForType(NotificationType $type, ?NotificationSetting $setting, string $audience = 'default'): array
    {
        return $this->effectiveChannelsFor($type, $setting, $audience);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recipientRulesForType(NotificationType $type, ?NotificationSetting $setting, string $audience = 'default'): array
    {
        return $this->recipientRulesFor($type, $setting, $audience);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CommunicationTemplate>
     */
    public function templatesForType(NotificationType $type, string $channel): \Illuminate\Database\Eloquent\Collection
    {
        $normalized = match ($channel) {
            'mail' => 'email',
            'database', 'broadcast' => 'email',
            default => $channel,
        };

        return CommunicationTemplate::query()
            ->where('notification_type_id', $type->id)
            ->where('channel', $normalized)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function providerStatus(string $driverKey): ?string
    {
        return ChannelProvider::query()->where('driver', $driverKey)->value('last_test_status');
    }

    private function providerRecordForDriver(string $driverKey): ?ChannelProvider
    {
        return ChannelProvider::query()->where('driver', $driverKey)->first();
    }

    private function usesSplitAudience(NotificationType $type, string $audience): bool
    {
        return $type->audience === NotificationAudience::Both && $audience !== 'default';
    }

    /**
     * @return array<string, mixed>
     */
    private function audienceConfig(?NotificationSetting $setting, string $audience): array
    {
        if ($setting === null || $setting->channel_config === null) {
            return [];
        }

        $audiences = $setting->channel_config['audiences'] ?? null;

        if (! is_array($audiences)) {
            return [];
        }

        $config = $audiences[$audience] ?? null;

        return is_array($config) ? $config : [];
    }

    private function settingFor(NotificationType $type): NotificationSetting
    {
        return NotificationSetting::firstOrCreate(
            ['notification_type_id' => $type->id],
            [
                'channels' => $type->default_channels,
                'is_enabled' => true,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function effectiveChannelsFor(NotificationType $type, ?NotificationSetting $setting, string $audience): array
    {
        if ($this->usesSplitAudience($type, $audience)) {
            $audienceConfig = $this->audienceConfig($setting, $audience);
            $channels = $audienceConfig['channels'] ?? null;

            if (is_array($channels)) {
                return $this->normalizeChannels($channels);
            }

            return $this->normalizeChannels($type->default_channels);
        }

        if ($setting && $setting->channels !== null) {
            return $this->normalizeChannels($setting->channels);
        }

        return $this->normalizeChannels($type->default_channels);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipientRulesFor(NotificationType $type, ?NotificationSetting $setting, string $audience): array
    {
        if ($this->usesSplitAudience($type, $audience)) {
            $rules = $this->audienceConfig($setting, $audience)['recipient_rules'] ?? null;

            if (is_array($rules)) {
                return $rules;
            }

            return $this->defaultRulesForAudience($type, $audience);
        }

        if ($setting?->recipient_rules !== null) {
            return $setting->recipient_rules;
        }

        return $type->default_recipients ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultRulesForAudience(NotificationType $type, string $audience): array
    {
        $defaults = $type->default_recipients ?? [];

        if ($audience === 'internal') {
            return array_values(array_filter(
                $defaults,
                static fn (array $rule): bool => ($rule['type'] ?? '') !== 'contact',
            ));
        }

        return array_values(array_filter(
            $defaults,
            static fn (array $rule): bool => ($rule['type'] ?? '') === 'contact',
        ));
    }

    /**
     * @param  list<string>  $channels
     */
    private function persistChannels(NotificationSetting $setting, NotificationType $type, array $channels, string $audience): void
    {
        if ($this->usesSplitAudience($type, $audience)) {
            $channelConfig = $setting->channel_config ?? [];
            $audiences = is_array($channelConfig['audiences'] ?? null) ? $channelConfig['audiences'] : [];
            $audiences[$audience] = array_merge(
                is_array($audiences[$audience] ?? null) ? $audiences[$audience] : [],
                ['channels' => $channels],
            );
            $channelConfig['audiences'] = $audiences;
            $setting->update(['channel_config' => $channelConfig]);

            return;
        }

        $setting->update(['channels' => $channels]);
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function normalizeChannels(array $channels): array
    {
        $normalized = [];

        foreach ($channels as $channel) {
            $normalized[] = match ($channel) {
                'in_app' => 'database',
                'email' => 'mail',
                default => $channel,
            };
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function sanitizeRecipientRules(array $rules): array
    {
        $sanitized = [];

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? null;

            if (! is_string($type) || $type === '') {
                continue;
            }

            if ($type === 'role' && empty($rule['role'])) {
                continue;
            }

            if ($type === 'specific_member' && empty($rule['member_id'])) {
                continue;
            }

            $sanitized[] = $rule;
        }

        return $sanitized;
    }

    public function render(): View
    {
        return view('notifications::admin.settings', $this->with());
    }
}
