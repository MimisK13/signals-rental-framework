<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

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
