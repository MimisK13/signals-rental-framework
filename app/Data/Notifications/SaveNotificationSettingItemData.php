<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

class SaveNotificationSettingItemData extends Data
{
    /**
     * @param  list<string>|null  $channels
     * @param  list<array<string, mixed>>|null  $recipient_rules
     * @param  array<string, array{channels?: list<string>, recipient_rules?: list<array<string, mixed>>}>|null  $audiences
     */
    public function __construct(
        public int $notification_type_id,
        public ?bool $is_enabled = null,
        public ?array $channels = null,
        public ?array $recipient_rules = null,
        public ?array $audiences = null,
    ) {}
}
