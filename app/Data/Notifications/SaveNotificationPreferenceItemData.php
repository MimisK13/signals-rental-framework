<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

class SaveNotificationPreferenceItemData extends Data
{
    /**
     * @param  list<string>|null  $channels
     */
    public function __construct(
        public int $notification_type_id,
        public ?array $channels = null,
        public bool $is_muted = false,
    ) {}
}
