<?php

namespace App\Services\Notifications;

use App\Enums\NotificationAudience;
use App\Models\NotificationSetting;
use App\Models\NotificationType;

/**
 * Reads effective tenant notification settings (P28 layer 2) for API/admin surfaces.
 */
class NotificationSettingsCatalog
{
    /**
     * @return list<string>
     */
    public function effectiveChannelsFor(NotificationType $type, ?NotificationSetting $setting, string $audience): array
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
    public function recipientRulesFor(NotificationType $type, ?NotificationSetting $setting, string $audience): array
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

    public function settingFor(NotificationType $type): NotificationSetting
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
     * @param  list<string>  $channels
     * @return list<string>
     */
    public function normalizeChannels(array $channels): array
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
    public function sanitizeRecipientRules(array $rules): array
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
}
