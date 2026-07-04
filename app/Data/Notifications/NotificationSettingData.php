<?php

namespace App\Data\Notifications;

use App\Enums\NotificationAudience;
use App\Models\NotificationType;
use App\Services\Notifications\NotificationSettingsCatalog;
use Spatie\LaravelData\Data;

class NotificationSettingData extends Data
{
    /**
     * @param  list<string>  $available_channels
     * @param  list<string>  $default_channels
     * @param  list<string>  $channels
     * @param  list<array<string, mixed>>  $recipient_rules
     * @param  array<string, array{channels: list<string>, recipient_rules: list<array<string, mixed>>}>|null  $audiences
     */
    public function __construct(
        public int $notification_type_id,
        public string $notification_type_key,
        public string $category,
        public string $name,
        public ?string $description,
        public string $audience,
        public bool $is_system,
        public bool $is_active,
        public array $available_channels,
        public array $default_channels,
        public bool $is_enabled,
        public array $channels,
        public array $recipient_rules,
        public ?array $audiences = null,
    ) {}

    public static function fromType(NotificationType $type): self
    {
        $catalog = app(NotificationSettingsCatalog::class);
        $setting = $type->setting;

        $audiences = null;

        if ($type->audience === NotificationAudience::Both) {
            $audiences = [
                'internal' => [
                    'channels' => $catalog->effectiveChannelsFor($type, $setting, 'internal'),
                    'recipient_rules' => $catalog->recipientRulesFor($type, $setting, 'internal'),
                ],
                'external' => [
                    'channels' => $catalog->effectiveChannelsFor($type, $setting, 'external'),
                    'recipient_rules' => $catalog->recipientRulesFor($type, $setting, 'external'),
                ],
            ];
        }

        return new self(
            notification_type_id: $type->id,
            notification_type_key: $type->key,
            category: $type->category,
            name: $type->name,
            description: $type->description,
            audience: $type->audience->value,
            is_system: $type->is_system,
            is_active: $type->is_active,
            available_channels: $catalog->normalizeChannels($type->available_channels),
            default_channels: $catalog->normalizeChannels($type->default_channels),
            is_enabled: $setting === null || $setting->is_enabled,
            channels: $catalog->effectiveChannelsFor($type, $setting, 'default'),
            recipient_rules: $catalog->recipientRulesFor($type, $setting, 'default'),
            audiences: $audiences,
        );
    }
}
