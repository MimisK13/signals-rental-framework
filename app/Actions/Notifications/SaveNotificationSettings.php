<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\SaveNotificationSettingItemData;
use App\Data\Notifications\SaveNotificationSettingsData;
use App\Enums\NotificationAudience;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Services\Notifications\NotificationSettingsCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveNotificationSettings
{
    public function __construct(
        private readonly NotificationSettingsCatalog $catalog,
    ) {}

    public function __invoke(SaveNotificationSettingsData $data): void
    {
        Gate::authorize('notifications.manage');

        foreach ($data->notification_settings as $item) {
            $this->saveItem($item);
        }
    }

    private function saveItem(SaveNotificationSettingItemData $item): void
    {
        $type = NotificationType::query()->whereKey($item->notification_type_id)->firstOrFail();

        if ($type->is_system && $item->is_enabled === false) {
            throw ValidationException::withMessages([
                'notification_settings' => ['System notifications cannot be disabled.'],
            ]);
        }

        $setting = $this->catalog->settingFor($type);

        if ($item->is_enabled !== null) {
            $setting->update(['is_enabled' => $item->is_enabled]);
        }

        if ($item->channels !== null) {
            $this->persistChannels($setting, $type, $this->catalog->normalizeChannels($item->channels), 'default');
        }

        if ($item->recipient_rules !== null) {
            $this->persistRecipientRules($setting, $type, $item->recipient_rules, 'default');
        }

        if ($item->audiences !== null && $type->audience === NotificationAudience::Both) {
            foreach (['internal', 'external'] as $audience) {
                $audienceData = $item->audiences[$audience] ?? null;

                if (! is_array($audienceData)) {
                    continue;
                }

                if (isset($audienceData['channels'])) {
                    $this->persistChannels(
                        $setting,
                        $type,
                        $this->catalog->normalizeChannels($audienceData['channels']),
                        $audience,
                    );
                }

                if (isset($audienceData['recipient_rules'])) {
                    $this->persistRecipientRules($setting, $type, $audienceData['recipient_rules'], $audience);
                }
            }
        }

        $setting->refresh();
    }

    /**
     * @param  list<string>  $channels
     */
    private function persistChannels(NotificationSetting $setting, NotificationType $type, array $channels, string $audience): void
    {
        $defaults = $this->catalog->normalizeChannels($type->default_channels);

        foreach ($channels as $channel) {
            if (! in_array($channel, $defaults, true)) {
                throw ValidationException::withMessages([
                    'channels' => ['Tenant settings can only narrow default channels, not add new ones.'],
                ]);
            }
        }

        if ($type->audience === NotificationAudience::Both && $audience !== 'default') {
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
     * @param  list<array<string, mixed>>  $rules
     */
    private function persistRecipientRules(NotificationSetting $setting, NotificationType $type, array $rules, string $audience): void
    {
        $sanitized = $this->catalog->sanitizeRecipientRules($rules);

        if ($type->audience === NotificationAudience::Both && $audience !== 'default') {
            $channelConfig = $setting->channel_config ?? [];
            $audiences = is_array($channelConfig['audiences'] ?? null) ? $channelConfig['audiences'] : [];
            $audiences[$audience] = array_merge(
                is_array($audiences[$audience] ?? null) ? $audiences[$audience] : [],
                ['recipient_rules' => $sanitized],
            );
            $channelConfig['audiences'] = $audiences;
            $setting->update(['channel_config' => $channelConfig]);

            return;
        }

        $setting->update(['recipient_rules' => $sanitized]);
    }
}
