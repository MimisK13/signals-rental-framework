<?php

use App\Actions\Notifications\SaveChannelProvider;
use App\Actions\Notifications\TestChannelProvider;
use App\Data\Notifications\SaveChannelProviderData;
use App\Data\Notifications\TestChannelProviderData;
use App\Enums\NotificationAudience;
use App\Models\ChannelProvider;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\RecipientResolverRegistry;
use App\Services\SettingsRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] #[Title('Notifications')] class extends Component {
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
     *     grouped: \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, NotificationType>>,
     *     drivers: list<\App\Data\Notifications\ChannelDriverData>,
     *     providers: \Illuminate\Support\Collection<string, ChannelProvider>,
     *     recipientTypes: list<string>,
     *     roles: \Illuminate\Support\Collection<string, string>,
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
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Notifications" description="Configure channel providers, notification types, recipients, and communication templates.">
        <x-slot:actions>
            <a href="{{ route('admin.settings.communication-log') }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm">{{ __('Communication log') }}</a>
        </x-slot:actions>
        {{-- Channel Providers --}}
        <x-signals.card>
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Channel Providers</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Delivery drivers registered for this installation.</p>
                </div>
            </div>
            <div class="p-4">
                <div class="flex flex-wrap gap-3">
                    @foreach ($drivers as $driver)
                        @php
                            $status = $this->providerStatus($driver->key);
                        @endphp
                        <div wire:key="provider-{{ $driver->key }}" class="s-card p-4 min-w-[220px] flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $driver->label }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $channelLabels[$driver->channel] ?? ucfirst($driver->channel) }}</div>
                                </div>
                                @if ($status === 'passed')
                                    <span class="s-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Tested</span>
                                @elseif ($status === 'failed')
                                    <span class="s-badge bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Failing</span>
                                @else
                                    <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Untested</span>
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if (count($driver->configSchema) > 0 || $driver->key === 'email')
                                    <button type="button" wire:click="openProviderConfig('{{ $driver->key }}')" class="s-btn s-btn-ghost s-btn-sm" wire:loading.attr="disabled">
                                        Configure
                                    </button>
                                @endif
                                <button type="button" wire:click="sendProviderTest('{{ $driver->key }}')" class="s-btn s-btn-ghost s-btn-sm" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="sendProviderTest('{{ $driver->key }}')">Send test</span>
                                    <span wire:loading wire:target="sendProviderTest('{{ $driver->key }}')">Testing…</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-signals.card>

        {{-- Communication log & unsubscribe settings --}}
        <x-signals.card>
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Communication log &amp; unsubscribe</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Retention for delivery history and signed unsubscribe link lifetime.</p>
            </div>
            <form wire:submit="saveNotificationSettings" class="p-4 space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input
                        wire:model="communicationLogRetentionDays"
                        label="Log retention (days)"
                        type="number"
                        min="30"
                        max="3650"
                        description="Terminal delivery rows are removed after this many days."
                    />
                    <flux:input
                        wire:model="communicationLogPruneSafetyDays"
                        label="Prune safety window (days)"
                        type="number"
                        min="1"
                        max="90"
                        description="Extra days beyond retention before in-flight (non-terminal) rows are removed."
                    />
                    <flux:input
                        wire:model="unsubscribeUrlTtlDays"
                        label="Unsubscribe link lifetime (days)"
                        type="number"
                        min="1"
                        max="3650"
                        description="How long newly generated one-click unsubscribe links remain valid."
                    />
                </div>
                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">Save settings</flux:button>
                    <x-action-message on="notification-settings-saved">Saved.</x-action-message>
                </div>
            </form>
        </x-signals.card>

        {{-- Notification Types --}}
        @if ($grouped->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No notification types found. Run database seeders to create default notification types.</p>
            </div>
        @else
            @foreach ($grouped as $category => $types)
                <div wire:key="category-{{ $category }}" class="s-card mb-4">
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $category }}</h3>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($types as $type)
                            @php
                                $setting = $type->setting;
                                $isEnabled = ! $setting || $setting->is_enabled;
                                $audiences = $type->audience === \App\Enums\NotificationAudience::Both
                                    ? ['internal' => 'Internal', 'external' => 'External']
                                    : ['default' => ''];
                            @endphp
                            <div wire:key="type-{{ $type->id }}" class="p-4 space-y-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm">{{ $type->name }}</span>
                                            @if ($type->is_system)
                                                <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Required</span>
                                            @endif
                                            <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ $type->audience->value }}</span>
                                        </div>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $type->description }}</p>
                                    </div>
                                    <flux:switch
                                        wire:click="toggleEnabled({{ $type->id }})"
                                        :checked="$isEnabled"
                                        :disabled="$type->is_system"
                                    />
                                </div>

                                @foreach ($audiences as $audienceKey => $audienceLabel)
                                    @php
                                        $effectiveChannels = $this->effectiveChannelsForType($type, $setting, $audienceKey);
                                        $recipientRules = $this->recipientRulesForType($type, $setting, $audienceKey);
                                        $recipientSummary = collect($recipientRules)->pluck('type')->join(', ') ?: 'None';
                                    @endphp
                                    <div wire:key="type-{{ $type->id }}-{{ $audienceKey }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 space-y-3">
                                        @if ($audienceLabel !== '')
                                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $audienceLabel }}</div>
                                        @endif

                                        <div class="flex flex-wrap gap-4">
                                            @foreach ($type->available_channels as $channel)
                                                @php
                                                    $canEnable = in_array($channel, $type->default_channels, true);
                                                    $isOn = in_array($channel, $effectiveChannels, true);
                                                @endphp
                                                <div class="flex items-center gap-2" wire:key="channel-{{ $type->id }}-{{ $audienceKey }}-{{ $channel }}">
                                                    <flux:switch
                                                        wire:click="toggleChannel({{ $type->id }}, '{{ $channel }}', '{{ $audienceKey }}')"
                                                        :checked="$isOn"
                                                        :disabled="$type->is_system || ! $isEnabled || (! $isOn && ! $canEnable)"
                                                    />
                                                    <span class="text-sm {{ ! $canEnable && ! $isOn ? 'text-zinc-400' : '' }}">
                                                        {{ $channelLabels[$channel] ?? ucfirst($channel) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3 text-sm">
                                            <span class="text-zinc-500 dark:text-zinc-400">Recipients:</span>
                                            <span class="text-zinc-700 dark:text-zinc-300">{{ $recipientSummary }}</span>
                                            <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="openRecipientEditor({{ $type->id }}, '{{ $audienceKey }}')">
                                                Edit rules
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <span class="text-zinc-500 dark:text-zinc-400">Templates:</span>
                                            @forelse ($this->templatesForType($type, 'mail') as $template)
                                                <a href="{{ route('admin.settings.communication-templates.edit', $template) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" wire:key="tpl-{{ $template->id }}">
                                                    {{ $template->name }}
                                                </a>
                                            @empty
                                                <span class="text-zinc-400 text-xs">No templates</span>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Provider config slide-over --}}
        <flux:modal wire:model="showProviderModal" class="max-w-lg">
            <flux:heading size="lg">Configure provider</flux:heading>
            <div class="mt-4 space-y-4">
                <flux:input wire:model="providerName" label="Provider name" />

                @if ($editingDriverKey !== '')
                    @php
                        $editingDriver = collect($drivers)->firstWhere('key', $editingDriverKey);
                    @endphp
                    @if ($editingDriver)
                        @foreach ($editingDriver->configSchema as $field)
                            <div wire:key="cfg-{{ $field->key }}">
                                @if ($field->type === 'password')
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" type="password" />
                                @elseif ($field->type === 'integer')
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" type="number" />
                                @elseif ($field->type === 'select' && $field->key === 'mailer')
                                    <flux:select wire:model="providerConfig.{{ $field->key }}" :label="$field->label">
                                        <option value="log">Log (development)</option>
                                        <option value="smtp">SMTP</option>
                                        <option value="ses">Amazon SES</option>
                                        <option value="mailgun">Mailgun</option>
                                        <option value="postmark">Postmark</option>
                                    </flux:select>
                                @elseif ($field->type === 'select' && $field->key === 'smtp_encryption')
                                    <flux:select wire:model="providerConfig.{{ $field->key }}" :label="$field->label">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </flux:select>
                                @else
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" />
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endif

                <flux:input wire:model="testRecipient" label="Test recipient (email channels)" type="email" />

                @if ($providerTestMessage)
                    <div class="text-sm {{ $providerTestSuccess ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ $providerTestMessage }}
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showProviderModal', false)">Cancel</flux:button>
                    <flux:button variant="ghost" wire:click="sendProviderTest('{{ $editingDriverKey }}')" wire:loading.attr="disabled">Send test</flux:button>
                    <flux:button variant="primary" wire:click="saveProviderConfig" wire:loading.attr="disabled">Save</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Recipient rules editor --}}
        <flux:modal wire:model="showRecipientModal" class="max-w-xl">
            <flux:heading size="lg">Recipient rules</flux:heading>
            <div class="mt-4 space-y-4">
                @foreach ($editingRecipientRules as $index => $rule)
                    <div wire:key="rule-{{ $index }}" class="flex items-start gap-2 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex-1 space-y-2">
                            <div class="text-sm font-medium">{{ str_replace('_', ' ', ucfirst($rule['type'] ?? '')) }}</div>
                            @if (($rule['type'] ?? '') === 'role')
                                <flux:select wire:model="editingRecipientRules.{{ $index }}.role" label="Role">
                                    <option value="">Select role…</option>
                                    @foreach ($roles as $roleName => $label)
                                        <option value="{{ $roleName }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                            @endif
                            @if (($rule['type'] ?? '') === 'specific_member')
                                <flux:select wire:model="editingRecipientRules.{{ $index }}.member_id" label="Member">
                                    <option value="">Select member…</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </div>
                        <button type="button" class="s-btn s-btn-ghost s-btn-sm text-red-600" wire:click="removeRecipientRule({{ $index }})">Remove</button>
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2">
                    @foreach ($recipientTypes as $ruleType)
                        <button type="button" wire:key="add-{{ $ruleType }}" class="s-btn s-btn-ghost s-btn-sm" wire:click="addRecipientRule('{{ $ruleType }}')">
                            + {{ str_replace('_', ' ', $ruleType) }}
                        </button>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showRecipientModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveRecipientRules" wire:loading.attr="disabled">Save rules</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-admin.layout>
</section>
