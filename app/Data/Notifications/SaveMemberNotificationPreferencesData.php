<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

class SaveMemberNotificationPreferencesData extends Data
{
    /**
     * @param  list<string>|null  $channels
     */
    public function __construct(
        public int $targetUserId,
        public int $notificationTypeId,
        public ?array $channels = null,
        public bool $isMuted = false,
    ) {}
}
