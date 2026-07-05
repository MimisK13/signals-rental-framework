<?php

namespace App\Services\Notifications;

use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;

/**
 * Three-layer channel narrowing for notification delivery (P24).
 *
 * Layer 1: notification type defaults.
 * Layer 2: tenant notification settings.
 * Layer 3: member (user) notification preferences.
 *
 * Each layer can only remove channels, never add. Missing layer data means no
 * narrowing at that layer. The logical {@code in_app} channel couples database
 * and broadcast when expanded to Laravel channel names.
 */
class NotificationChannelResolver
{
    /**
     * Resolve the final logical channel list for a recipient.
     *
     * @return list<string> Canonical channel keys: in_app, email, sms, slack, teams
     */
    public function resolve(NotificationType $type, ?Member $recipient = null): array
    {
        $type->loadMissing('setting');

        $channels = $this->normalizeChannels($type->default_channels ?? []);

        $tenantChannels = $this->tenantChannels($type);

        if ($tenantChannels !== null) {
            $channels = $this->intersect($channels, $tenantChannels);
        }

        if ($recipient !== null) {
            $memberChannels = $this->memberChannels($type, $recipient);

            if ($memberChannels !== null) {
                $channels = $this->intersect($channels, $memberChannels);
            }
        }

        return $channels;
    }

    /**
     * Expand logical channels to Laravel notification channel names.
     *
     * @param  list<string>  $logicalChannels
     * @return list<string>
     */
    public function toLaravelChannels(array $logicalChannels): array
    {
        $laravel = [];

        foreach ($logicalChannels as $channel) {
            if ($channel === 'in_app') {
                $laravel[] = 'database';
                $laravel[] = 'broadcast';

                continue;
            }

            $laravel[] = match ($channel) {
                'email' => 'mail',
                default => $channel,
            };
        }

        return array_values(array_unique($laravel));
    }

    /**
     * Map a logical channel to the communication_log channel column value.
     */
    public function toLogChannel(string $logicalChannel): string
    {
        return match ($logicalChannel) {
            'email' => 'email',
            'in_app' => 'in_app',
            default => $logicalChannel,
        };
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function normalizeChannels(array $channels): array
    {
        $normalized = [];

        foreach ($channels as $channel) {
            $normalized[] = $this->normalizeChannel($channel);
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeChannel(string $channel): string
    {
        return match ($channel) {
            'database', 'broadcast' => 'in_app',
            'mail' => 'email',
            default => $channel,
        };
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $layer
     * @return list<string>
     */
    private function intersect(array $current, array $layer): array
    {
        $layerLookup = array_fill_keys($layer, true);

        return array_values(array_filter(
            $current,
            static fn (string $channel): bool => isset($layerLookup[$channel]),
        ));
    }

    /**
     * @return list<string>|null Null when layer 2 does not narrow.
     */
    private function tenantChannels(NotificationType $type): ?array
    {
        $setting = $type->setting;

        if ($setting === null) {
            return null;
        }

        if (! $setting->is_enabled) {
            return [];
        }

        if ($setting->channels === null) {
            return null;
        }

        return $this->normalizeChannels($setting->channels);
    }

    /**
     * @return list<string>|null Null when layer 3 does not narrow.
     */
    private function memberChannels(NotificationType $type, Member $recipient): ?array
    {
        $userId = User::query()
            ->where('member_id', $recipient->id)
            ->value('id');

        if ($userId === null) {
            return null;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('notification_type_id', $type->id)
            ->first();

        if ($preference === null) {
            return null;
        }

        if ($preference->is_muted) {
            return [];
        }

        if ($preference->channels === null) {
            return null;
        }

        return $this->normalizeChannels($preference->channels);
    }
}
